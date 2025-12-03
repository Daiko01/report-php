<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// 2. Obtener datos de trabajadores (CON CORRECCIÓN INP/AFP)
try {
    $sql = "SELECT 
                t.id, t.rut, t.nombre, t.estado_previsional, t.tiene_cargas, t.numero_cargas, 
                t.sistema_previsional,  -- Campo para saber si es AFP o INP
                a.nombre as nombre_afp, -- Nombre de la AFP
                s.nombre as nombre_sindicato
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
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped datatable-es" id="tablaTrabajadores" width="100%">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Estado</th>
                            <th>Previsión</th>
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

                                <td>
                                    <?php
                                    if ($t['estado_previsional'] == 'Pensionado') {
                                        echo 'N/A';
                                    } elseif ($t['sistema_previsional'] == 'INP') {
                                        echo 'INP';
                                    } else {
                                        echo $t['nombre_afp'] ? htmlspecialchars($t['nombre_afp']) : 'AFP (Sin Asig.)';
                                    }
                                    ?>
                                </td>

                                <td><?php echo $t['nombre_sindicato'] ? htmlspecialchars($t['nombre_sindicato']) : 'N/A'; ?></td>
                                <td><?php echo $t['tiene_cargas'] ? $t['numero_cargas'] : 'No'; ?></td>

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
// 5. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
    $(document).ready(function() {

        // --- 1. LÓGICA DE FILTROS EN ENCABEZADO ---

        // Clonar la fila del encabezado para crear la fila de filtros
        $('#tablaTrabajadores thead tr').clone(true).addClass('filters').appendTo('#tablaTrabajadores thead');

        var table = $('#tablaTrabajadores').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true, // Activar extensión responsive
            orderCellsTop: true, // Indicar que los filtros están arriba
            fixedHeader: true,

            initComplete: function() {
                var api = this.api();

                // Iterar sobre cada columna visible
                api.columns([0, 1, 2, 3, 4, 5]).every(function() {
                    var column = this;
                    var cell = $('.filters th').eq($(column.header()).index()); // Obtener la celda del filtro
                    var title = $(column.header()).text().trim(); // Título de la columna

                    // Columnas con Filtro de Texto (Nombre, RUT)
                    if ([0, 1].includes(column.index())) {
                        $(cell).html('<input type="text" class="form-control form-control-sm" placeholder="Buscar..." />');
                        $('input', cell).on('keyup change clear', function() {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                    }
                    // Columnas con Filtro de Select (Estado, Previsión, Sindicato, Cargas)
                    else if ([2, 3, 4, 5].includes(column.index())) {
                        var select = $('<select class="form-select form-select-sm"><option value="">Todos</option></select>')
                            .appendTo(cell.empty())
                            .on('change', function() {
                                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                                // Buscar coincidencia exacta
                                column.search(val ? '^' + val + '$' : '', true, false).draw();
                            });

                        // Llenar el select con datos únicos de la columna
                        column.data().unique().sort().each(function(d, j) {
                            var cleanData = $(d).text().trim() || d; // Limpiar HTML (ej. badges)
                            if (cleanData && !select.find('option[value="' + cleanData + '"]').length) {
                                select.append('<option value="' + cleanData + '">' + cleanData + '</option>');
                            }
                        });
                    }
                });

                // Limpiar la celda de filtro para la columna "Acciones"
                $('.filters th').eq(6).html('');
            }
        });

        // --- 2. LÓGICA DE SWEETALERT PARA ELIMINAR ---

        // Usamos delegación de eventos en la tabla
        $('#tablaTrabajadores').on('click', '.btn-eliminar-trabajador', function() {
            var $btn = $(this);
            var trabajadorId = $btn.data('id');
            var nombre = $btn.data('nombre');

            Swal.fire({
                title: '¿Estás seguro?',
                text: `Vas a eliminar al trabajador ${nombre}. Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar petición AJAX
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_trabajador_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: trabajadorId
                            })
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