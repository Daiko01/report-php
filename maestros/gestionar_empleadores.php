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
                    <div class="col-md-3">
                        <label class="small fw-bold text-gray-600">Caja de Compensación</label>
                        <select class="form-select form-select-sm" id="filterCaja">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-gray-600">Mutual de Seguridad</label>
                        <select class="form-select form-select-sm" id="filterMutual">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-sm btn-secondary w-100 shadow-sm" id="btnClearFilters">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
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
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"i>>rt<"row"<"col-sm-12"p>>', // Custom DOM

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

                // Llenar Selects Externos: Caja(2), Mutual(3)
                populateSelect(2, '#filterCaja');
                populateSelect(3, '#filterMutual');
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

        bindSelect('#filterCaja', 2);
        bindSelect('#filterMutual', 3);

        // Limpiar Filtros
        $('#btnClearFilters').on('click', function() {
            $('#filterNombre, #filterRut, #filterCaja, #filterMutual').val('');
            table.columns().search('').draw();
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