<?php
// Cargar el bootstrap (inicia sesión y conecta a BD)
require_once __DIR__ . '/bootstrap.php'; // Estamos en /core, así que es directo

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Verificar CSRF antes de procesar nada
    verify_csrf_token();
    
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // 1. Buscar al usuario por username (RUT)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        // 2. Verificar si el usuario existe y si la contraseña es correcta
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 3. Verificar si está activo
            if ($user['is_active'] == 1) {
                // Éxito: Guardar datos en la sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nombre'] = $user['nombre_completo'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirigir al Dashboard
                header('Location: ' . BASE_URL . '/index.php');
                exit;

            } else {
                // Usuario inactivo
                $_SESSION['flash_message'] = [
                    'type' => 'warning',
                    'message' => 'Tu cuenta está deshabilitada.'
                ];
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
        } else {
            // Usuario o contraseña incorrecta
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Usuario o contraseña incorrectos.'
            ];
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }

    } catch (PDOException $e) {
        // Error de base de datos
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error al conectar: ' . $e->getMessage()
        ];
        header('Location: ../../login.php');
        exit;
    }
} else {
    // Si alguien intenta acceder directo
    header('Location: ../../login.php');
    exit;
}
?>