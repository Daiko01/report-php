<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Genera un token CSRF y lo guarda en la sesión si no existe.
 * @return string El token CSRF.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Genera un campo input hidden con el token CSRF.
 */
function csrf_field() {
    $token = csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifica si el token CSRF recibido coincide con el de la sesión.
 * Si no coincide, detiene la ejecución y muestra un error.
 */
function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            // Log del error (opcional)
            error_log('Error de validación CSRF en: ' . $_SERVER['REQUEST_URI']);
            
            // Respuesta de error
            http_response_code(403);
            die('Error de seguridad: Token CSRF inválido o expirado. Por favor, recargue la página e intente nuevamente.');
        }
    }
}
?>
