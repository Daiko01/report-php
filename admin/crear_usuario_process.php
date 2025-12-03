<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión y Rol de Admin
require_once dirname(__DIR__) . '/app/includes/session_check.php';
if ($_SESSION['user_role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// 3. Cargar la librería de utilidades (para validarRUT y esUnico)
require_once dirname(__DIR__) . '/app/lib/utils.php';

// 4. Verificar que sea un POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 5. Recoger y limpiar datos
    $nombre_completo = trim($_POST['nombre_completo']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']); // RUT
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    
    // 6. Validaciones de Backend
    
    // 6.1. Validar formato RUT (Módulo 11)
    if (!validarRUT($username)) {
        $_SESSION['flash_message'] = [
            'type' => 'error', // Requisito: Popup de error
            'message' => 'El RUT ingresado no es válido (Dígito verificador incorrecto).'
        ];
        header('Location: ' . BASE_URL . '/admin/crear_usuario.php');
        exit;
    }
    
    // Formatear el RUT antes de guardarlo (para consistencia)
    $username_formateado = formatearRUT($username);

    // 6.2. Validar Unicidad de RUT (Username)
    if (!esUnico($pdo, $username_formateado, 'users', 'username')) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "El RUT '$username_formateado' ya está registrado como usuario."
        ];
        header('Location: ' . BASE_URL . '/admin/crear_usuario.php');
        exit;
    }

    // 6.3. Validar Unicidad de Email
    if (!esUnico($pdo, $email, 'users', 'email')) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "El email '$email' ya está registrado."
        ];
        header('Location: ' . BASE_URL . '/admin/crear_usuario.php');
        exit;
    }
    
    // 7. Todos los chequeos OK. Hashear clave y guardar.
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, nombre_completo, email, password_hash, role, is_active) 
             VALUES (:username, :nombre, :email, :pass, :role, 1)" // Nace activo (is_active = 1)
        );
        
        $stmt->execute([
            ':username' => $username_formateado,
            ':nombre' => $nombre_completo,
            ':email' => $email,
            ':pass' => $password_hash,
            ':role' => $role
        ]);
        
        // 8. Mensaje de Éxito (Requisito: Tostada)
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => '¡Usuario creado exitosamente!'
        ];
        header('Location: ' . BASE_URL . '/admin/gestionar_usuarios.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error de Base de Datos: ' . $e->getMessage()
        ];
        header('Location: ' . BASE_URL . '/admin/crear_usuario.php');
        exit;
    }

} else {
    // Si no es POST, redirigir
    header('Location: ' . BASE_URL . '/admin/gestionar_usuarios.php');
    exit;
}
?>