<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

try {
    // Fetch ALL active workers with their CURRENT employer (if any)
    $stmt = $pdo->prepare("
        SELECT t.id, t.nombre, t.rut, e.id as empleador_id, e.nombre as empleador_nombre
        FROM trabajadores t
        LEFT JOIN contratos c ON t.id = c.trabajador_id AND (c.fecha_termino IS NULL OR c.fecha_termino >= CURDATE())
        LEFT JOIN empleadores e ON c.empleador_id = e.id
        WHERE t.estado_previsional != 'Inactivo'
        ORDER BY t.nombre ASC
    ");
    $stmt->execute();
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($drivers);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
