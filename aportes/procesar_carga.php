<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') { header('Location: cargar_aportes.php'); exit; }

$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];

if (!isset($_FILES['archivo_aportes']) || $_FILES['archivo_aportes']['error'] != 0) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error al subir el archivo.'];
    header('Location: cargar_aportes.php'); exit;
}

$tmp_name = $_FILES['archivo_aportes']['tmp_name'];

// Arrays para consolidación
$aportes_validos = []; // [rut => [monto => x, empresa => y]]
$lista_excedentes = []; // Para insertar en BD

// Preparar consultas
// 1. Buscar dueño del bus
$stmt_bus = $pdo->prepare("SELECT b.empleador_id, e.empresa_sistema FROM buses b JOIN empleadores e ON b.empleador_id = e.id WHERE b.numero_maquina = ? LIMIT 1");

// 2. Verificar contrato vigente en el periodo
$fecha_inicio_mes = "$ano-$mes-01";
$fecha_fin_mes = date('Y-m-t', strtotime($fecha_inicio_mes));

$stmt_contrato = $pdo->prepare("
    SELECT c.id FROM contratos c 
    JOIN trabajadores t ON c.trabajador_id = t.id
    WHERE t.rut = :rut 
    AND c.empleador_id = :eid
    AND c.fecha_inicio <= :fin_mes
    AND (c.esta_finiquitado = 0 OR c.fecha_finiquito >= :inicio_mes)
    AND (c.fecha_termino IS NULL OR c.fecha_termino >= :inicio_mes)
    LIMIT 1
");

$pdo->beginTransaction();
try {
    // Limpiar excedentes antiguos de este mes (opcional, para no duplicar si recargan)
    $pdo->prepare("DELETE FROM excedentes_aportes WHERE mes = ? AND ano = ?")->execute([$mes, $ano]);
    
    // Nota: No borramos 'aportes_externos' aun, lo hacemos al final para reemplazar solo lo que venga nuevo o sumar. 
    // Para mantenerlo simple: Borramos todo lo de este mes en aportes_externos para recargar limpio.
    $pdo->prepare("DELETE FROM aportes_externos WHERE mes = ? AND ano = ?")->execute([$mes, $ano]);

    if (($handle = fopen($tmp_name, "r")) !== FALSE) {
        
        // Detectar delimitador (mirar primera línea)
        $firstLine = fgets($handle);
        rewind($handle); // Volver al inicio
        
        if ($firstLine === false || empty(trim($firstLine))) {
            // Archivo vacío o error
            $delimiter = ','; // Default
        } else {
            $cntSemi = substr_count($firstLine, ';');
            $cntComma = substr_count($firstLine, ',');
            $delimiter = ($cntSemi > $cntComma) ? ';' : ',';
        }

        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            // Validar columnas mínimas (0:Maquina, 1:RUT, 2:Nombre, 3:Monto)
            if (count($data) < 4) continue;

            $maquina = trim($data[0]);
            $rut_crudo = trim($data[1]);
            
            // Limpiar RUT de puntos y guiones para tener el "raw" y convertir a MAYUSCULAS (k -> K)
            $rut_limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $rut_crudo));
            
            $nombre_conductor = trim($data[2]);
            // Convertir a UTF-8 si es necesario (simple fix para acentos básicos de Excel)
            if (!mb_check_encoding($nombre_conductor, 'UTF-8')) {
                $nombre_conductor = mb_convert_encoding($nombre_conductor, 'UTF-8', 'ISO-8859-1');
            }

            // Monto: Eliminar $, puntos y comas. Asumimos enteros.
            $monto = (int)preg_replace('/[^0-9]/', '', $data[3]);

            if (empty($rut_limpio) || $monto <= 0) continue;

            // 1. Identificar Empleador por Máquina
            $stmt_bus->execute([$maquina]);
            $info_bus = $stmt_bus->fetch();

            if (!$info_bus) {
                // ERROR: Máquina no existe -> Excedente
                $lista_excedentes[] = [
                    'rut' => $rut_crudo, 'nombre' => $nombre_conductor, 'maquina' => $maquina,
                    'monto' => $monto, 'empresa' => 'DESCONOCIDA', 'motivo' => 'Máquina no registrada'
                ];
                continue;
            }

            $empleador_id = $info_bus['empleador_id'];
            $empresa_sistema = $info_bus['empresa_sistema'];

            // 2. Verificar Contrato con ESE empleador
            // Usar formatearRUT para asegurar coincidencia con BD (ej: 12.345.678-9)
            $rut_bd = formatearRUT($rut_limpio);
            
            $stmt_contrato->execute([
                ':rut' => $rut_bd,
                ':eid' => $empleador_id,
                ':inicio_mes' => $fecha_inicio_mes,
                ':fin_mes' => $fecha_fin_mes
            ]);

            if ($stmt_contrato->fetch()) {
                // ES VALIDO: Sumar a consolidados
                // Clave compuesta para diferenciar si un chofer trabaja en BP y SOL el mismo mes (raro, pero posible)
                $key = $rut_limpio . '_' . $empresa_sistema; 
                
                if (!isset($aportes_validos[$key])) {
                    $aportes_validos[$key] = [
                        'rut' => $rut_bd, // Guardar RUT FORMATEADO (12.345.678-9)
                        'monto' => 0,
                        'empresa' => $empresa_sistema
                    ];
                }
                $aportes_validos[$key]['monto'] += $monto;

            } else {
                // ERROR: Sin contrato vigente -> Excedente
                $lista_excedentes[] = [
                    'rut' => $rut_crudo, 'nombre' => $nombre_conductor, 'maquina' => $maquina,
                    'monto' => $monto, 'empresa' => $empresa_sistema, 'motivo' => 'Sin contrato vigente con dueño del bus'
                ];
            }
        }
        fclose($handle);

        // 3. Insertar Válidos
        $stmt_ins_val = $pdo->prepare("INSERT INTO aportes_externos (rut_trabajador, monto, mes, ano, empresa_origen) VALUES (?, ?, ?, ?, ?)");
        foreach ($aportes_validos as $av) {
            $stmt_ins_val->execute([$av['rut'], $av['monto'], $mes, $ano, $av['empresa']]);
        }

        // 4. Insertar Excedentes
        $stmt_ins_exc = $pdo->prepare("INSERT INTO excedentes_aportes (mes, ano, rut_conductor, nombre_conductor, nro_maquina, monto, empresa_detectada, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($lista_excedentes as $ex) {
            $stmt_ins_exc->execute([$mes, $ano, $ex['rut'], $ex['nombre'], $ex['maquina'], $ex['monto'], $ex['empresa'], $ex['motivo']]);
        }

        $pdo->commit();
        
        $total_validos = count($aportes_validos);
        $total_excedentes = count($lista_excedentes);

        $_SESSION['flash_message'] = [
            'type' => $total_excedentes > 0 ? 'warning' : 'success',
            'message' => "Proceso terminado. Aportes cargados: $total_validos. Excedentes detectados: $total_excedentes."
        ];
        
        // Si hay excedentes, ir a la vista de excedentes. Si no, volver.
        if ($total_excedentes > 0) {
            header('Location: ver_excedentes.php?mes='.$mes.'&ano='.$ano);
        } else {
            header('Location: cargar_aportes.php');
        }
        exit;

    } else {
        throw new Exception("No se pudo abrir el archivo.");
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    header('Location: cargar_aportes.php'); exit;
}
?>