<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// --- CONFIGURACIÓN DE DATOS (SIN PAGINACIÓN PHP) ---
$id_sistema = ID_EMPRESA_SISTEMA;

try {
    // 1. Obtener TODOS los contratos para DataTables
    $sql = "SELECT c.*, t.nombre as trabajador_nombre, t.rut as trabajador_rut, e.nombre as empleador_nombre
            FROM contratos c
            JOIN trabajadores t ON c.trabajador_id = t.id
            JOIN empleadores e ON c.empleador_id = e.id
            WHERE e.empresa_sistema_id = :sys_id
            ORDER BY c.fecha_inicio DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sys_id' => $id_sistema]);
    $contratos = $stmt->fetchAll();

    $total_records = count($contratos);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function getPaginationUrl($p)
{
    $params = $_GET;
    $params['p'] = $p;
    return '?' . http_build_query($params);
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<style>
    :root {
        --primary-color: <?php echo COLOR_SISTEMA; ?>;
    }

    .btn-primary {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }

    .filter-bar {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e3e6f0;
    }

    .table-modern td {
        padding: 1rem;
        border-bottom: 1px solid #f8f9fc !important;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }

    .page-link {
        color: #5a5c69;
        border: none;
        background: transparent;
        font-weight: 600;
        border-radius: 8px !important;
    }

    .page-item.active .page-link {
        background-color: var(--primary-color) !important;
        color: white !important;
    }

    /* Animación sutil de carga */
    .loading-fade {
        opacity: 0.5;
        pointer-events: none;
        transition: opacity 0.2s;
    }
</style>

<div class="container-fluid px-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 mt-3">
        <div>
            <h1 class="h4 mb-0 text-gray-800 fw-bold">Gestión de Contratos</h1>
            <p class="small text-muted mb-0">Mostrando <span class="badge bg-dark rounded-pill"><?php echo $total_records; ?></span> resultados</p>
        </div>
        <a href="crear_contrato.php" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm rounded-pill">
            <i class="fas fa-plus me-1"></i> NUEVO CONTRATO
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
                    <div class="col-md-4">
                        <label class="small fw-bold text-gray-600">Trabajador (Nombre/RUT)</label>
                        <input type="text" class="form-control form-control-sm border-left-primary" id="filterTrabajador" placeholder="Buscar trabajador...">
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-gray-600">Empleador</label>
                        <select class="form-select form-select-sm" id="filterEmpleador">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-gray-600">Estado</label>
                        <select class="form-select form-select-sm" id="filterEstado">
                            <option value="">Todos</option>
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

    <div id="tableContainer" class="table-responsive shadow-sm rounded-3 bg-white">
        <table class="table table-modern table-hover align-middle mb-0 datatable-es" id="tablaContratos">
            <thead class="bg-light">
                <tr class="small text-muted fw-bold">
                    <th class="ps-4">TRABAJADOR</th>
                    <th>EMPLEADOR</th>
                    <th>PERIODO</th>
                    <th class="text-end">SUELDO BASE</th>
                    <th class="text-center">ESTADO</th>
                    <th class="text-end pe-4">GESTIÓN</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contratos as $c):
                    $hoy = date('Y-m-d');
                    if ($c['esta_finiquitado']) {
                        $label = "Finiquitado";
                        $color = "danger";
                        $sub = date('d/m/Y', strtotime($c['fecha_finiquito']));
                    } elseif ($c['fecha_termino'] && $c['fecha_termino'] < $hoy) {
                        $label = "Vencido";
                        $color = "warning";
                        $sub = "Plazo cumplido";
                    } else {
                        $label = "Vigente";
                        $color = "success";
                        $sub = "Activo";
                    }
                ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($c['trabajador_nombre']); ?></div>
                            <div class="text-muted small"><?php echo $c['trabajador_rut']; ?></div>
                        </td>
                        <td class="small fw-bold text-muted"><?php echo htmlspecialchars($c['empleador_nombre']); ?></td>
                        <td class="small">
                            <div class="text-dark fw-bold"><?php echo date('d/m/Y', strtotime($c['fecha_inicio'])); ?></div>
                            <div class="text-muted"><?php echo $c['fecha_termino'] ? date('d/m/Y', strtotime($c['fecha_termino'])) : 'Indefinido'; ?></div>
                        </td>
                        <td class="text-end fw-bold text-dark">$<?php echo number_format($c['sueldo_imponible'], 0, ',', '.'); ?></td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center">
                                <span class="status-dot bg-<?php echo $color; ?>"></span>
                                <span class="small fw-bold text-<?php echo $color; ?>"><?php echo $label; ?></span>
                            </div>
                            <div class="text-muted" style="font-size: 0.6rem;"><?php echo $sub; ?></div>
                        </td>
                        <td class="text-end pe-4">
                            <a href="editar_contrato.php?id=<?php echo $c['id']; ?>" class="btn btn-warning btn-sm btn-circle shadow-sm" title="Editar Contrato">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // --- CONFIGURACIÓN DATATABLES ---
        var table = $('#tablaContratos').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            orderCellsTop: true,
            fixedHeader: true,
            order: [
                [2, "desc"]
            ], // Ordenar por fecha inicio desc
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"i>>rt<"row"<"col-sm-12"p>>',

            initComplete: function() {
                var api = this.api();

                // Helper para llenar selects
                function populateSelect(colIndex, selectId) {
                    var column = api.column(colIndex);
                    var select = $(selectId);

                    column.data().unique().sort().each(function(d, j) {
                        var cleanData = d;
                        if (typeof d === 'string' && d.indexOf('<') !== -1) {
                            cleanData = $('<div>').html(d).text().trim();
                        }
                        cleanData = (cleanData) ? String(cleanData).trim() : '';

                        if (cleanData !== '' && !select.find('option[value="' + cleanData + '"]').length) {
                            select.append('<option value="' + cleanData + '">' + cleanData + '</option>');
                        }
                    });
                }

                populateSelect(1, '#filterEmpleador');
                // Estado lo dejamos manual o lo llenamos?
                // Mejor manual para asegurar etiquetas limpias
                var selectEstado = $('#filterEstado');
                if (selectEstado.children('option').length <= 1) { // Si solo tiene "Todos"
                    selectEstado.append('<option value="Vigente">Vigente</option>');
                    selectEstado.append('<option value="Vencido">Vencido</option>');
                    selectEstado.append('<option value="Finiquitado">Finiquitado</option>');
                }
            }
        });

        // Icono colapso
        $('#filterCard').on('show.bs.collapse', function() {
            $('.fa-chevron-down').css('transform', 'rotate(180deg)');
        });
        $('#filterCard').on('hide.bs.collapse', function() {
            $('.fa-chevron-down').css('transform', 'rotate(0deg)');
        });

        // Bindings
        $('#filterTrabajador').on('keyup change', function() {
            table.column(0).search(this.value).draw();
        });

        function bindSelect(id, colIndex) {
            $(id).on('change', function() {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                table.column(colIndex).search(val ? (colIndex === 4 ? val : '^' + val + '$') : '', true, false).draw();
            });
        }

        bindSelect('#filterEmpleador', 1);
        bindSelect('#filterEstado', 4); // Estado usa contains search simple

        $('#btnClearFilters').on('click', function() {
            $('#filterTrabajador, #filterEmpleador, #filterEstado').val('');
            table.columns().search('').draw();
        });
    });
</script>