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

    <!-- Sección de Filtros Externa (NUEVA) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-file-contract me-1"></i> Contratos Registrados</h6>

            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>

        <div class="collapse border-top bg-light p-3" id="filterPanel">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted">Empleador:</label>
                    <select class="form-select form-select-sm" id="filterEmpleador" autocomplete="off">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Estado:</label>
                    <select class="form-select form-select-sm" id="filterEstado" autocomplete="off">
                        <option value="">Todos</option>
                        <option value="Vigente">Vigente</option>
                        <option value="Por Vencer">Por Vencer</option>
                        <option value="Vencido">Vencido</option>
                        <option value="Finiquitado">Finiquitado</option>
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
                        $color = "danger"; // Red for Finiquitado
                        $sub = date('d/m/Y', strtotime($c['fecha_finiquito']));
                        $filterStatus = "Finiquitado";
                    } elseif ($c['fecha_termino'] && $c['fecha_termino'] < $hoy) {
                        $label = "Vencido";
                        $color = "warning"; // Yellow as requested for Vencido (though technically expired)
                        $sub = "Plazo cumplido";
                        $filterStatus = "Vencido";
                    } elseif ($c['fecha_termino'] && $c['fecha_termino'] <= date('Y-m-d', strtotime('+30 days'))) {
                        $label = "Vigente"; // Filter name
                        $color = "info"; // Blue/Info for Por Vencer

                        // Calculate days remaining
                        $fecha_termino = new DateTime($c['fecha_termino']);
                        $fecha_hoy = new DateTime($hoy);
                        $dias_restantes = $fecha_hoy->diff($fecha_termino)->days;
                        // Since logic block checks <= 30 days and >= hoy (implicit by else if above failing), days >= 0
                        // Does < hoy check time? date('Y-m-d') has no time.
                        // Technically if term < hoy, it's vencido. So here diff is positive.

                        // Use days + 1 because diff can be 0 if today is end date?
                        // Let's rely on diff->days (absolute). Since we know it's future or today.
                        $sub = "Por Vencer (" . $dias_restantes . " días)";
                        $filterStatus = "Por Vencer"; // Restored distinct filter status
                    } else {
                        $label = "Vigente";
                        $color = "success"; // Green for Vigente
                        $sub = "Activo";
                        $filterStatus = "Vigente";
                    }

                    if ($c['esta_finiquitado']) {
                        $label = "Finiquitado";
                        $color = "secondary";
                        $sub = date('d/m/Y', strtotime($c['fecha_finiquito']));
                        $filterStatus = "Finiquitado";
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
                        <td class="text-center" data-status="<?php echo $filterStatus; ?>">
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
            // Updated DOM
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',

            initComplete: function() {
                var api = this.api();

                // Styling Search
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar contrato...');
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

                populateSelect(1, '#filterEmpleador');
                // Estado is now hardcoded in HTML for specific options requested
            }
        });

        // --- EXTERNAL FILTERS LOGIC ---

        // Custom filtering function
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                const fEmp = $('#filterEmpleador').val();
                const fEstado = $('#filterEstado').val();

                // Column indices:
                // 1: Empleador
                // 4: Estado (w/ HTML)

                const cellEmp = data[1] || "";
                const cellEstado = data[4] || "";

                if (fEmp && !cellEmp.includes(fEmp)) return false;

                // Custom Logic for Status using data-attribute
                const node = settings.aoData[dataIndex].anCells[4];
                const statusValue = $(node).data('status');

                // Strict match since visual options match data-status values
                // "Por Vencer" filter will match "Por Vencer" data status
                if (fEstado && statusValue !== fEstado) return false;

                return true;
            }
        );

        // Bindings
        $('#filterEmpleador, #filterEstado').on('change', function() {
            table.draw();
        });

        $('#btnClearFilters').on('click', function() {
            $('#filterEmpleador').val('').trigger('change');
            $('#filterEstado').val('').trigger('change');

            // Clear Global Search Input
            $('.dataTables_filter input').val('');

            // Reset DataTable Search (Global + Columns)
            table.search('').columns().search('').draw();
        });
    });
</script>