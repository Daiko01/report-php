<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php'; // Para esUnico()

if ($_SESSION['user_role'] != 'admin') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 2. Verificar que sea un POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // 3. Recoger y limpiar datos
    $id = (int)$_POST['id'];
    $nombre_completo = trim($_POST['nombre_completo']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    // 'is_active' se maneja diferente: si el checkbox no está marcado, no se envía.
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // 4. Validar Unicidad de Email (excluyendo este propio usuario)
    if (!esUnico($pdo, $email, 'users', 'email', $id)) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "El email '$email' ya está en uso por otro usuario."
        ];
        header('Location: ' . BASE_URL . '/admin/editar_usuario.php?id=' . $id);
        exit;
    }

    // 5. Actualizar la BD
    try {
        $sql = "UPDATE users SET 
                    nombre_completo = :nombre,
                    email = :email,
                    role = :role,
                    is_active = :active
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre_completo,
            ':email' => $email,
            ':role' => $role,
            ':active' => $is_active,
            ':id' => $id
        ]);

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => '¡Usuario actualizado exitosamente!'
        ];
        header('Location: ' . BASE_URL . '/admin/gestionar_usuarios.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error de Base de Datos: ' . $e->getMessage()
        ];
        header('Location: ' . BASE_URL . '/admin/editar_usuario.php?id=' . $id);
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/admin/gestionar_usuarios.php');
    exit;
}
