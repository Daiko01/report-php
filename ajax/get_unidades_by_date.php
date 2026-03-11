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
    $sql = "SELECT DISTINCT u.id, u.numero 
            FROM unidades u
            JOIN terminales t ON t.unidad_id = u.id
            JOIN buses b ON b.terminal_id = t.id 
            JOIN produccion_buses pb ON pb.bus_id = b.id 
            WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ?
            ORDER BY u.numero ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $anio]);
    $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $unidades]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'data' => []]);
}
