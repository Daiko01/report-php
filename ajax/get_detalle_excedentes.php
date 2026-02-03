<?php
// ajax/get_detalle_excedentes.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if (!isset($_GET['bus_id']) || !isset($_GET['conductor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit;
}

$bus_id = (int)$_GET['bus_id'];
$conductor_id = (int)$_GET['conductor_id'];
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

try {
    $sql = "SELECT 
                p.id, 
                p.fecha, 
                p.nro_guia, 
                p.gasto_imposiciones as monto,
                b.numero_maquina,
                t.nombre as conductor
            FROM produccion_buses p
            JOIN buses b ON p.bus_id = b.id
            JOIN trabajadores t ON p.conductor_id = t.id
            WHERE p.bus_id = :bus_id
              AND p.conductor_id = :conductor_id
              AND MONTH(p.fecha) = :mes
              AND YEAR(p.fecha) = :ano
              AND p.gasto_imposiciones > 0
            ORDER BY p.fecha DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':bus_id' => $bus_id,
        ':conductor_id' => $conductor_id,
        ':mes' => $mes,
        ':ano' => $ano
    ]);

    $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format Date and Money
    foreach ($guias as &$g) {
        $g['fecha_formatted'] = date('d/m/Y', strtotime($g['fecha']));
        $g['monto_formatted'] = '$ ' . number_format($g['monto'], 0, ',', '.');
    }

    echo json_encode(['success' => true, 'data' => $guias]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
