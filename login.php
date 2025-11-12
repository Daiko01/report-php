<?php
// Cargar bootstrap (solo para iniciar sesión y manejar mensajes de error)
require_once __DIR__ . '/app/core/bootstrap.php';
// Ejecutar el chequeo de sesión (redirige a index.php si ya está logueado)
require_once __DIR__ . '/app/includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Reportes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 10vh auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h3 class="text-center mb-4">Sistema de Reportes</h3>
            <p class="text-center text-muted mb-4">Acceso al sistema</p>
            
            <form action="app/core/login_process.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario (RUT)</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Ingresar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php
    // Manejo de Alertas Flash con SweetAlert2
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        $type = $flash['type']; // 'success', 'error', 'warning'
        $message = $flash['message'];
        
        // Limpiar el mensaje para que no se muestre de nuevo
        unset($_SESSION['flash_message']);

        echo "<script>
            Swal.fire({
                icon: '{$type}',
                title: '{$message}',
                toast: false,
                position: 'center',
                showConfirmButton: true
            });
        </script>";
    }
    ?>
</body>
</html>