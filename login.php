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
    <title>Login - TransReport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="public/assets/css/style.css?v=1.1" rel="stylesheet">
</head>

<body class="login-page">
    <div class="split-screen">
        <!-- Columna Izquierda: Gráfico y Branding -->
        <div class="left-pane">
            <div class="left-content">
                <div class="mb-4">
                    <img src="public/assets/img/isotipo_transreport.svg" alt="Isotipo TransReport" style="height: 100px; width: auto;">
                </div>
                <h1 class="system-title">TransReport</h1>
                <p class="system-subtitle">Control de Operaciones y Gestión de Reportes</p>
            </div>
        </div>

        <!-- Columna Derecha: Formulario -->
        <div class="right-pane">
            <div class="login-box">
                <div class="brand-logo-container">
                    <!-- Placeholder para el logo -->
                    <img src="public/assets/img/logo_transreport.svg" alt="Logo TransReport" class="brand-logo mb-3">
                    <h2 class="welcome-text">Bienvenido de nuevo</h2>
                    <p class="sub-text">Ingresa tus credenciales para acceder</p>
                </div>

                <form action="app/core/login_process.php" method="POST">
                    <?php csrf_field(); ?>

                    <div class="mb-4">
                        <label for="username" class="form-label">Usuario (RUT)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" maxlength="12" placeholder="12.345.678-9" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="d-grid pt-2">
                        <button type="submit" class="btn btn-custom">
                            Ingresar <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-5 text-center text-muted" style="font-size: 0.8rem;">
                    &copy; <?php echo date('Y'); ?> <strong>DAIKO</strong>. Todos los derechos reservados.
                </div>
            </div>
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

        // Codificar variables para uso seguro en JS
        $typeSafe = json_encode($type);
        $messageSafe = json_encode($message);

        echo "<script>
            Swal.fire({
                icon: {$typeSafe},
                title: {$messageSafe},
                toast: false,
                position: 'center',
                showConfirmButton: true,
                confirmButtonColor: '#0dcaf0'
            });
        </script>";
    }
    ?>

    <script>
        document.getElementById('username').addEventListener('input', function(e) {
            let valor = e.target.value.replace(/[^0-9kK]/g, ''); // Eliminar todo lo que no sea número o K

            // Manejo del cuerpo y dígito verificador
            let cuerpo = valor.slice(0, -1);
            let dv = valor.slice(-1).toUpperCase();

            // Si está vacío o es muy corto, devolver limpio
            if (valor.length < 2) {
                e.target.value = valor.toUpperCase();
                return;
            }

            // Formatear con puntos
            // Invertimos, ponemos puntos cada 3, y volvemos a invertir
            cuerpo = cuerpo.split('').reverse().join('').replace(/(\d{3})(?=\d)/g, '$1.').split('').reverse().join('');

            // Resultado final con guión
            e.target.value = cuerpo + '-' + dv;
        });
    </script>
</body>

</html>