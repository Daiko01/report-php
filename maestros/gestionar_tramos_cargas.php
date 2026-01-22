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

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Histórico Tramos Asignación Familiar</h1>
        <form class="d-flex gap-2" method="GET">
            <select name="anio" class="form-select w-auto" onchange="this.form.submit()">
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
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Valores Año <?= $anio_sel ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr class="text-center">
                            <th>Mes</th>
                            <th>Tramo A (Monto / Tope)</th>
                            <th>Tramo B (Monto / Tope)</th>
                            <th>Tramo C (Monto / Tope)</th>
                            <th>Tramo D (Monto / Tope)</th>
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
                                <td class="align-middle fw-bold"><?= $nom_mes ?></td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <div class="small fw-bold text-success">$<?= number_format($currA['monto_por_carga'], 0, ',', '.') ?></div>
                                        <div class="text-muted" style="font-size:0.75rem">Top: $<?= number_format($currA['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <div class="small fw-bold text-success">$<?= number_format($currB['monto_por_carga'], 0, ',', '.') ?></div>
                                        <div class="text-muted" style="font-size:0.75rem">Top: $<?= number_format($currB['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <div class="small fw-bold text-success">$<?= number_format($currC['monto_por_carga'], 0, ',', '.') ?></div>
                                        <div class="text-muted" style="font-size:0.75rem">Top: $<?= number_format($currC['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($datos): ?>
                                        <div class="small fw-bold text-success">$<?= number_format($currD['monto_por_carga'], 0, ',', '.') ?></div>
                                        <div class="text-muted" style="font-size:0.75rem">Top: $<?= number_format($currD['renta_maxima'], 0, ',', '.') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <button class="btn btn-sm btn-info btn-editar-tramo"
                                        data-mes="<?= $num_mes ?>"
                                        data-mes-nom="<?= $nom_mes ?>"
                                        data-a-monto="<?= $currA['monto_por_carga'] ?>" data-a-tope="<?= $currA['renta_maxima'] ?>"
                                        data-b-monto="<?= $currB['monto_por_carga'] ?>" data-b-tope="<?= $currB['renta_maxima'] ?>"
                                        data-c-monto="<?= $currC['monto_por_carga'] ?>" data-c-tope="<?= $currC['renta_maxima'] ?>"
                                        data-d-monto="<?= $currD['monto_por_carga'] ?>" data-d-tope="<?= $currD['renta_maxima'] ?>">
                                        <i class="fas fa-edit"></i>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Editar Tramos - <span id="lblMes"></span> <?= $anio_sel ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_tramos">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="anio" value="<?= $anio_sel ?>">
                    <input type="hidden" name="mes" id="inputMes">

                    <!-- Tramo A -->
                    <div class="mb-3 border-bottom pb-2">
                        <label class="fw-bold mb-1">Tramo A</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small>Monto Carga</small>
                                <input type="number" class="form-control" name="tramo[A][monto]" id="valAMonto" required>
                            </div>
                            <div class="col-6">
                                <small>Renta Máxima (Tope)</small>
                                <input type="number" class="form-control" name="tramo[A][tope]" id="valATope" required>
                            </div>
                        </div>
                    </div>

                    <!-- Tramo B -->
                    <div class="mb-3 border-bottom pb-2">
                        <label class="fw-bold mb-1">Tramo B</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small>Monto Carga</small>
                                <input type="number" class="form-control" name="tramo[B][monto]" id="valBMonto" required>
                            </div>
                            <div class="col-6">
                                <small>Renta Máxima (Tope)</small>
                                <input type="number" class="form-control" name="tramo[B][tope]" id="valBTope" required>
                            </div>
                        </div>
                    </div>

                    <!-- Tramo C -->
                    <div class="mb-3 border-bottom pb-2">
                        <label class="fw-bold mb-1">Tramo C</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small>Monto Carga</small>
                                <input type="number" class="form-control" name="tramo[C][monto]" id="valCMonto" required>
                            </div>
                            <div class="col-6">
                                <small>Renta Máxima (Tope)</small>
                                <input type="number" class="form-control" name="tramo[C][tope]" id="valCTope" required>
                            </div>
                        </div>
                    </div>

                    <!-- Tramo D -->
                    <div class="mb-3">
                        <label class="fw-bold mb-1">Tramo D</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small>Monto Carga</small>
                                <input type="number" class="form-control" name="tramo[D][monto]" id="valDMonto" required>
                            </div>
                            <div class="col-6">
                                <small>Renta Máxima (Tope)</small>
                                <input type="number" class="form-control" name="tramo[D][tope]" id="valDTope" required>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('modalEditarTramos'));

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