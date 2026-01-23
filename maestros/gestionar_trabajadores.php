<?php

// 1. Cargar el núcleo

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

require_once dirname(__DIR__) . '/app/includes/session_check.php';

require_once dirname(__DIR__) . '/app/includes/header.php';



// 2. Obtener datos de trabajadores (Original query restoration, simplified)
try {
    $sql = "SELECT 
                t.id, t.rut, t.nombre, t.estado_previsional, t.tiene_cargas, t.numero_cargas, 
                t.sistema_previsional,
                a.nombre as nombre_afp,
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

    <!-- Sección de Filtros Externa (NUEVA) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-users me-1"></i> Trabajadores Registrados</h6>

            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>

        <div class="collapse border-top bg-light p-3" id="filterPanel">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Estado:</label>
                    <select class="form-select form-select-sm" id="filterEstado" autocomplete="off">
                        <option value="">Todos</option>
                        <option value="Activo">Activo</option>
                        <option value="Pensionado">Pensionado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Previsión:</label>
                    <select class="form-select form-select-sm" id="filterPrevision" autocomplete="off">
                        <option value="">Total</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Sindicato:</label>
                    <select class="form-select form-select-sm" id="filterSindicato" autocomplete="off">
                        <option value="">Cualquiera</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Cargas:</label>
                    <select class="form-select form-select-sm" id="filterCargas" autocomplete="off">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-sm btn-outline-secondary w-100" id="btnClearFilters">
                        <i class="fas fa-times me-1"></i> Limpiar
                    </button>
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
                            <th>Cargas</th> <!-- Restore Cargas column as requested -->
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
                                        <span class="badge bg-secondary">Pensionado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Activo</span>
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
            // Updated DOM to match gestionar_buses style more closely or keep custom
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',

            initComplete: function() {
                var api = this.api();

                // Styling Search
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar trabajador...');
                $('.dataTables_length select').addClass('form-select shadow-sm');

                // Helper para llenar selects dinámicos (Previsión, Sindicato)
                function populateSelect(colIndex, selectId, excludeNA = false) {
                    var column = api.column(colIndex);
                    var select = $(selectId);
                    // Solo llenar si está vacío
                    if (select.children('option').length <= 1) {
                        column.data().unique().sort().each(function(d, j) {
                            var cleanData = d;
                            if (typeof d === 'string' && d.indexOf('<') !== -1) {
                                cleanData = $('<div>').html(d).text().trim();
                            }
                            cleanData = (cleanData) ? String(cleanData).trim() : '';

                            if (excludeNA && (cleanData === 'N/A' || cleanData === '')) {
                                return;
                            }

                            if (cleanData !== '' && !select.find('option[value="' + cleanData + '"]').length) {
                                select.append('<option value="' + cleanData + '">' + cleanData + '</option>');
                            }
                        });
                    }
                }

                // Llenar Selects Externos desde la data de la tabla
                // Col 3: Previsión (Exclude N/A)
                // Col 4: Sindicato
                // Col 5: Cargas
                populateSelect(3, '#filterPrevision', true);
                populateSelect(4, '#filterSindicato');
                populateSelect(5, '#filterCargas');
            }
        });

        // --- EXTERNAL FILTERS LOGIC ---

        // Custom filtering function
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                const fEstado = $('#filterEstado').val();
                const fPrev = $('#filterPrevision').val();
                const fSind = $('#filterSindicato').val();
                const fCargas = $('#filterCargas').val();

                // Column indices updated:
                // 0: Nombre
                // 1: Rut
                // 2: Estado
                // 3: Prevision
                // 4: Sindicato
                // 5: Cargas

                const cellEstado = data[2] || "";
                const cellPrev = data[3] || "";
                const cellSind = data[4] || "";
                const cellCargas = data[5] || "";

                if (fEstado && !cellEstado.includes(fEstado)) return false;
                if (fPrev && !cellPrev.includes(fPrev)) return false;
                if (fSind && !cellSind.includes(fSind)) return false;
                if (fCargas && !cellCargas.includes(fCargas)) return false; // Exact match usually for numbers, but includes works if unique enough



                return true;
            }
        );

        // Event Listeners for Filters
        $('#filterEstado').on('change', function() {
            const val = $(this).val();
            if (val === 'Pensionado') {
                $('#filterPrevision').val('').prop('disabled', true);
            } else {
                $('#filterPrevision').prop('disabled', false);
            }
            table.draw();
        });

        $('#filterPrevision, #filterSindicato, #filterCargas').on('change', function() {
            table.draw();
        });

        $('#btnClearFilters').click(function() {
            // Reset Selects and trigger change to ensure UI updates and dependent logic runs
            $('#filterEstado').val('').trigger('change');
            $('#filterPrevision').val('').trigger('change');
            $('#filterSindicato').val('').trigger('change');
            $('#filterCargas').val('').trigger('change');

            // Clear Global Search Input
            $('.dataTables_filter input').val('');

            // Reset DataTable Search (Global + Columns)
            table.search('').columns().search('').draw();
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