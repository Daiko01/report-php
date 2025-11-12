<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';

// 3. Obtener todos los trabajadores (con JOINs)
try {
    $sql = "SELECT t.*, a.nombre as nombre_afp, s.nombre as nombre_sindicato
            FROM trabajadores t
            LEFT JOIN afps a ON t.afp_id = a.id
            LEFT JOIN sindicatos s ON t.sindicato_id = s.id
            ORDER BY t.nombre ASC";
    $stmt = $pdo->query($sql);
    $trabajadores = $stmt->fetchAll();
} catch (PDOException $e) {
    $trabajadores = [];
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Trabajadores</h1>
        <a href="crear_trabajador.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Crear Nuevo Trabajador
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Trabajadores</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" id="tablaTrabajadores" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Estado</th>
                            <th>AFP</th>
                            <th>Sindicato</th>
                            <th>Cargas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trabajadores as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($t['rut']); ?></td>
                            <td>
                                <?php if ($t['estado_previsional'] == 'Pensionado'): ?>
                                    <span class="badge bg-info">Pensionado</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Activo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $t['nombre_afp'] ? htmlspecialchars($t['nombre_afp']) : 'N/A'; ?></td>
                            <td><?php echo $t['nombre_sindicato'] ? htmlspecialchars($t['nombre_sindicato']) : 'N/A'; ?></td>
                            <td>
                                <?php echo $t['tiene_cargas'] ? $t['numero_cargas'] : 'No'; ?>
                            </td>
                            <td>
                                <a href="editar_trabajador.php?id=<?php echo $t['id']; ?>" class="btn btn-warning btn-circle btn-sm" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                
                                <button class="btn btn-danger btn-circle btn-sm btn-eliminar-trabajador" 
                                        data-id="<?php echo $t['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
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
// 4. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
$(document).ready(function() {
    $('#tablaTrabajadores').on('click', '.btn-eliminar-trabajador', function() {
        var $btn = $(this);
        var trabajadorId = $btn.data('id');
        var nombre = $btn.data('nombre');

        Swal.fire({
            title: `¿Estás seguro?`,
            text: `Vas a eliminar al trabajador ${nombre}. ¡Esta acción no se puede deshacer!`,
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
                fetch('<?php echo BASE_URL; ?>/ajax/eliminar_trabajador_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: trabajadorId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            '¡Eliminado!',
                            `El trabajador ${nombre} ha sido eliminado.`,
                            'success'
                        ).then(() => {
                            location.reload(); // Recargar la página
                        });
                    } else {
                        Swal.fire(
                            'Error',
                            data.message || 'No se pudo eliminar el trabajador.',
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