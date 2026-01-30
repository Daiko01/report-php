<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if (!isset($_GET['bus_id'])) {
    echo json_encode([]);
    exit;
}

$bus_id = (int)$_GET['bus_id'];
// Fixed Tariff List - Could be moved to config later
$tarifas = [140, 270, 430, 500, 820, 1000];
$folios = [];

try {
    foreach ($tarifas as $t) {
        // Find the last end folio for this tariff and bus
        $stmt = $pdo->prepare("
            SELECT d.folio_fin 
            FROM produccion_detalle_boletos d
            JOIN produccion_buses p ON d.guia_id = p.id
            WHERE p.bus_id = ? AND d.tarifa = ?
            ORDER BY p.fecha DESC, d.id DESC
            LIMIT 1
        ");
        $stmt->execute([$bus_id, $t]);
        $last = $stmt->fetchColumn();

        $folios[$t] = $last ? (int)$last : 0; // Default to 0 if no history
    }

    echo json_encode([
        'folios' => $folios,
        'gastos' => [] // Empty array as requested
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
