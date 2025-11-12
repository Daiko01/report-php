<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 3. Obtener los datos (JSON)
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

$id = $data['id'];

// 4. Eliminar de la BD
try {
    // NOTA: Si hay planillas asociadas, esto fallará por restricción FK.
    // Deberíamos manejar esa excepción.
    $stmt = $pdo->prepare("DELETE FROM empleadores WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el empleador.']);
    }

} catch (PDOException $e) {
    // Capturar error de restricción de clave foránea (FK)
    if ($e->getCode() == '23000') {
         echo json_encode(['success' => false, 'message' => 'Error: No se puede eliminar. El empleador ya tiene planillas o trabajadores asociados.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}
?>