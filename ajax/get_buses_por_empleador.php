<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if (!isset($_GET['empleador_id'])) {
    echo json_encode([]);
    exit;
}

$empleador_id = (int)$_GET['empleador_id'];

try {
    // Only fetch buses that belong to the current system via the employer
    $stmt = $pdo->prepare("
        SELECT b.id, b.numero_maquina, b.patente 
        FROM buses b
        JOIN empleadores e ON b.empleador_id = e.id
        WHERE e.id = ? AND e.empresa_sistema_id = ?
        ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC
    ");
    $stmt->execute([$empleador_id, ID_EMPRESA_SISTEMA]);
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($buses);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
