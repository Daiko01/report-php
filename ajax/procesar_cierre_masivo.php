<?php
// ajax/procesar_cierre_masivo.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/calculos_cierre.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$mes = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : 0;

if ($mes <= 0 || $anio <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mes o año inválidos']);
    exit;
}

try {
    // 1. Obtener buses con producción en el mes
    $query = "SELECT DISTINCT b.id 
              FROM buses b
              JOIN produccion_buses pb ON b.id = pb.bus_id
              WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$mes, $anio]);
    $buses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($buses) === 0) {
        echo json_encode(['success' => false, 'message' => 'No hay buses con producción para este mes.']);
        exit;
    }

    // 2. Pre-cargar parámetros mensuales una sola vez para optimizar
    $stmtP = $pdo->prepare("SELECT * FROM parametros_mensuales WHERE mes = ? AND anio = ?");
    $stmtP->execute([$mes, $anio]);
    $parametros_mensuales = $stmtP->fetch(PDO::FETCH_ASSOC);

    // 3. Preparar Query de Insert/Update
    // NOTA: En el UPDATE solo tocamos los campos calculados/fijos.
    // Los campos manuales (anticipos, otros_ingresos, etc.) NO se tocan en el UPDATE para no borrar data ingresada.
    $sqlInitial = "INSERT INTO cierres_maquinas 
            (bus_id, mes, anio, 
             subsidio_operacional, devolucion_minutos, otros_ingresos_1, otros_ingresos_2, otros_ingresos_3,
             anticipo, asignacion_familiar, pago_minutos, saldo_anterior, ayuda_mutua, servicio_grua, poliza_seguro,
             valor_vueltas_directo, valor_vueltas_local, cant_vueltas_directo, cant_vueltas_local,
             monto_leyes_sociales, monto_administracion_aplicado,
             derechos_loza, seguro_cartolas, gps, boleta_garantia, boleta_garantia_dos, estado)
            VALUES 
            (?, ?, ?, 
             0, 0, 0, 0, 0, 
             0, 0, 0, 0, 0, 0, 0,
             0, 0, 0, 0,
             ?, ?, 
             ?, ?, ?, ?, ?, 'Cerrado')
            ON DUPLICATE KEY UPDATE
             monto_leyes_sociales = VALUES(monto_leyes_sociales),
             monto_administracion_aplicado = VALUES(monto_administracion_aplicado),
             derechos_loza = VALUES(derechos_loza),
             seguro_cartolas = VALUES(seguro_cartolas),
             gps = VALUES(gps),
             boleta_garantia = VALUES(boleta_garantia),
             boleta_garantia_dos = VALUES(boleta_garantia_dos),
             estado = 'Cerrado'";

    $stmtInsert = $pdo->prepare($sqlInitial);

    $count = 0;
    foreach ($buses as $bus_id) {
        // Calcular valores
        $calculos = calcular_cierre_bus($pdo, $bus_id, $mes, $anio, $parametros_mensuales);

        $defs = $calculos['defaults'];
        $leyes = $calculos['monto_leyes_sociales'];
        $admin = $calculos['admin_global'];

        // Ejecutar
        $stmtInsert->execute([
            $bus_id,
            $mes,
            $anio,
            // (Manuales van como 0 en el INSERT, pero ignorados en Update)
            $leyes,
            $admin,
            $defs['derechos_loza'],
            $defs['seguro_cartolas'],
            $defs['gps'],
            $defs['boleta_garantia'],
            $defs['boleta_garantia_dos']
        ]);
        $count++;

        // 4. PROCESAR EXCEDENTES (Exempt Workers)
        // La funcion calcular_cierre_bus ya nos devolvió la lista de trabajadores con el flag 'es_excedente'
        if (!empty($calculos['lista_trabajadores'])) {
            foreach ($calculos['lista_trabajadores'] as $trab) {
                if (!empty($trab['es_excedente']) && $trab['es_excedente']) {

                    // IDEMPOTENCIA: Borrar registro previo de este trabajador para este mes/bus
                    $stmtDelEx = $pdo->prepare("DELETE FROM excedentes_aportes WHERE bus_id = ? AND trabajador_id = ? AND mes = ? AND ano = ?");
                    $stmtDelEx->execute([$bus_id, $trab['trabajador_id'], $mes, $anio]);

                    // INSERTAR NUEVO
                    $monto_ex = (int)$trab['monto_excedente'];
                    if ($monto_ex > 0) {
                        $stmtInsEx = $pdo->prepare("INSERT INTO excedentes_aportes 
                            (bus_id, trabajador_id, mes, ano, monto, origen, nro_maquina, rut_conductor, nombre_conductor, motivo)
                            VALUES (?, ?, ?, ?, ?, 'CIERRE_MENSUAL', ?, ?, ?, 'Excedente por Exención')");
                        $stmtInsEx->execute([
                            $bus_id,
                            $trab['trabajador_id'],
                            $mes,
                            $anio,
                            $monto_ex,
                            $calc_nro_maquina = $trab['nro_maquina'] ?? $bus_id, // Usar el nro_maquina que viene de calculos, o fallback ID
                            $trab['rut'],
                            $trab['nombre']
                        ]);
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Se procesaron correctamente $count cierres de buses."
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
