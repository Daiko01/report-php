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

    <!-- Sección de Filtros Externa -->
    <div class="card shadow mb-4 border-bottom-primary">
        <div class="card-header py-3 bg-gradient-white" data-bs-toggle="collapse" data-bs-target="#filterCard" style="cursor:pointer;">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-search me-1"></i> Filtros de Búsqueda Avanzada
                <i class="fas fa-chevron-down float-end transition-icon"></i>
            </h6>
        </div>
        <div class="collapse show" id="filterCard">
            <div class="card-body bg-light">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="small fw-bold text-gray-600">Nombre</label>
                        <input type="text" class="form-control form-control-sm border-left-primary" id="filterNombre" placeholder="Buscar por Nombre...">
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-gray-600">RUT</label>
                        <input type="text" class="form-control form-control-sm" id="filterRut" placeholder="Buscar por RUT...">
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-gray-600">Estado</label>
                        <select class="form-select form-select-sm" id="filterEstado">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-gray-600">Previsión</label>
                        <select class="form-select form-select-sm" id="filterPrevision">
                            <option value="">Total</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="small fw-bold text-gray-600">Sindicato</label>
                        <select class="form-select form-select-sm" id="filterSindicato">
                            <option value="">Cualquiera</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-gray-600">Cargas</label>
                        <select class="form-select form-select-sm" id="filterCargas">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-sm btn-secondary w-100 shadow-sm" id="btnClearFilters">
                            <i class="fas fa-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>
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

        // --- 1. CONFIGURACIÓN DATATABLES Y FILTROS EXTERNOS ---
        var table = $('#tablaTrabajadores').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"i>>rt<"row"<"col-sm-12"p>>', // Custom DOM: Length, Info, Table, Pagination. No default Search.

            initComplete: function() {
                var api = this.api();

                // Helper para llenar selects
                function populateSelect(colIndex, selectId) {
                    var column = api.column(colIndex);
                    var select = $(selectId);

                    column.data().unique().sort().each(function(d, j) {
                        var cleanData = d;
                        // Si contiene tags HTML, extraer solo el texto
                        if (typeof d === 'string' && d.indexOf('<') !== -1) {
                            cleanData = $('<div>').html(d).text().trim();
                        }

                        cleanData = (cleanData) ? String(cleanData).trim() : '';

                        if (cleanData !== '' && !select.find('option[value="' + cleanData + '"]').length) {
                            select.append('<option value="' + cleanData + '">' + cleanData + '</option>');
                        }
                    });
                }

                // Llenar Selects Externos
                populateSelect(2, '#filterEstado');
                populateSelect(3, '#filterPrevision');
                populateSelect(4, '#filterSindicato');
                populateSelect(5, '#filterCargas');
            }
        });

        // 1.1 Icono de Colapso Dinámico
        $('#filterCard').on('show.bs.collapse', function() {
            $('.fa-chevron-down').css('transform', 'rotate(180deg)');
        });
        $('#filterCard').on('hide.bs.collapse', function() {
            $('.fa-chevron-down').css('transform', 'rotate(0deg)');
        });

        // Binding de Eventos para Filtros Externos

        // Texto
        $('#filterNombre').on('keyup change', function() {
            table.column(0).search(this.value).draw();
        });
        $('#filterRut').on('keyup change', function() {
            table.column(1).search(this.value).draw();
        });

        // Selects
        function bindSelect(id, colIndex) {
            $(id).on('change', function() {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                table.column(colIndex).search(val ? '^' + val + '$' : '', true, false).draw();
            });
        }

        bindSelect('#filterEstado', 2);
        bindSelect('#filterPrevision', 3);
        bindSelect('#filterSindicato', 4);
        bindSelect('#filterCargas', 5);

        // Limpiar Filtros
        $('#btnClearFilters').on('click', function() {
            $('#filterNombre, #filterRut').val('');
            $('#filterEstado, #filterPrevision, #filterSindicato, #filterCargas').val('');

            // Limpiar búsquedas
            table.columns().search('').draw();
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