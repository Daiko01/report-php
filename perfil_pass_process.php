<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once __DIR__ . '/app/core/bootstrap.php';

// 2. Verificar Sesión
require_once __DIR__ . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $user_id = $_SESSION['user_id'];

    // 4. Validación 1: Nuevas contraseñas deben coincidir
    if ($new_password !== $confirm_password) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'La nueva contraseña y su confirmación no coinciden.'
        ];
        header('Location: ' . BASE_URL . '/perfil.php');
        exit;
    }
    
    // 5. Validación 2: Verificar la contraseña actual
    try {
        // Obtener el hash actual del usuario
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            // Requisito: Validar con password_verify()
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'La "Contraseña Actual" es incorrecta.'
            ];
            header('Location: ' . BASE_URL . '/perfil.php');
            exit;
        }

        // 6. Todo OK: Hashear la nueva clave y guardar
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :pass WHERE id = :id");
        $update_stmt->execute([
            ':pass' => $new_password_hash,
            ':id' => $user_id
        ]);

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => '¡Contraseña actualizada exitosamente!'
        ];
        header('Location: ' . BASE_URL . '/perfil.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error de Base de Datos: ' . $e->getMessage()
        ];
        header('Location: ' . BASE_URL . '/perfil.php');
        exit;
    }

} else {
    header('Location: ' . BASE_URL . '/perfil.php');
    exit;
}
?>