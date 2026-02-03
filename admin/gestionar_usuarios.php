<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión y Rol de Admin
require_once dirname(__DIR__) . '/app/includes/session_check.php';
if ($_SESSION['user_role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// 3. Obtener todos los usuarios de la BD
try {
    $stmt = $pdo->query("SELECT id, username, nombre_completo, email, role, is_active FROM users ORDER BY nombre_completo ASC");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
}

// 4. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Usuarios</h1>
        <a href="crear_usuario.php" class="btn btn-primary shadow-sm rounded-pill px-3">
            <i class="fas fa-user-plus fa-sm text-white-50 me-2"></i>Crear Nuevo Usuario
        </a>
    </div>

    <div class="card shadow border-0 mb-4">
        <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users me-2"></i>Lista de Usuarios del Sistema</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="dataTableUsuarios" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>RUT / Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="icon-circle bg-light text-primary me-3 text-xs" style="height: 2.5rem; width: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-gray-800"><?php echo htmlspecialchars($u['nombre_completo']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-monospace small"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td>
                                    <?php
                                    switch ($u['role']) {
                                        case 'admin':
                                            echo '<span class="badge bg-primary rounded-pill px-3"><i class="fas fa-shield-alt me-1"></i> Admin</span>';
                                            break;
                                        case 'contador':
                                            echo '<span class="badge bg-info text-dark rounded-pill px-3"><i class="fas fa-calculator me-1"></i> Contador</span>';
                                            break;
                                        case 'recaudador':
                                            echo '<span class="badge bg-warning text-dark rounded-pill px-3"><i class="fas fa-hand-holding-usd me-1"></i> Recaudador</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary rounded-pill px-3"><i class="fas fa-user me-1"></i> ' . htmlspecialchars($u['role']) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="editar_usuario.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-primary btn-sm" title="Editar" style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; margin-right: 5px;">
                                            <i class="fas fa-pencil-alt fa-xs"></i>
                                        </a>

                                        <?php if ($_SESSION['user_id'] != $u['id']):
                                            $accion = $u['is_active'] ? 'deshabilitar' : 'habilitar';
                                            $texto = $u['is_active'] ? 'Deshabilitar' : 'Habilitar';
                                            $icono = $u['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off';
                                            $clase = $u['is_active'] ? 'btn-outline-success' : 'btn-outline-secondary';
                                            $nuevo_estado = $u['is_active'] ? 0 : 1;
                                        ?>
                                            <button class="btn <?php echo $clase; ?> btn-sm btn-toggle-estado"
                                                data-id="<?php echo $u['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($u['nombre_completo']); ?>"
                                                data-accion="<?php echo $accion; ?>"
                                                data-nuevo-estado="<?php echo $nuevo_estado; ?>"
                                                title="<?php echo $texto; ?> usuario"
                                                style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas <?php echo $icono; ?> fa-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Init DataTable
        $('#dataTableUsuarios').DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
            },
            columnDefs: [{
                target: 4,
                orderable: false
            }]
        });

        // Toggle Status Logic
        $('#dataTableUsuarios').on('click', '.btn-toggle-estado', function() {
            var $btn = $(this);
            var userId = $btn.data('id');
            var nombre = $btn.data('nombre');
            var accion = $btn.data('accion');
            var nuevoEstado = $btn.data('nuevo-estado');

            Swal.fire({
                title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} usuario?`,
                text: `Estás a punto de ${accion} el acceso para ${nombre}.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: nuevoEstado ? '#1cc88a' : '#d33',
                cancelButtonColor: '#858796',
                confirmButtonText: `Sí, ${accion}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Procesando...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
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
                                Swal.fire('¡Actualizado!', `El usuario ha sido ${accion}do.`, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message || 'No se pudo actualizar.', 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Problema de conexión.', 'error'));
                }
            });
        });
    });
</script>