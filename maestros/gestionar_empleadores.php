<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión (Cualquier usuario logueado)
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 3. Cargar el Header (Layout)
require_once dirname(__DIR__) . '/app/includes/header.php';

// 4. Obtener todos los empleadores (con JOIN para ver nombres de Caja/Mutual)
try {
    $sql = "SELECT e.*, c.nombre as nombre_caja, m.nombre as nombre_mutual 
            FROM empleadores e
            LEFT JOIN cajas_compensacion c ON e.caja_compensacion_id = c.id
            LEFT JOIN mutuales_seguridad m ON e.mutual_seguridad_id = m.id
            ORDER BY e.nombre ASC";
    $stmt = $pdo->query($sql);
    $empleadores = $stmt->fetchAll();
} catch (PDOException $e) {
    $empleadores = [];
    // Manejar error visualmente si es necesario
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Empleadores</h1>
        <a href="crear_empleador.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Crear Nuevo Empleador
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Empleadores</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" id="tablaEmpleadores" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Caja</th>
                            <th>Mutual</th>
                            <th>Tasa</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleadores as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($e['rut']); ?></td>
                            <td><?php echo $e['nombre_caja'] ? htmlspecialchars($e['nombre_caja']) : 'Sin Caja'; ?></td>
                            <td><?php echo htmlspecialchars($e['nombre_mutual']); ?></td>
                            <td><?php echo number_format($e['tasa_mutual_decimal'] * 100, 2, ',', '.'); ?>%</td>
                            <td>
                                <a href="editar_empleador.php?id=<?php echo $e['id']; ?>" class="btn btn-warning btn-circle btn-sm" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                
                                <button class="btn btn-danger btn-circle btn-sm btn-eliminar-empleador" 
                                        data-id="<?php echo $e['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($e['nombre']); ?>"
                                        title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
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
    
    // Delegación de eventos para el botón eliminar
    $('#tablaEmpleadores').on('click', '.btn-eliminar-empleador', function() {
        var $btn = $(this);
        var empleadorId = $btn.data('id');
        var nombre = $btn.data('nombre');

        // Requisito: Confirmación con SweetAlert2
        Swal.fire({
            title: `¿Estás seguro?`,
            text: `Vas a eliminar al empleador ${nombre}. ¡Esta acción no se puede deshacer!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar "Cargando..."
                Swal.fire({
                    title: 'Eliminando...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                // Enviar petición AJAX
                fetch('<?php echo BASE_URL; ?>/ajax/eliminar_empleador_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: empleadorId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            '¡Eliminado!',
                            `El empleador ${nombre} ha sido eliminado.`,
                            'success'
                        ).then(() => {
                            location.reload(); // Recargar la página
                        });
                    } else {
                        Swal.fire(
                            'Error',
                            data.message || 'No se pudo eliminar el empleador.',
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