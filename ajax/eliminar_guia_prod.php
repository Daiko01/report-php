<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// ADMIN CHECK (Rol 'admin')
$role = $_SESSION['user_role'] ?? '';

if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado: Solo administradores.']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

$id = (int)$_POST['id'];

try {
    // Delete - Cascade should handle details if FK is set, but lets be safe
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM produccion_detalle_boletos WHERE guia_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM produccion_buses WHERE id = ?")->execute([$id]);
    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
