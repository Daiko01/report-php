<?php
// maestros/gestionar_tramos_cargas.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// VERIFICACIÓN DE ROL: Solo ADMIN
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die("Acceso Denegado. Se requieren permisos de Administrador.");
}

$anio_sel = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// PROCESAR GUARDADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tramos') {
    verify_csrf_token(); // Asumiendo que existe helper global

    $p_mes = (int)$_POST['mes'];
    $p_anio = (int)$_POST['anio'];

    // Datos A, B, C, D
    // Formato post: tramo[A][monto], tramo[A][tope], ...
    $tramos_data = $_POST['tramo'];

    try {
        $pdo->beginTransaction();

        // 1. Borrar lo que exista para ese mes/año (para reescribir limpio)
        // OJO: La tabla `cargas_tramos_historicos` tiene `fecha_inicio`. 
        // Asumiremos que fecha_inicio es el primer día del mes.
        $fecha_inicio = "$p_anio-$p_mes-01";

        $stmtDel = $pdo->prepare("DELETE FROM cargas_tramos_historicos WHERE fecha_inicio = ?");
        $stmtDel->execute([$fecha_inicio]);

        // 2. Insertar los 4 tramos
        $stmtIns = $pdo->prepare("INSERT INTO cargas_tramos_historicos (tramo, monto_por_carga, renta_maxima, fecha_inicio) VALUES (?, ?, ?, ?)");

        foreach ($tramos_data as $letra => $v) {
            $monto = (int)$v['monto'];
            $tope = (int)$v['tope'];
            $stmtIns->execute([$letra, $monto, $tope, $fecha_inicio]);
        }

        $pdo->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Tramos para $p_mes/$p_anio actualizados correctamente."];
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Error al guardar: " . $e->getMessage()];
    }

    header("Location: gestionar_tramos_cargas.php?anio=$p_anio");
    exit;
}

