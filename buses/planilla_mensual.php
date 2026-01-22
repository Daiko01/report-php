<?php
// buses/planilla_mensual.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// --- CONFIG ---
$months = [
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

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$bus_id = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : 0;

// --- ACCIONES POST (Guardar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_rows') {
    $ids = $_POST['id'] ?? [];
    $stmt = $pdo->prepare("UPDATE produccion_buses SET 
        ingreso = ?, gasto_petroleo = ?, gasto_boletos = ?, 
        gasto_administracion = ?, gasto_varios = ?, 
        pago_conductor = ?, aporte_previsional = ?
        WHERE id = ?");

    try {
        $pdo->beginTransaction();
        foreach ($ids as $key => $id) {
            $stmt->execute([
                $_POST['ingreso'][$key] ?? 0,
                $_POST['petroleo'][$key] ?? 0,
                $_POST['boletos'][$key] ?? 0,
                $_POST['administracion'][$key] ?? 0,
                $_POST['varios'][$key] ?? 0,
                $_POST['pago_conductor'][$key] ?? 0,
                $_POST['aporte_previsional'][$key] ?? 0,
                $id
            ]);
        }
        $pdo->commit();
        $message = "Planilla actualizada correctamente.";
        $msg_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error al guardar: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// --- CONSULTA DE DATOS ---

// 1. Obtener todas las máquinas con producción (Sin Paginación PHP para usar DataTables)
$sql_buses_activos = "SELECT b.id, b.numero_maquina, b.patente, COUNT(p.id) as total_guias
                      FROM buses b
                      INNER JOIN produccion_buses p ON b.id = p.bus_id
                      INNER JOIN unidades u ON b.unidad_id = u.id
                      WHERE MONTH(p.fecha) = ? AND YEAR(p.fecha) = ?
                      AND u.empresa_asociada_id = ?
                      GROUP BY b.id
                      ORDER BY b.numero_maquina ASC";

$stmt_activos = $pdo->prepare($sql_buses_activos);
$stmt_activos->execute([$month, $year, ID_EMPRESA_SISTEMA]);
$buses_con_datos = $stmt_activos->fetchAll();

// 2. Detalle de bus seleccionado
$produccion = [];
if ($bus_id) {
    $stmt = $pdo->prepare("SELECT p.*, t.nombre as nombre_chofer 
                           FROM produccion_buses p 
                           LEFT JOIN trabajadores t ON p.trabajador_id = t.id 
                           WHERE p.bus_id = ? 
                           AND MONTH(p.fecha) = ? AND YEAR(p.fecha) = ? 
                           ORDER BY p.fecha ASC, p.nro_guia ASC");
    $stmt->execute([$bus_id, $month, $year]);
    $produccion = $stmt->fetchAll();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Planilla Mensual</h1>
        <?php if ($bus_id): ?>
            <div>
                <a href="planilla_mensual.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary shadow-sm me-2">
                    <i class="fas fa-chevron-left"></i> Volver a la Lista
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="small fw-bold">Mes</label>
                    <select name="month" class="form-select select2 auto-submit">
                        <?php foreach ($months as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $k == $month ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Año</label>
                    <input type="number" name="year" class="form-control auto-submit" value="<?= $year ?>">
                </div>
                <?php if ($bus_id): ?>
                    <input type="hidden" name="bus_id" value="<?= $bus_id ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!$bus_id): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Máquinas con Datos</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 datatable-es">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Máquina</th>
                                <th>Patente</th>
                                <th class="text-center">Registros (Guías)</th>
                                <th class="text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buses_con_datos as $b): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">Máquina <?= $b['numero_maquina'] ?></td>
                                    <td><span class="badge border text-dark"><?= $b['patente'] ?></span></td>
                                    <td class="text-center"><?= $b['total_guias'] ?> guías</td>
                                    <td class="text-end pe-4">
                                        <a href="?month=<?= $month ?>&year=<?= $year ?>&bus_id=<?= $b['id'] ?>" class="btn btn-sm btn-primary">Ver Detalle</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="update_rows">
            <div class="card shadow mb-4 border-left-success">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Detalle de Máquina</h6>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Guardar Planilla</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="bg-light small">
                                <tr class="text-center">
                                    <th>Fecha</th>
                                    <th>Guía</th>
                                    <th>Ingreso</th>
                                    <th>Pago 22%</th>
                                    <th>Petróleo</th>
                                    <th>Varios</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produccion as $p): ?>
                                    <tr>
                                        <input type="hidden" name="id[]" value="<?= $p['id'] ?>">
                                        <td class="text-center"><?= date('d/m', strtotime($p['fecha'])) ?></td>
                                        <td class="text-center"><?= $p['nro_guia'] ?></td>
                                        <td><input type="number" class="form-control form-control-sm input-ingreso" name="ingreso[]" value="<?= $p['ingreso'] ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm input-pago bg-light" name="pago_conductor[]" value="<?= $p['pago_conductor'] ?>" readonly></td>
                                        <td><input type="number" class="form-control form-control-sm" name="petroleo[]" value="<?= $p['gasto_petroleo'] ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm" name="varios[]" value="<?= $p['gasto_varios'] ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5'
        });

        // AUTO-SUBMIT: Detecta cambios en Mes y Año
        $('.auto-submit').on('change', function() {
            $('#filterForm').submit();
        });

        // Cálculos 22%
        $('.input-ingreso').on('input', function() {
            let row = $(this).closest('tr');
            let ing = parseFloat($(this).val()) || 0;
            row.find('.input-pago').val(Math.round(ing * 0.22));
        });
    });
</script>