<?php
// 1. Cargar el núcleo (Sesión y BD)
// Usamos __DIR__ para navegar correctamente desde /admin/
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión y Rol de Admin
require_once dirname(__DIR__) . '/app/includes/session_check.php';
if ($_SESSION['user_role'] != 'admin') {
    // Si un no-admin llega aquí, lo sacamos
    header('Location: ../index.php');
    exit;
}

// 3. Cargar el Header (Layout)
// (Usamos rutas relativas para los assets)
require_once dirname(__DIR__) . '/app/includes/header.php';

// 4. Obtener todos los usuarios de la BD
try {
    $stmt = $pdo->query("SELECT id, username, nombre_completo, email, role, is_active FROM users ORDER BY nombre_completo ASC");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
    // Manejar error
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Usuarios</h1>
        <a href="crear_usuario.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Crear Nuevo Usuario
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Usuarios del Sistema</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" id="dataTableUsuarios" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Usuario (RUT)</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['role']); ?></td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar_usuario.php?id=<?php echo $u['id']; ?>" class="btn btn-warning btn-circle btn-sm" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>

                                    <?php
                                    // Requisito: Botón para Habilitar/Deshabilitar
                                    // No puedes deshabilitar a tu propio usuario (admin)
                                    if ($_SESSION['user_id'] != $u['id']):
                                        $accion = $u['is_active'] ? 'deshabilitar' : 'habilitar';
                                        $texto = $u['is_active'] ? 'Deshabilitar' : 'Habilitar';
                                        $icono = $u['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on';
                                        $color = $u['is_active'] ? 'btn-secondary' : 'btn-success';
                                        $nuevo_estado = $u['is_active'] ? 0 : 1;
                                    ?>
                                        <button class="btn <?php echo $color; ?> btn-circle btn-sm btn-toggle-estado"
                                            data-id="<?php echo $u['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($u['nombre_completo']); ?>"
                                            data-accion="<?php echo $accion; ?>"
                                            data-nuevo-estado="<?php echo $nuevo_estado; ?>"
                                            title="<?php echo $texto; ?> usuario">
                                            <i class="fas <?php echo $icono; ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
// 5. Cargar el Footer (Layout y JS)
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
    $(document).ready(function() {

        // Usamos delegación de eventos en la tabla por si DataTables la redibuja
        $('#dataTableUsuarios').on('click', '.btn-toggle-estado', function() {
            var $btn = $(this);
            var userId = $btn.data('id');
            var nombre = $btn.data('nombre');
            var accion = $btn.data('accion');
            var nuevoEstado = $btn.data('nuevo-estado');

            // Requisito: Confirmación con SweetAlert2
            Swal.fire({
                title: `¿Estás seguro?`,
                text: `Vas a ${accion} al usuario ${nombre}.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accion}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // El usuario confirmó, enviar la petición AJAX

                    // Mostrar "Cargando..."
                    Swal.fire({
                        title: 'Procesando...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('../ajax/actualizar_usuario_estado.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: userId,
                                is_active: nuevoEstado
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    '¡Actualizado!',
                                    `El usuario ${nombre} ha sido actualizado.`,
                                    'success'
                                ).then(() => {
                                    // Recargar la página para ver los cambios
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Error',
                                    data.message || 'No se pudo actualizar el usuario.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
                        });
                }
            });
        });
    });
</script>

<?php
// Cierre final (ya está en el footer)
?>