// OBTENER CLIENTE HISTÓRICO
// Group by default groups by first column. We want to organize by date.
$stmtHist = $pdo->prepare("SELECT fecha_inicio, tramo, monto_por_carga, renta_maxima FROM cargas_tramos_historicos WHERE YEAR(fecha_inicio) = ? ORDER BY fecha_inicio ASC, tramo ASC");
$stmtHist->execute([$anio_sel]);
$history_raw = $stmtHist->fetchAll(PDO::FETCH_GROUP);
// Estructura: '2024-01-01' => [ [tramo=>A, ...], [tramo=>B...], ... ]

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Histórico Tramos Asignación Familiar</h1>
        <form class="d-flex align-items-center shadow-sm bg-white rounded-pill px-3 py-1" method="GET">
            <label class="me-2 text-gray-600 fw-bold small mb-0"><i class="fas fa-calendar-alt me-1"></i> Año:</label>
            <select name="anio" class="form-select w-auto border-0 bg-transparent fw-bold text-primary py-1" onchange="this.form.submit()" style="box-shadow: none;">
                <?php
                $y = date('Y');
                for ($i = $y - 2; $i <= $y + 1; $i++) {
                    $sel = ($i == $anio_sel) ? 'selected' : '';
                    echo "<option value='$i' $sel>$i</option>";
                }
                ?>
            </select>
        </form>
    </div>

    <!-- TABLA DE MESES -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header py-3 bg-white d-flex align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table me-2"></i>Valores Año <?= $anio_sel ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th>Mes</th>
                            <th>Tramo A</th>
                            <th>Tramo B</th>
                            <th>Tramo C</th>
                            <th>Tramo D</th>
                            <th style="width: 100px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $meses = [
                            1 => 'Enero',
                            2 => 'Febrero',
                            3 => 'Marzo',
                            4 => 'Abril',
                            5 => 'Mayo',
                            6 => 'Junio',
                            7 => 'Julio',
                            8 => 'Agosto',
                            9 => 'Septiembre',
                            10 => 'Octubre',
                            11 => 'Noviembre',
                            12 => 'Diciembre'
                        ];

                        foreach ($meses as $num_mes => $nom_mes):
                            $fecha_key = "$anio_sel-" . str_pad($num_mes, 2, '0', STR_PAD_LEFT) . "-01";
                            $datos = isset($history_raw[$fecha_key]) ? $history_raw[$fecha_key] : [];

                            // Reordenar por tramo clave para fácil visualización
                            $tramos = [];
                            foreach ($datos as $d) {
                                $tramos[$d['tramo']] = $d;
                            }

                            // Valores por defecto
                            $currA = $tramos['A'] ?? ['monto_por_carga' => 0, 'renta_maxima' => 0];
                            $currB = $tramos['B'] ?? ['monto_por_carga' => 0, 'renta_maxima' => 0];
                            $currC = $tramos['C'] ?? ['monto_por_carga' => 0, 'renta_maxima' => 0];
                            $currD = $tramos['D'] ?? ['monto_por_carga' => 0, 'renta_maxima' => 0];
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="icon-circle bg-light text-primary me-3 text-xs" style="height: 2.5rem; width: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <span class="fw-bold small"><?= substr($nom_mes, 0, 3) ?></span>
                                        </div>
                                        <span class="fw-bold text-gray-800"><?= $nom_mes ?></span>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <span class="badge bg-success text-white rounded-pill px-3 mb-1">
                                            $<?= number_format($currA['monto_por_carga'], 0, ',', '.') ?>
                                        </span>
                                        <div class="small text-muted" style="font-size: 0.7rem;">Top: $<?= number_format($currA['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <span class="badge bg-info text-dark rounded-pill px-3 mb-1">
                                            $<?= number_format($currB['monto_por_carga'], 0, ',', '.') ?>
                                        </span>
                                        <div class="small text-muted" style="font-size: 0.7rem;">Top: $<?= number_format($currB['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 mb-1">
                                            $<?= number_format($currC['monto_por_carga'], 0, ',', '.') ?>
                                        </span>
                                        <div class="small text-muted" style="font-size: 0.7rem;">Top: $<?= number_format($currC['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <span class="badge bg-danger text-white rounded-pill px-3 mb-1">
                                            $<?= number_format($currD['monto_por_carga'], 0, ',', '.') ?>
                                        </span>
                                        <div class="small text-muted" style="font-size: 0.7rem;">Top: $<?= number_format($currD['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <button class="btn btn-outline-primary btn-sm btn-editar-tramo rounded-circle"
                                        style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"
                                        title="Editar Tramos <?= $nom_mes ?>"
                                        data-mes="<?= $num_mes ?>"
                                        data-mes-nom="<?= $nom_mes ?>"
                                        data-a-monto="<?= $currA['monto_por_carga'] ?>" data-a-tope="<?= $currA['renta_maxima'] ?>"
                                        data-b-monto="<?= $currB['monto_por_carga'] ?>" data-b-tope="<?= $currB['renta_maxima'] ?>"
                                        data-c-monto="<?= $currC['monto_por_carga'] ?>" data-c-tope="<?= $currC['renta_maxima'] ?>"
                                        data-d-monto="<?= $currD['monto_por_carga'] ?>" data-d-tope="<?= $currD['renta_maxima'] ?>">
                                        <i class="fas fa-pencil-alt fa-sm"></i>
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

<!-- Modal Edición -->
<div class="modal fade" id="modalEditarTramos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Tramos - <span id="lblMes" class="fw-bold"></span> <?= $anio_sel ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="save_tramos">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="anio" value="<?= $anio_sel ?>">
                    <input type="hidden" name="mes" id="inputMes">

                    <div class="row g-3">
                        <!-- Tramo A -->
                        <div class="col-md-6">
                            <div class="card shadow-sm border-left-success h-100">
                                <div class="card-body">
                                    <h6 class="font-weight-bold text-success mb-3">Tramo A</h6>
                                    <div class="mb-2">
                                        <label class="small text-muted">Monto Carga ($)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[A][monto]" id="valAMonto" required>
                                    </div>
                                    <div>
                                        <label class="small text-muted">Renta Máxima (Tope $)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[A][tope]" id="valATope" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tramo B -->
                        <div class="col-md-6">
                            <div class="card shadow-sm border-left-info h-100">
                                <div class="card-body">
                                    <h6 class="font-weight-bold text-info mb-3">Tramo B</h6>
                                    <div class="mb-2">
                                        <label class="small text-muted">Monto Carga ($)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[B][monto]" id="valBMonto" required>
                                    </div>
                                    <div>
                                        <label class="small text-muted">Renta Máxima (Tope $)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[B][tope]" id="valBTope" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tramo C -->
                        <div class="col-md-6">
                            <div class="card shadow-sm border-left-warning h-100">
                                <div class="card-body">
                                    <h6 class="font-weight-bold text-warning mb-3">Tramo C</h6>
                                    <div class="mb-2">
                                        <label class="small text-muted">Monto Carga ($)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[C][monto]" id="valCMonto" required>
                                    </div>
                                    <div>
                                        <label class="small text-muted">Renta Máxima (Tope $)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[C][tope]" id="valCTope" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tramo D -->
                        <div class="col-md-6">
                            <div class="card shadow-sm border-left-danger h-100">
                                <div class="card-body">
                                    <h6 class="font-weight-bold text-danger mb-3">Tramo D</h6>
                                    <div class="mb-2">
                                        <label class="small text-muted">Monto Carga ($)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[D][monto]" id="valDMonto" required>
                                    </div>
                                    <div>
                                        <label class="small text-muted">Renta Máxima (Tope $)</label>
                                        <input type="number" class="form-control form-control-sm" name="tramo[D][tope]" id="valDTope" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .border-left-success {
        border-left: 4px solid #1cc88a !important;
    }

    .border-left-info {
        border-left: 4px solid #36b9cc !important;
    }

    .border-left-warning {
        border-left: 4px solid #f6c23e !important;
    }

    .border-left-danger {
        border-left: 4px solid #e74a3b !important;
    }
</style>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Modal
        const modalEl = document.getElementById('modalEditarTramos');
        const modal = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.btn-editar-tramo').forEach(btn => {
            btn.addEventListener('click', function() {
                const mes = this.dataset.mes;
                const mesNom = this.dataset.mesNom;

                document.getElementById('inputMes').value = mes;
                document.getElementById('lblMes').textContent = mesNom;

                document.getElementById('valAMonto').value = this.dataset.aMonto;
                document.getElementById('valATope').value = this.dataset.aTope;

                document.getElementById('valBMonto').value = this.dataset.bMonto;
                document.getElementById('valBTope').value = this.dataset.bTope;

                document.getElementById('valCMonto').value = this.dataset.cMonto;
                document.getElementById('valCTope').value = this.dataset.cTope;

                document.getElementById('valDMonto').value = this.dataset.dMonto;
                document.getElementById('valDTope').value = this.dataset.dTope;

                modal.show();
            });
        });
    });
</script>