<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Proteger (Solo admin/contador)
if (!in_array($_SESSION['user_role'], ['admin', 'contador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['empleador_id']) || !isset($data['mes']) || !isset($data['ano'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos para eliminar.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. Eliminar de 'planillas_mensuales'
    $stmt = $pdo->prepare(
        "DELETE FROM planillas_mensuales 
         WHERE empleador_id = :eid AND mes = :mes AND ano = :ano"
    );
    $stmt->execute([
        ':eid' => $data['empleador_id'],
        ':mes' => $data['mes'],
        ':ano' => $data['ano']
    ]);

    // 4. (Importante) Eliminar el registro de cierre si también existe
    $stmt_cierre = $pdo->prepare(
        "DELETE FROM cierres_mensuales 
         WHERE empleador_id = :eid AND mes = :mes AND ano = :ano"
    );
    $stmt_cierre->execute([
        ':eid' => $data['empleador_id'],
        ':mes' => $data['mes'],
        ':ano' => $data['ano']
    ]);

    $pdo->commit();

    echo json_encode(['success' => true]);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
