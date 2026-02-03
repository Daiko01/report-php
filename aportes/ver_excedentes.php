<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');

// --- QUERIES ---

// 1. LIVE DATA QUERY
// Fetch aggregated surplus from daily guides for Exempt workers
$sql = "SELECT 
            p.bus_id, 
            p.conductor_id, 
            SUM(p.gasto_imposiciones) as monto,
            b.numero_maquina,
            t.rut,
            t.nombre as nombre_conductor
        FROM produccion_buses p
        JOIN buses b ON p.bus_id = b.id
        JOIN trabajadores t ON p.conductor_id = t.id
        WHERE MONTH(p.fecha) = :mes 
          AND YEAR(p.fecha) = :ano
          AND t.es_excedente = 1
          AND p.gasto_imposiciones > 0
        GROUP BY p.bus_id, p.conductor_id
        ORDER BY b.numero_maquina, t.nombre";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':mes' => $mes, ':ano' => $ano]);
    $excedentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $excedentes = [];
    $error = $e->getMessage();
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Excedentes (En Vivo)</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <!-- Form with auto-submit ID -->
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label">Mes</label>
                    <select class="form-select filter-autosubmit" name="mes">
                        <?php
                        $meses_arr = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
                        foreach ($meses_arr as $num => $nombre) {
                            $selected = ($num == $mes) ? 'selected' : '';
                            echo "<option value='$num' $selected>$nombre</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año</label>
                    <select class="form-select filter-autosubmit" name="ano">
                        <?php for ($y = date('Y') + 1; $y >= date('Y') - 1; $y--) {
                            $selected = ($y == $ano) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        } ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Listado de Excedentes Acumulados</h6>

            <?php if (!empty($excedentes)): ?>
                <a href="generar_pdf_excedentes_db.php?mes=<?= $mes ?>&ano=<?= $ano ?>" target="_blank" class="btn btn-danger">
                    <i class="fas fa-file-pdf me-2"></i> Imprimir Reporte (Cierre)
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable-basic">
                    <thead class="table-light">
                        <tr>
                            <th>N° Bus</th>
                            <th>RUT</th>
                            <th>Nombre Conductor</th>
                            <th class="text-end">Monto Acumulado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($excedentes as $ex): ?>
                            <tr>
                                <td class="fw-bold text-center"><?= htmlspecialchars($ex['numero_maquina']) ?></td>
                                <td><?= htmlspecialchars($ex['rut']) ?></td>
                                <td><?= htmlspecialchars($ex['nombre_conductor']) ?></td>
                                <td class="text-end fw-bold text-success">$ <?= number_format($ex['monto'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info text-white btn-detalle"
                                        data-bus="<?= $ex['bus_id'] ?>"
                                        data-cond="<?= $ex['conductor_id'] ?>"
                                        data-maquina="<?= htmlspecialchars($ex['numero_maquina']) ?>"
                                        data-nombre="<?= htmlspecialchars($ex['nombre_conductor']) ?>">
                                        <i class="fas fa-search me-1"></i> Ver Detalle
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

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title"><i class="fas fa-list-alt me-2"></i>Detalle de Excedentes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="fw-bold text-primary" id="modalTitulo"></h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered" width="100%">
                        <thead class="bg-light">
                            <tr>
                                <th>Fecha</th>
                                <th>N° Guía</th>
                                <th class="text-end">Monto (Imposiciones)</th>
                            </tr>
                        </thead>
                        <tbody id="tablaDetalleBody">
                            <!-- JS Load -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const filters = document.querySelectorAll('.filter-autosubmit');
        const form = document.getElementById('filterForm');

        filters.forEach(filter => {
            filter.addEventListener('change', function() {
                form.submit();
            });
        });

        // Detail Logic
        $('.btn-detalle').click(function() {
            const btn = $(this);
            const busId = btn.data('bus');
            const condId = btn.data('cond');
            const maquina = btn.data('maquina');
            const nombre = btn.data('nombre');
            const mes = '<?= $mes ?>';
            const ano = '<?= $ano ?>';

            $('#modalTitulo').text(`Bus N° ${maquina} - ${nombre}`);
            $('#tablaDetalleBody').html('<tr><td colspan="3" class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando datos...</td></tr>');

            const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
            modal.show();

            // Fetch
            fetch(`../ajax/get_detalle_excedentes.php?bus_id=${busId}&conductor_id=${condId}&mes=${mes}&ano=${ano}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        let html = '';
                        if (res.data.length === 0) {
                            html = '<tr><td colspan="3" class="text-center text-muted">No se encontraron detalles.</td></tr>';
                        } else {
                            res.data.forEach(g => {
                                html += `
                                    <tr>
                                        <td class="text-center">${g.fecha_formatted}</td>
                                        <td class="text-center fw-bold">${g.nro_guia}</td>
                                        <td class="text-end text-success fw-bold">${g.monto_formatted}</td>
                                    </tr>
                                `;
                            });
                        }
                        $('#tablaDetalleBody').html(html);
                    } else {
                        $('#tablaDetalleBody').html(`<tr><td colspan="3" class="text-center text-danger">Error: ${res.message}</td></tr>`);
                    }
                })
                .catch(err => {
                    $('#tablaDetalleBody').html(`<tr><td colspan="3" class="text-center text-danger">Fallo de conexión.</td></tr>`);
                });
        });
    });
</script>