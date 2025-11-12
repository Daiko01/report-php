<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
if ($_SESSION['user_role'] != 'admin') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 2. Validar ID y obtener datos del usuario
if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/admin/gestionar_usuarios.php');
    exit;
}
$user_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT username, nombre_completo, email, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new Exception("Usuario no encontrado.");
    }
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: ' . BASE_URL . '/admin/gestionar_usuarios.php');
    exit;
}

// 3. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Usuario</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Datos de <?php echo htmlspecialchars($user['nombre_completo']); ?></h6>
        </div>
        <div class="card-body">

            <form action="editar_usuario_process.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $user_id; ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Usuario (RUT)</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nombre_completo" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($user['nombre_completo']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>Usuario (user)</option>
                            <option value="contador" <?php echo ($user['role'] == 'contador') ? 'selected' : ''; ?>>Contador (contador)</option>
                            <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrador (admin)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estado</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Activo (habilitado para iniciar sesión)</label>
                        </div>
                    </div>
                </div>

                <hr>
                <a href="gestionar_usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </form>

        </div>
    </div>
</div>

<?php
// 4. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>