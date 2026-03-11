<?php
// ajax/eliminar_guia_masivo.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere rol de administrador.']);
    exit;
}

$ids_json = $_POST['ids'] ?? '';
$ids = json_decode($ids_json, true);

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron registros válidos para eliminar.']);
    exit;
}

// Ensure all IDs are integers to prevent SQL Injection in the IN clause
$ids = array_map('intval', $ids);
$ids = array_filter($ids, function ($id) {
    return $id > 0;
});

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'IDs inválidos proporcionados.']);
    exit;
}

try {
    $inPart = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM produccion_buses WHERE id IN ($inPart)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);

    $deletedCount = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Se han eliminado correctamente $deletedCount registro(s)."
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
