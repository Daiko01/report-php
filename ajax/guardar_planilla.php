<?php
// ajax/guardar_planilla.php (Versi贸n refactorizada)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php'; // <-- USAMOS EL SERVICIO

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos.']);
    exit;
}

// CSRF Check
if (!isset($data['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF). Recargue la página.']);
    exit;
}

$empleador_id = $data['empleador_id'];
$mes = $data['mes'];
$ano = $data['ano'];
$trabajadores = $data['trabajadores'];

// 1. Instanciar el servicio
$calculoService = new CalculoPlanillaService($pdo);

// 2.a. Obtener Datos Históricos Globales (Mutual y SIS)
// Tasa Mutual (Empleador)
$stmt_emp = $pdo->prepare("SELECT tasa_mutual_decimal FROM empleadores WHERE id = ?");
$stmt_emp->execute([$empleador_id]);
$tasa_mutual_decimal = $stmt_emp->fetchColumn() ?: 0.0;
$tasa_mutual_aplicada = $tasa_mutual_decimal * 100; // Guardamos en porcentaje %

// SIS (Global vigente)
$stmt_sis = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes)) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");
$stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
$tasa_sis_decimal = $stmt_sis->fetchColumn() ?: 0.0;
$sis_aplicado = $tasa_sis_decimal * 100; // Guardamos en porcentaje %

$pdo->beginTransaction();
try {
    // 2.b Borrar planilla existente
    $delete_stmt = $pdo->prepare("DELETE FROM planillas_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
    $delete_stmt->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);

    if (empty($trabajadores)) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Planilla vaciada exitosamente.']);
        exit;
    }

    // 3. Preparar INSERT
    $sql = "INSERT INTO planillas_mensuales (mes, ano, empleador_id, trabajador_id, sueldo_imponible, bonos_imponibles, tipo_contrato, fecha_inicio, fecha_termino, dias_trabajados, aportes, adicional_salud_apv, cesantia_licencia_medica, cotiza_cesantia_pensionado, descuento_afp, afp_historico_nombre, descuento_salud, seguro_cesantia, sindicato, asignacion_familiar_calculada, tasa_mutual_aplicada, sis_aplicado, tipo_asignacion_familiar, tramo_asignacion_familiar, sueldo_base_snapshot, es_part_time_snapshot, tipo_jornada_snapshot) 
                VALUES (:mes, :ano, :eid, :tid, :sueldo, :bonos, :tipo, :f_inicio, :f_termino, :dias, :aportes, :adicional, :cesantia_lic, :cotiza_ces, :desc_afp, :afp_hist, :desc_salud, :desc_cesantia, :desc_sindicato, :desc_asig_fam, :tasa_mutual, :sis_app, :tipo_asig, :tramo_asig, :s_snapshot, :pt_snapshot, :j_snapshot)";
    $stmt_insert = $pdo->prepare($sql);

    // 4. Recorrer y llamar al servicio
    foreach ($trabajadores as $t) {

        // 5. LLAMAR AL SERVICIO DE CÁLCULO
        $calculos = $calculoService->calcularFila($t, $mes, $ano);

        $stmt_insert->execute([
            ':mes' => $mes,
            ':ano' => $ano,
            ':eid' => $empleador_id,
            ':tid' => $t['trabajador_id'],
            ':sueldo' => (int)$t['sueldo_imponible'],
            ':bonos' => (int)($t['bonos_imponibles'] ?? 0),
            ':tipo' => $t['tipo_contrato'],
            ':f_inicio' => $t['fecha_inicio'],
            ':f_termino' => !empty($t['fecha_termino']) ? $t['fecha_termino'] : null,
            ':dias' => (int)$t['dias_trabajados'],
            ':aportes' => (int)$t['aportes'],
            ':adicional' => (int)$t['adicional_salud_apv'],
            ':cesantia_lic' => (int)$t['cesantia_licencia_medica'],
            ':cotiza_ces' => (int)$t['cotiza_cesantia_pensionado'],
            // --- Valores del Servicio ---
            ':desc_afp' => $calculos['descuento_afp'],
            ':afp_hist' => $calculos['afp_historico_nombre'],
            ':desc_salud' => $calculos['descuento_salud'],
            ':desc_cesantia' => $calculos['seguro_cesantia'],
            ':desc_sindicato' => $calculos['sindicato'],
            ':desc_asig_fam' => $calculos['asignacion_familiar_calculada'],
            // --- Snapshot ---
            ':tasa_mutual' => $tasa_mutual_aplicada,
            ':sis_app' => $sis_aplicado,
            ':tipo_asig' => $calculos['tipo_asignacion_familiar'],
            ':tramo_asig' => $calculos['tramo_asignacion_familiar'],
            // --- Snapshots ---
            ':s_snapshot' => (int)$t['sueldo_imponible'],
            ':pt_snapshot' => (int)($t['es_part_time'] ?? 0),
            ':j_snapshot' => ($t['es_part_time'] ?? 0) ? 'Part Time' : 'Full Time'
        ]);
    }

    $pdo->commit();
    log_audit("Planilla Guardada", "Planilla $mes/$ano guardada para empleador $empleador_id. Trabajadores: " . count($trabajadores));
    echo json_encode(['success' => true, 'message' => 'Planilla guardada y calculada exitosamente!']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    exit;
}
