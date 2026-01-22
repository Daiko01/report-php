<?php
// buses/check_reporte_info.php
// Endpoint AJAX para verificar si hay datos antes de generar reporte
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

$mes = isset($_POST['mes']) ? (int)$_POST['mes'] : date('n');
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : date('Y');
$filtro_tipo = $_POST['filtro_tipo'] ?? 'todos';
$bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
$empleador_id = isset($_POST['empleador_id']) ? (int)$_POST['empleador_id'] : 0;

$sql = "SELECT COUNT(*) FROM produccion_buses pb
        JOIN buses b ON pb.bus_id = b.id
        WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ? ";

$params = [$mes, $anio];

if ($filtro_tipo === 'bus' && $bus_id) {
    $sql .= " AND b.id = ?";
    $params[] = $bus_id;
} elseif ($filtro_tipo === 'empleador' && $empleador_id) {
    $sql .= " AND b.empleador_id = ? ";
    $params[] = $empleador_id;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();

    echo json_encode(['exists' => ($count > 0)]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
