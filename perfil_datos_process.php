<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once __DIR__ . '/app/core/bootstrap.php';

// 2. Verificar Sesión
require_once __DIR__ . '/app/includes/session_check.php';

// 3. Cargar librería (para 'esUnico')
require_once __DIR__ . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_token();

    $nombre_completo = trim($_POST['nombre_completo']);
    $email = trim($_POST['email']);
    $user_id = $_SESSION['user_id'];

    // 4. Validar Email (Debe ser único, EXCEPTO para mi propio ID)
    if (!esUnico($pdo, $email, 'users', 'email', $user_id)) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Ese email ya está en uso por otro usuario.'
        ];
        header('Location: ' . BASE_URL . '/perfil.php');
        exit;
    }

    // 5. Actualizar la base de datos
    try {
        $stmt = $pdo->prepare("UPDATE users SET nombre_completo = :nombre, email = :email WHERE id = :id");
        $stmt->execute([
            ':nombre' => $nombre_completo,
            ':email' => $email,
            ':id' => $user_id
        ]);

        // 6. Actualizar el nombre en la sesión (para que se vea en el Topbar)
        $_SESSION['user_nombre'] = $nombre_completo;

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => '¡Datos actualizados exitosamente!'
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
