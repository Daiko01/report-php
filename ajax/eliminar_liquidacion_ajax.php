<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

// 1. Validar Permisos (Solo Admin)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

// 2. Obtener Datos
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) && !isset($input['ids'])) {
    echo json_encode(['success' => false, 'message' => 'Falta ID de liquidación.']);
    exit;
}

$ids_to_delete = [];
if (isset($input['ids']) && is_array($input['ids'])) {
    $ids_to_delete = $input['ids'];
} elseif (isset($input['id'])) {
    $ids_to_delete[] = $input['id'];
}

if (empty($ids_to_delete)) {
    echo json_encode(['success' => false, 'message' => 'No hay registros para eliminar.']);
    exit;
}

// 3. Eliminar
try {
    // Usamos marcadores de posición para la cláusula IN
    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));

    $stmt = $pdo->prepare("DELETE FROM liquidaciones WHERE id IN ($placeholders)");

    if ($stmt->execute($ids_to_delete)) {
        echo json_encode(['success' => true, 'message' => 'Liquidaciones eliminadas correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar en la base de datos.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
}
