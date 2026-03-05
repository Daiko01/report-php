<?php
// ajax/eliminar_guia.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    // Solo el rol admin puede eliminar
    $role = $_SESSION['user_role'] ?? '';
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo administradores pueden eliminar guías.']);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de guía inválido.']);
        exit;
    }

    // Verificar que la guía existe
    $stmtCheck = $pdo->prepare("SELECT id, nro_guia, fecha FROM produccion_buses WHERE id = ?");
    $stmtCheck->execute([$id]);
    $guia = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$guia) {
        echo json_encode(['success' => false, 'message' => 'Guía no encontrada.']);
        exit;
    }

    // Eliminar el registro
    $stmtDel = $pdo->prepare("DELETE FROM produccion_buses WHERE id = ?");
    $stmtDel->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => "Guía Nº {$guia['nro_guia']} del " . date('d/m/Y', strtotime($guia['fecha'])) . " eliminada correctamente."
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
