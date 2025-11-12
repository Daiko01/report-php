<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Obtener los datos (JSON)
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

$id = $data['id'];

// 3. Eliminar de la BD
try {
    // Si hay planillas asociadas, la FK (ON DELETE CASCADE)
    // que pusimos en la BD debería borrarlas.
    // Si la regla fuera ON DELETE RESTRICT, esto fallaría.
    $stmt = $pdo->prepare("DELETE FROM trabajadores WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el trabajador.']);
    }

} catch (PDOException $e) {
    // Capturar error de restricción de clave foránea (FK)
    if ($e->getCode() == '23000') {
         echo json_encode(['success' => false, 'message' => 'Error: No se puede eliminar. El trabajador ya tiene planillas asociadas.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}
?>