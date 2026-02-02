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

    if ($user === false) {
        // Usuario no encontrado en BD (Sesión Zombie), forzar logout
        header('Location: logout.php');
        exit;
    }
} catch (PDOException $e) {
    // Manejar error, aunque es poco probable
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'No se pudieron cargar tus datos.'];
    $user = ['nombre_completo' => '', 'email' => '', 'username' => 'Error'];
}

// 4. Cargar el Header (Layout)
require_once __DIR__ . '/app/includes/header.php';
?>

<div class="container-fluid">

    <div class="row">
        <!-- Columna Izquierda: Tarjeta de Perfil Resumida -->
        <div class="col-xl-4 col-md-5 mb-4">
            <div class="card shadow border-0 text-center py-4">
                <div class="card-body">
                    <div class="mb-3">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px; font-size: 3rem;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <h4 class="font-weight-bold text-gray-800 mb-1"><?php echo htmlspecialchars($user['nombre_completo']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="d-inline-block bg-light rounded px-3 py-1 text-xs font-weight-bold text-uppercase text-primary border">
                        <i class="fas fa-id-card me-1"></i> RUT: <?php echo htmlspecialchars($user['username']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Formularios de Edición -->
        <div class="col-xl-8 col-md-7">
            <div class="card shadow border-0 mb-4">
                <div class="card-header py-3 bg-white border-bottom-0">
                    <ul class="nav nav-tabs nav-fill card-header-tabs" id="perfilTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-bold" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">
                                <i class="fas fa-user-cog me-2"></i>Mis Datos
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" id="pass-tab" data-bs-toggle="tab" data-bs-target="#pass" type="button" role="tab">
                                <i class="fas fa-lock me-2"></i>Seguridad
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- TAB: DATOS -->
                        <div class="tab-pane fade show active" id="datos" role="tabpanel">
                            <form action="perfil_datos_process.php" method="POST" class="needs-validation" novalidate>
                                <?php csrf_field(); ?>
                                <h6 class="heading-small text-muted mb-4">Información de Usuario</h6>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Nombre Completo</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-gray-400"></i></span>
                                        <input type="text" class="form-control border-start-0 ps-0" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($user['nombre_completo']); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Correo Electrónico</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-gray-400"></i></span>
                                        <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                                        <i class="fas fa-save me-2"></i>Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB: PASSWORD -->
                        <div class="tab-pane fade" id="pass" role="tabpanel">
                            <form action="perfil_pass_process.php" method="POST" class="needs-validation" novalidate>
                                <?php csrf_field(); ?>
                                <h6 class="heading-small text-muted mb-4">Cambiar Contraseña</h6>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Contraseña Actual</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-gray-400"></i></span>
                                        <input type="password" class="form-control border-start-0 ps-0 password-field" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary border-start-0 toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Nueva Contraseña</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-gray-400"></i></span>
                                            <input type="password" class="form-control border-start-0 ps-0 password-field" id="new_password" name="new_password" required>
                                            <button class="btn btn-outline-secondary border-start-0 toggle-password" type="button">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Confirmar Nueva Contraseña</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-check-circle text-gray-400"></i></span>
                                            <input type="password" class="form-control border-start-0 ps-0 password-field" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary border-start-0 toggle-password" type="button">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div id="password_match_feedback" class="small mt-1 fw-bold" style="min-height: 20px;"></div>
                                    </div>
                                </div>

                                <div class="alert alert-info py-2 small">
                                    <i class="fas fa-info-circle me-1"></i> Usa una contraseña segura que incluya números y letras.
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                                        <i class="fas fa-sync-alt me-2"></i>Actualizar Contraseña
                                    </button>
                                </div>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Validation Logic
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })();

        // Toggle Password Logic
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Real-time Password Match Validation
        const newPass = document.getElementById('new_password');
        const confirmPass = document.getElementById('confirm_password');
        const feedback = document.getElementById('password_match_feedback');

        function checkMatch() {
            const val1 = newPass.value;
            const val2 = confirmPass.value;

            if (!val2) {
                feedback.innerHTML = '';
                confirmPass.classList.remove('is-valid', 'is-invalid');
                return;
            }

            if (val1 === val2) {
                feedback.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>Las contraseñas coinciden</span>';
                confirmPass.classList.remove('is-invalid');
                confirmPass.classList.add('is-valid');
            } else {
                feedback.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>No coinciden</span>';
                confirmPass.classList.remove('is-valid');
                confirmPass.classList.add('is-invalid');
            }
        }

        if (newPass && confirmPass) {
            newPass.addEventListener('input', checkMatch);
            confirmPass.addEventListener('input', checkMatch);
        }
    });
</script>