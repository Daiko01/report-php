<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// --- CONFIGURACIÓN DE LÍMITES Y PAGINACIÓN ---
$limit_options = [10, 15, 20, 'todos'];
$records_per_page = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? $_GET['limit'] : 15;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) $current_page = 1;

// --- LÓGICA DE FILTROS ---
$id_sistema = ID_EMPRESA_SISTEMA;
$empleador_id = !empty($_GET['empleador_id']) ? (int)$_GET['empleador_id'] : null;
$mes_inicio = !empty($_GET['mes_inicio']) ? (int)$_GET['mes_inicio'] : null;
$ano_inicio = !empty($_GET['ano_inicio']) ? (int)$_GET['ano_inicio'] : null;
$mes_termino = !empty($_GET['mes_termino']) ? (int)$_GET['mes_termino'] : null;
$ano_termino = !empty($_GET['ano_termino']) ? (int)$_GET['ano_termino'] : null;

try {
    // 2. Obtener lista de empleadores (Seguridad: solo de este sistema)
    $stmt_emp = $pdo->prepare("SELECT id, nombre FROM empleadores WHERE empresa_sistema_id = ? ORDER BY nombre");
    $stmt_emp->execute([$id_sistema]);
    $empleadores_lista = $stmt_emp->fetchAll();

    // 3. Construcción del WHERE dinámico (Optimizado)
    $where_sql = " WHERE e.empresa_sistema_id = :sys_id";
    $params = [':sys_id' => $id_sistema];

    if ($empleador_id) {
        $where_sql .= " AND p.empleador_id = :empleador_id";
        $params[':empleador_id'] = $empleador_id;
    }

    // Filtro de Periodo "Desde"
    if ($ano_inicio && $mes_inicio) {
        $where_sql .= " AND (p.ano > :ano_ini OR (p.ano = :ano_ini AND p.mes >= :mes_ini))";
        $params[':ano_ini'] = $ano_inicio;
        $params[':mes_ini'] = $mes_inicio;

        // Filtro de Periodo "Hasta" (Opcional)
        if ($ano_termino && $mes_termino) {
            $where_sql .= " AND (p.ano < :ano_fin OR (p.ano = :ano_fin AND p.mes <= :mes_fin))";
            $params[':ano_fin'] = $ano_termino;
            $params[':mes_fin'] = $mes_termino;
        }
    }

    // 4. Conteo Total (Ajustado para ser idéntico a la consulta principal)
    $count_sql = "SELECT COUNT(*) FROM (
                    SELECT p.mes 
                    FROM planillas_mensuales p 
                    JOIN empleadores e ON p.empleador_id = e.id 
                    $where_sql 
                    GROUP BY p.empleador_id, p.mes, p.ano
                  ) as subconsulta";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();

    // 5. Ajuste de Límite y Offset
    $limit_sql = "";
    $total_pages = 1;
    if ($records_per_page !== 'todos') {
        $total_pages = ceil($total_records / (int)$records_per_page);
        if ($current_page > $total_pages) $current_page = $total_pages;
        $offset = max(0, ($current_page - 1) * (int)$records_per_page);
        $limit_sql = " LIMIT " . (int)$records_per_page . " OFFSET $offset";
    }

    // 6. Consulta Final
    $sql = "SELECT p.empleador_id, p.mes, p.ano, e.nombre as empleador_nombre, e.rut as empleador_rut
            FROM planillas_mensuales p
            JOIN empleadores e ON p.empleador_id = e.id"
        . $where_sql .
        " GROUP BY p.empleador_id, p.mes, p.ano 
              ORDER BY p.ano DESC, p.mes DESC, e.nombre" . $limit_sql;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

function getPaginationUrl($p)
{
    $params = $_GET;
    $params['p'] = $p;
    return '?' . http_build_query($params);
}

$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<style>
    :root {
        --primary-color: <?php echo COLOR_SISTEMA; ?>;
    }

    .filter-bar {
        background: #fdfdfd;
        border-radius: 12px;
        border: 1px solid #e3e6f0;
    }

    .report-row:hover {
        background-color: #f8f9fc !important;
    }

    .page-link {
        color: #5a5c69;
        border: none;
        font-weight: 600;
        margin: 0 2px;
        border-radius: 8px !important;
    }

    .page-item.active .page-link {
        background-color: var(--primary-color) !important;
        color: #fff !important;
    }
</style>

