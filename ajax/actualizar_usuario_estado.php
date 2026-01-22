<?php

// 1. Cargar el núcleo (Sesión y BD)

require_once dirname(__DIR__) . '/app/core/bootstrap.php';



// 2. Verificar Sesión y Rol de Admin

require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SESSION['user_role'] != 'admin') {

    http_response_code(403); // Forbidden

    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);

    exit;
}



// 3. Obtener los datos (JSON)

$data = json_decode(file_get_contents('php://input'), true);



if (!$data || !isset($data['id']) || !isset($data['is_active'])) {

    http_response_code(400); // Bad Request

    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);

    exit;
}



$id = $data['id'];

$is_active = $data['is_active'];



// 4. Validar que no se deshabilite a sí mismo (seguridad extra)

if ($id == $_SESSION['user_id']) {

    http_response_code(400);

    echo json_encode(['success' => false, 'message' => 'No puedes deshabilitarte a ti mismo.']);

    exit;
}



// 5. Actualizar la BD

try {

    $stmt = $pdo->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");

    $stmt->execute([

        ':is_active' => $is_active,

        ':id' => $id

    ]);



    if ($stmt->rowCount() > 0) {

        echo json_encode(['success' => true]);
    } else {

        echo json_encode(['success' => false, 'message' => 'No se encontró el usuario o ya estaba en ese estado.']);
    }
} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
