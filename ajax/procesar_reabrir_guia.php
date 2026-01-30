<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Validate Role (Admin or Accountant only)
$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin' && $role !== 'contador') {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para reabrir guías.']);
    exit;
}

// Check if IDs provided
if (!isset($_POST['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron guías para reabrir']);
    exit;
}

$ids = $_POST['ids'];

// If single ID passed as string/int, convert to array
if (!is_array($ids)) {
    $ids = [$ids];
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Lista de guías vacía']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Prepare statement
    $stmt = $pdo->prepare("UPDATE produccion_buses SET estado = 'Abierto' WHERE id = ?");

    $count = 0;
    foreach ($ids as $id) {
        $stmt->execute([$id]);
        $count += $stmt->rowCount();
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Se han reabierto $count guía(s) correctamente.",
        'count' => $count
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al reabrir guías: ' . $e->getMessage()]);
}
