<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

try {
    // Fetch ALL active workers with their CURRENT employer (if any)
    // We fetch raw data and deduplicate in PHP to pick the BEST/LATEST contract logic
    $stmt = $pdo->prepare("
        SELECT t.id, t.nombre, t.rut, t.es_excedente, 
               e.id as empleador_id, e.nombre as empleador_nombre,
               c.fecha_inicio
        FROM trabajadores t
        LEFT JOIN contratos c ON t.id = c.trabajador_id 
             AND (c.fecha_termino IS NULL OR c.fecha_termino >= CURDATE())
             AND (c.fecha_finiquito IS NULL OR c.fecha_finiquito >= CURDATE())
        LEFT JOIN empleadores e ON c.empleador_id = e.id
        WHERE t.estado_previsional != 'Inactivo'
        ORDER BY t.nombre ASC, c.fecha_inicio DESC
    ");
    $stmt->execute();
    $driversRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter/Format & Deduplicate
    $driversMap = [];
    foreach ($driversRaw as $d) {
        $id = $d['id'];

        // If already exists, check priority
        if (isset($driversMap[$id])) {
            // Priority: If current has employer and existing doesn't, swap (shouldn't happen due to ORDER BY date DESC usually, but safety)
            // Actually, ORDER BY c.fecha_inicio DESC puts the Newest contract first. 
            // So the FIRST time we see the ID, it is the Latest Active Contract match.
            // Consecutive rows will be older contracts (if multiple active overlap) or NULL contract rows (if left join behaves weird with multiple).
            // So we simply SKIP if exists.
            continue;
        }

        // Format Name
        $nombre = $d['nombre'];
        if (!empty($d['es_excedente']) && $d['es_excedente'] == 1) {
            $nombre .= ' (EXENTO)';
        }
        $d['nombre'] = $nombre;

        $driversMap[$id] = $d;
    }

    // Convert back to index array
    echo json_encode(array_values($driversMap));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
