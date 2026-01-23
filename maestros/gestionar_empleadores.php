<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// 2. Obtener empleadores filtrados por la Identidad del Sistema
try {
    // Usamos la constante ID_EMPRESA_SISTEMA definida en bootstrap.php
    $sql = "SELECT e.*, c.nombre as nombre_caja, m.nombre as nombre_mutual 
            FROM empleadores e
            LEFT JOIN cajas_compensacion c ON e.caja_compensacion_id = c.id
            LEFT JOIN mutuales_seguridad m ON e.mutual_seguridad_id = m.id
            WHERE e.empresa_sistema_id = " . ID_EMPRESA_SISTEMA . "
            ORDER BY e.nombre ASC";

    $stmt = $pdo->query($sql);
    $empleadores = $stmt->fetchAll();
} catch (PDOException $e) {
    $empleadores = [];
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-building text-primary me-2"></i>Gestión de Empleadores - <?php echo NOMBRE_SISTEMA; ?>
        </h1>
        <a href="crear_empleador.php" class="btn btn-primary shadow-sm fw-bold">
            <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Crear Nuevo Empleador
        </a>
    </div>

    <!-- Sección de Filtros Externa (NUEVA) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-building me-1"></i> Empleadores Registrados</h6>

            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>

        <div class="collapse border-top bg-light p-3" id="filterPanel">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Caja de Compensación:</label>
                    <select class="form-select form-select-sm" id="filterCaja" autocomplete="off">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Mutual de Seguridad:</label>
                    <select class="form-select form-select-sm" id="filterMutual" autocomplete="off">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
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
                <table class="table table-bordered table-hover datatable-es" id="tablaEmpleadores" width="100%">
                    <thead class="bg-light">
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Caja</th>
                            <th>Mutual</th>
                            <th>Tasa</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleadores as $e): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($e['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($e['rut']); ?></td>
                                <td><?php echo $e['nombre_caja'] ? htmlspecialchars($e['nombre_caja']) : '<span class="text-muted">Sin Caja</span>'; ?></td>
                                <td><?php echo htmlspecialchars($e['nombre_mutual']); ?></td>
                                <td class="text-center"><?php echo number_format($e['tasa_mutual_decimal'] * 100, 2, ',', '.'); ?>%</td>
                                <td class="text-center">
                                    <a href="editar_empleador.php?id=<?php echo $e['id']; ?>" class="btn btn-primary btn-sm rounded-pill px-3" title="Editar">
                                        <i class="fas fa-pencil-alt me-1"></i> Editar
                                    </a>

                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3 btn-eliminar-empleador"
                                        data-id="<?php echo $e['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($e['nombre']); ?>"
                                        title="Eliminar">
                                        <i class="fas fa-trash me-1"></i> Eliminar
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
// 3. Cargar el Footer (Layout y JS)
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
    $(document).ready(function() {

        // --- 1. CONFIGURACIÓN DATATABLES Y FILTROS EXTERNOS ---
        var table = $('#tablaEmpleadores').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            orderCellsTop: true,
            fixedHeader: true,
            // Updated DOM
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',

            initComplete: function() {
                var api = this.api();

                // Styling Search
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar empleador...');
                $('.dataTables_length select').addClass('form-select shadow-sm');

                // Helper para llenar selects
                function populateSelect(colIndex, selectId) {
                    var column = api.column(colIndex);
                    var select = $(selectId);

                    if (select.children('option').length <= 1) {
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
                }

                // Llenar Selects Externos: Caja(2), Mutual(3)
                populateSelect(2, '#filterCaja');
                populateSelect(3, '#filterMutual');
            }
        });

        // --- EXTERNAL FILTERS LOGIC ---

        // Custom filtering function
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                const fCaja = $('#filterCaja').val();
                const fMutual = $('#filterMutual').val();

                // Column indices:
                // 2: Caja
                // 3: Mutual

                const cellCaja = data[2] || "";
                const cellMutual = data[3] || "";

                if (fCaja && !cellCaja.includes(fCaja)) return false;
                if (fMutual && !cellMutual.includes(fMutual)) return false;

                return true;
            }
        );

        // Binding de Eventos para Filtros Externos
        $('#filterCaja, #filterMutual').on('change', function() {
            table.draw();
        });


        // Limpiar Filtros
        $('#btnClearFilters').on('click', function() {
            $('#filterCaja').val('').trigger('change');
            $('#filterMutual').val('').trigger('change');

            // Clear Global Search Input
            $('.dataTables_filter input').val('');

            // Reset DataTable Search (Global + Columns)
            table.search('').columns().search('').draw();
        });

        // LÓGICA DE ELIMINACIÓN CON SWEETALERT
        $('#tablaEmpleadores').on('click', '.btn-eliminar-empleador', function() {
            var $btn = $(this);
            var empleadorId = $btn.data('id');
            var nombre = $btn.data('nombre');

            Swal.fire({
                title: `¿Eliminar Empleador?`,
                text: `Se eliminará a ${nombre}. Se borrarán también sus buses y trabajadores asociados en esta base de datos.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar definitivamente',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_empleador_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: empleadorId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('¡Eliminado!', `El empleador ha sido borrado.`, 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        });
    });
</script>