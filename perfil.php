<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once __DIR__ . '/app/core/bootstrap.php';

// 2. Verificar Sesión (CUALQUIER usuario logueado puede estar aquí)
require_once __DIR__ . '/app/includes/session_check.php';

// 3. Obtener los datos actuales del usuario (para rellenar el formulario)
try {
    $stmt = $pdo->prepare("SELECT nombre_completo, email, username FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    // Manejar error, aunque es poco probable
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'No se pudieron cargar tus datos.'];
    $user = ['nombre_completo' => '', 'email' => '', 'username' => 'Error'];
}

// 4. Cargar el Header (Layout)
require_once __DIR__ . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Mi Perfil</h1>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                
                <div class="card-header py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab" aria-controls="datos" aria-selected="true">
                                <i class="fas fa-user-edit me-2"></i>Actualizar Datos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pass-tab" data-bs-toggle="tab" data-bs-target="#pass" type="button" role="tab" aria-controls="pass" aria-selected="false">
                                <i class="fas fa-key me-2"></i>Cambiar Contraseña
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="perfilTabsContent">

                        <div class="tab-pane fade show active" id="datos" role="tabpanel" aria-labelledby="datos-tab">
                            <h5 class="card-title mb-3">Información Personal</h5>
                            
                            <form action="perfil_datos_process.php" method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label class="form-label">Usuario (RUT)</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                    <div class="form-text">Tu usuario (RUT) no puede ser modificado.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                    <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($user['nombre_completo']); ?>" required>
                                    <div class="invalid-feedback">Tu nombre es obligatorio.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <div class="invalid-feedback">Ingresa un email válido.</div>
                                </div>
                                <hr>
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="pass" role="tabpanel" aria-labelledby="pass-tab">
                            <h5 class="card-title mb-3">Seguridad</h5>
                            
                            <form action="perfil_pass_process.php" method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <div class="invalid-feedback">Ingresa tu contraseña actual.</div>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="invalid-feedback">Ingresa una nueva contraseña.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Confirma tu nueva contraseña.</div>
                                </div>
                                <hr>
                                <button type="submit" class="btn btn-danger">Cambiar Contraseña</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 5. Cargar el Footer (Layout y JS)
require_once __DIR__ . '/app/includes/footer.php';
?>

<script>
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>