<div class="container-fluid px-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 mt-3">
        <div>
            <h1 class="h4 mb-0 text-gray-800 fw-bold">Historial de Reportes</h1>
            <p class="small text-muted mb-0">Resultados: <span class="badge bg-primary rounded-pill"><?php echo $total_records; ?></span></p>
        </div>
        <button class="btn btn-success btn-sm fw-bold shadow-sm px-4" id="btn-descargar-zip" disabled>
            <i class="fas fa-file-archive me-2"></i> DESCARGAR ZIP
        </button>
    </div>

    <div class="card filter-bar shadow-sm mb-4 border-0">
        <div class="card-body">
            <form action="" method="GET" id="filtro-form" class="row g-2 align-items-end">
                <div class="col-lg-3">
                    <label class="form-label small fw-bold text-muted">EMPLEADOR</label>
                    <select class="form-select form-select-sm border-0 bg-light shadow-none" name="empleador_id" onchange="this.form.submit()">
                        <option value="">Todos los empleadores</option>
                        <?php foreach ($empleadores_lista as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($empleador_id == $emp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label small fw-bold text-muted">PERIODO DESDE</label>
                    <div class="input-group input-group-sm">
                        <select class="form-select border-0 bg-light" name="mes_inicio" onchange="this.form.submit()">
                            <option value="">Mes</option>
                            <?php foreach ($meses as $n => $m): ?>
                                <option value="<?= $n ?>" <?= ($mes_inicio == $n) ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select border-0 bg-light" name="ano_inicio" onchange="this.form.submit()">
                            <option value="">Año</option>
                            <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                <option value="<?= $i ?>" <?= ($ano_inicio == $i) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3">
                    <label class="form-label small fw-bold text-muted">PERIODO HASTA (OPCIONAL)</label>
                    <div class="input-group input-group-sm">
                        <select class="form-select border-0 bg-light" name="mes_termino" onchange="this.form.submit()">
                            <option value="">Mes</option>
                            <?php foreach ($meses as $n => $m): ?>
                                <option value="<?= $n ?>" <?= ($mes_termino == $n) ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select border-0 bg-light" name="ano_termino" onchange="this.form.submit()">
                            <option value="">Año</option>
                            <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                <option value="<?= $i ?>" <?= ($ano_termino == $i) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small fw-bold text-muted">MOSTRAR</label>
                    <select name="limit" class="form-select form-select-sm border-0 bg-light" onchange="this.form.submit()">
                        <?php foreach ($limit_options as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($records_per_page == $opt) ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1">
                    <a href="reportes_selector.php" class="btn btn-light btn-sm w-100 border-0 bg-light" title="Limpiar"><i class="fas fa-sync-alt"></i></a>
                </div>
            </form>

            <?php if ($total_records > 0): ?>
                <div class="mt-3 pt-2 border-top d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="org_descarga" id="org_mes" value="mes" checked>
                        <label class="form-check-label small" for="org_mes">Organizar por <strong>Mes</strong></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="org_descarga" id="org_emp" value="empleador">
                        <label class="form-check-label small" for="org_emp">Organizar por <strong>Empleador</strong></label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive bg-white rounded-3 shadow-sm mb-4">
        <table class="table align-middle mb-0">
            <thead class="bg-light small fw-bold text-muted text-uppercase">
                <tr>
                    <th class="ps-4" style="width: 40px;"><input class="form-check-input" type="checkbox" id="select-all"></th>
                    <th>Empleador / Empresa</th>
                    <th>RUT</th>
                    <th>Período</th>
                    <th class="text-end pe-4">Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportes)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No se encontraron reportes con los criterios seleccionados.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($reportes as $r): ?>
                    <tr class="report-row">
                        <td class="ps-4">
                            <input class="form-check-input check-report" type="checkbox"
                                value="<?= $r['empleador_id'] ?>"
                                data-mes="<?= $r['mes'] ?>"
                                data-ano="<?= $r['ano'] ?>">
                        </td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($r['empleador_nombre']) ?></td>
                        <td><code><?= htmlspecialchars($r['empleador_rut']) ?></code></td>
                        <td><span class="badge bg-light text-primary border px-2 py-1"><?= $meses[$r['mes']] . ' ' . $r['ano'] ?></span></td>
                        <td class="text-end pe-4">
                            <a href="ver_pdf.php?id=<?= $r['empleador_id'] ?>&mes=<?= $r['mes'] ?>&ano=<?= $r['ano'] ?>" target="_blank" class="btn btn-sm btn-outline-primary border-0 rounded-circle">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($records_per_page !== 'todos' && $total_pages > 1): ?>
        <nav class="pb-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link shadow-sm" href="<?= getPaginationUrl($current_page - 1) ?>"><i class="fas fa-chevron-left"></i></a>
                </li>
                <?php
                $start = max(1, $current_page - 2);
                $end = min($total_pages, $current_page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= ($current_page == $i) ? 'active' : '' ?>">
                        <a class="page-link shadow-sm" href="<?= getPaginationUrl($i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link shadow-sm" href="<?= getPaginationUrl($current_page + 1) ?>"><i class="fas fa-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Selección masiva
        $('#select-all').on('click', function() {
            $('.check-report').prop('checked', $(this).prop('checked'));
            updateZipBtn();
        });

        $('.check-report').on('change', function() {
            updateZipBtn();
        });

        function updateZipBtn() {
            const count = $('.check-report:checked').length;
            $('#btn-descargar-zip').prop('disabled', count === 0).html(`<i class="fas fa-file-archive me-2"></i> DESCARGAR ZIP (${count})`);
        }

        // Lógica ZIP
        $('#btn-descargar-zip').on('click', function() {
            const reportes = [];
            $('.check-report:checked').each(function() {
                reportes.push({
                    empleador_id: $(this).val(),
                    mes: $(this).data('mes'),
                    ano: $(this).data('ano')
                });
            });

            const org = $('input[name="org_descarga"]:checked').val();

            Swal.fire({
                title: 'Generando archivo ZIP...',
                text: 'Estamos preparando tus documentos.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('<?php echo BASE_URL; ?>/ajax/descargar_zip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        reportes: reportes,
                        organizacion: org
                    })
                })
                .then(res => res.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Reportes_${org}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    Swal.close();
                })
                .catch(err => {
                    Swal.fire('Error', 'No se pudo generar el archivo ZIP.', 'error');
                });
        });
    });
</script>