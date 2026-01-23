<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if (!isset($_POST['mes']) || !isset($_POST['ano']) || !isset($_POST['tipo_reporte'])) {
    echo json_encode(['exists' => false, 'error' => 'Faltan parámetros']);
    exit;
}

$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];
$tipo_reporte = $_POST['tipo_reporte'];
$id_sistema_actual = ID_EMPRESA_SISTEMA;

try {
    $exists = false;

    if ($tipo_reporte === 'sindicatos') {
        $sql = "SELECT 1 
                FROM planillas_mensuales p
                JOIN trabajadores t ON p.trabajador_id = t.id
                JOIN sindicatos s ON t.sindicato_id = s.id
                JOIN empleadores e ON p.empleador_id = e.id
                WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ? AND s.id IS NOT NULL
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes, $ano, $id_sistema_actual]);
        $exists = (bool)$stmt->fetchColumn();
    } elseif ($tipo_reporte === 'excedentes') {
        // Excedentes logic from report:
        // ((p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica)) > 0
        $sql = "SELECT 1
                FROM planillas_mensuales p
                JOIN empleadores e ON p.empleador_id = e.id
                WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ?
                AND ((p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica)) > 0
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes, $ano, $id_sistema_actual]);
        $exists = (bool)$stmt->fetchColumn();
    } elseif ($tipo_reporte === 'asignacion') {
        $sql = "SELECT 1
                FROM planillas_mensuales p
                JOIN empleadores e ON p.empleador_id = e.id
                WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ?
                AND p.asignacion_familiar_calculada > 0
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes, $ano, $id_sistema_actual]);
        $exists = (bool)$stmt->fetchColumn();
    }

    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
