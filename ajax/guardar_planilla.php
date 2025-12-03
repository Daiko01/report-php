<?php
// ajax/guardar_planilla.php (Versión refactorizada)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php'; // <-- USAMOS EL SERVICIO

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos.']);
    exit;
}

$empleador_id = $data['empleador_id'];
$mes = $data['mes'];
$ano = $data['ano'];
$trabajadores = $data['trabajadores'];

// 1. Instanciar el servicio
$calculoService = new CalculoPlanillaService($pdo);

$pdo->beginTransaction();
try {
    // 2. Borrar planilla existente
    $delete_stmt = $pdo->prepare("DELETE FROM planillas_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
    $delete_stmt->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);

    if (empty($trabajadores)) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Planilla vaciada exitosamente.']);
        exit;
    }

    // 3. Preparar INSERT
    $sql = "INSERT INTO planillas_mensuales (mes, ano, empleador_id, trabajador_id, sueldo_imponible, tipo_contrato, fecha_inicio, fecha_termino, dias_trabajados, aportes, adicional_salud_apv, cesantia_licencia_medica, cotiza_cesantia_pensionado, descuento_afp, descuento_salud, seguro_cesantia, sindicato, asignacion_familiar_calculada) 
            VALUES (:mes, :ano, :eid, :tid, :sueldo, :tipo, :f_inicio, :f_termino, :dias, :aportes, :adicional, :cesantia_lic, :cotiza_ces, :desc_afp, :desc_salud, :desc_cesantia, :desc_sindicato, :desc_asig_fam)";
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
            ':desc_salud' => $calculos['descuento_salud'],
            ':desc_cesantia' => $calculos['seguro_cesantia'],
            ':desc_sindicato' => $calculos['sindicato'],
            ':desc_asig_fam' => $calculos['asignacion_familiar_calculada']
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '¡Planilla guardada y calculada exitosamente!']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    exit;
}
