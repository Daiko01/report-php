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
$file_name = $_FILES['archivo_aportes']['name'];

// 1. Validaciones
$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if ($ext != 'csv') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Solo se permiten archivos CSV.'];
    header('Location: cargar_aportes.php'); exit;
}

$aportes_validos = []; 
$lista_excedentes = []; 

// 2. PREPARAR CONSULTAS NUEVAS (FASE 4: JOIN CON EMPRESAS_SISTEMA)

// A. Buscar dueño del bus y nombre OFICIAL de la empresa
// Usamos LEFT JOIN con empresas_sistema para traer el nombre limpio.
// Si no tiene asignada, hacemos fallback al campo antiguo 'empresa_sistema' (por seguridad)
$stmt_bus = $pdo->prepare("
    SELECT 
        b.empleador_id, 
        e.nombre as nombre_empleador, 
        COALESCE(es.nombre, e.empresa_sistema) as nombre_empresa_madre
    FROM buses b 
    JOIN empleadores e ON b.empleador_id = e.id 
    LEFT JOIN empresas_sistema es ON e.empresa_sistema_id = es.id
    WHERE b.numero_maquina = ? 
    LIMIT 1
");

// B. Verificar contrato
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

// C. Detective
$stmt_detective = $pdo->prepare("
    SELECT e.nombre 
    FROM contratos c 
    JOIN trabajadores t ON c.trabajador_id = t.id
    JOIN empleadores e ON c.empleador_id = e.id
    WHERE t.rut = :rut
    AND c.fecha_inicio <= :fin_mes
    AND (c.esta_finiquitado = 0 OR c.fecha_finiquito >= :inicio_mes)
    AND (c.fecha_termino IS NULL OR c.fecha_termino >= :inicio_mes)
    LIMIT 1
");

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM excedentes_aportes WHERE mes = ? AND ano = ?")->execute([$mes, $ano]);
    $pdo->prepare("DELETE FROM aportes_externos WHERE mes = ? AND ano = ?")->execute([$mes, $ano]);

    if (($handle = fopen($tmp_name, "r")) !== FALSE) {
        
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine ?? '', ';') > substr_count($firstLine ?? '', ',')) ? ';' : ',';

        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            if (count($data) < 4) continue;

            $maquina = trim($data[0]); 
            $rut_crudo = trim($data[1]);
            $nombre_conductor = trim($data[2]);
            
            $rut_limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $rut_crudo)); 
            
            if (!mb_check_encoding($nombre_conductor, 'UTF-8')) {
                $nombre_conductor = mb_convert_encoding($nombre_conductor, 'UTF-8', 'ISO-8859-1');
            }

            $monto = (int)preg_replace('/[^0-9]/', '', $data[3]); 

            if (empty($rut_limpio) || $monto <= 0) continue;

            // --- LÓGICA DE BUSES FASE 4 ---

            // 1. Identificar Empleador
            $stmt_bus->execute([$maquina]);
            $info_bus = $stmt_bus->fetch();

            if (!$info_bus) {
                $lista_excedentes[] = [
                    'rut' => $rut_crudo, 'nombre' => $nombre_conductor, 'maquina' => $maquina,
                    'monto' => $monto, 'empresa' => 'DESCONOCIDA', 'motivo' => 'Máquina no registrada'
                ];
                continue;
            }

            $empleador_id_bus = $info_bus['empleador_id'];
            $nombre_empleador_bus = $info_bus['nombre_empleador'];
            $empresa_sistema = $info_bus['nombre_empresa_madre']; // Nombre limpio desde la nueva tabla

            // 2. Verificar Contrato
            $rut_bd = number_format(substr($rut_limpio, 0, -1), 0, ',', '.') . '-' . substr($rut_limpio, -1);
            
            $stmt_contrato->execute([
                ':rut' => $rut_bd,
                ':eid' => $empleador_id_bus,
                ':inicio_mes' => $fecha_inicio_mes,
                ':fin_mes' => $fecha_fin_mes
            ]);

            if ($stmt_contrato->fetch()) {
                // VALIDO
                $key = $rut_limpio . '_' . $empresa_sistema; 
                
                if (!isset($aportes_validos[$key])) {
                    $aportes_validos[$key] = [
                        'rut' => $rut_limpio, // Guardamos limpio
                        'monto' => 0,
                        'empresa' => $empresa_sistema
                    ];
                }
                $aportes_validos[$key]['monto'] += $monto;

            } else {
                // EXCEDENTE
                $stmt_detective->execute([':rut' => $rut_bd, ':inicio_mes' => $fecha_inicio_mes, ':fin_mes' => $fecha_fin_mes]);
                $otro_empleador = $stmt_detective->fetchColumn();

                if ($otro_empleador) {
                    $motivo = "CONFLICTO: Bus de $nombre_empleador_bus, pero Chofer es de $otro_empleador";
                } else {
                    $motivo = "Sin contrato vigente en el sistema";
                }

                $lista_excedentes[] = [
                    'rut' => $rut_crudo, 'nombre' => $nombre_conductor, 'maquina' => $maquina,
                    'monto' => $monto, 'empresa' => $empresa_sistema, 'motivo' => $motivo
                ];
            }
        }
        fclose($handle);

        // 3. Insertar
        $stmt_ins_val = $pdo->prepare("INSERT INTO aportes_externos (rut_trabajador, monto, mes, ano, empresa_origen) VALUES (?, ?, ?, ?, ?)");
        foreach ($aportes_validos as $av) {
            $stmt_ins_val->execute([$av['rut'], $av['monto'], $mes, $ano, $av['empresa']]);
        }

        $stmt_ins_exc = $pdo->prepare("INSERT INTO excedentes_aportes (mes, ano, rut_conductor, nombre_conductor, nro_maquina, monto, empresa_detectada, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($lista_excedentes as $ex) {
            $stmt_ins_exc->execute([$mes, $ano, $ex['rut'], $ex['nombre'], $ex['maquina'], $ex['monto'], $ex['empresa'], $ex['motivo']]);
        }

        $pdo->commit();
        
        $total_validos = count($aportes_validos);
        $total_excedentes = count($lista_excedentes);
        
        if ($total_excedentes > 0) {
            session_start();
            $_SESSION['reporte_excedentes'] = [
                'empresa' => 'Múltiple (Automático)',
                'mes' => $mes,
                'ano' => $ano,
                'data' => $lista_excedentes
            ];
            header('Location: resultado_carga.php?registrados=' . $total_validos . '&excedentes=' . $total_excedentes);
        } else {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Carga exitosa. $total_validos aportes procesados."];
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