<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;

if ($mes <= 0 || $mes > 12 || $anio < 2000) {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

try {
    $sql = "SELECT DISTINCT e.id, e.nombre, e.rut 
            FROM empleadores e 
            JOIN buses b ON b.empleador_id = e.id 
            JOIN produccion_buses pb ON pb.bus_id = b.id 
            WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ?
            ORDER BY e.nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $anio]);
    $empleadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $empleadores]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'data' => []]);
}
