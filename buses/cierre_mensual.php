<?php
// buses/cierre_mensual.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/calculos_cierre.php';

// --- CONFIGURACIÓN DE ENTORNO ---
$mes_sel = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : date('n');
$anio_sel = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : date('Y');
$bus_id = isset($_REQUEST['bus_id']) ? (int)$_REQUEST['bus_id'] : 0;

$months_list = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

$msg = '';
$msg_type = '';

// =================================================================================
// 1. PROCESO DE GUARDADO (POST) - Mantenido EXACTO de tu código
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bus_id = (int)$_POST['bus_id'];
    $mes = (int)$_POST['mes'];
    $anio = (int)$_POST['anio'];

    // Inputs Manuales
    $subsidio_operacional = (int)($_POST['subsidio_operacional'] ?? 0);
    $devolucion_minutos = (int)($_POST['devolucion_minutos'] ?? 0);
    $otros_ingresos_1 = (int)($_POST['otros_ingresos_1'] ?? 0);
    $otros_ingresos_2 = (int)($_POST['otros_ingresos_2'] ?? 0);
    $otros_ingresos_3 = (int)($_POST['otros_ingresos_3'] ?? 0);

    $anticipo = (int)($_POST['anticipo'] ?? 0);
    $asignacion_familiar = (int)($_POST['asignacion_familiar'] ?? 0);
    $pago_minutos = (int)($_POST['pago_minutos'] ?? 0);
    $saldo_anterior = (int)($_POST['saldo_anterior'] ?? 0);
    $ayuda_mutua = (int)($_POST['ayuda_mutua'] ?? 0);
    $servicio_grua = (int)($_POST['servicio_grua'] ?? 0);
    $poliza_seguro = (int)($_POST['poliza_seguro'] ?? 0);

    $valor_vueltas_directo = (int)($_POST['valor_vueltas_directo'] ?? 0);
    $valor_vueltas_local = (int)($_POST['valor_vueltas_local'] ?? 0);
    $cant_vueltas_directo = (int)($_POST['cant_vueltas_directo'] ?? 0);
    $cant_vueltas_local = (int)($_POST['cant_vueltas_local'] ?? 0);

    // Campos calculados
    $monto_leyes_sociales = (int)($_POST['monto_leyes_sociales'] ?? 0);
    $monto_administracion_aplicado = (int)($_POST['monto_administracion_aplicado'] ?? 0);

    try {
        $stmt = $pdo->prepare("INSERT INTO cierres_maquinas 
            (bus_id, mes, anio, subsidio_operacional, devolucion_minutos, otros_ingresos_1, otros_ingresos_2, otros_ingresos_3,
             anticipo, asignacion_familiar, pago_minutos, saldo_anterior, ayuda_mutua, servicio_grua, poliza_seguro,
             valor_vueltas_directo, valor_vueltas_local, cant_vueltas_directo, cant_vueltas_local,
             monto_leyes_sociales, monto_administracion_aplicado,
             derechos_loza, seguro_cartolas, gps, boleta_garantia, boleta_garantia_dos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
             subsidio_operacional = VALUES(subsidio_operacional),
             devolucion_minutos = VALUES(devolucion_minutos),
             otros_ingresos_1 = VALUES(otros_ingresos_1),
             otros_ingresos_2 = VALUES(otros_ingresos_2),
             otros_ingresos_3 = VALUES(otros_ingresos_3),
             anticipo = VALUES(anticipo),
             asignacion_familiar = VALUES(asignacion_familiar),
             pago_minutos = VALUES(pago_minutos),
             saldo_anterior = VALUES(saldo_anterior),
             ayuda_mutua = VALUES(ayuda_mutua),
             servicio_grua = VALUES(servicio_grua),
             poliza_seguro = VALUES(poliza_seguro),
             valor_vueltas_directo = VALUES(valor_vueltas_directo),
             valor_vueltas_local = VALUES(valor_vueltas_local),
             cant_vueltas_directo = VALUES(cant_vueltas_directo),
             cant_vueltas_local = VALUES(cant_vueltas_local),
             monto_leyes_sociales = VALUES(monto_leyes_sociales),
             monto_administracion_aplicado = VALUES(monto_administracion_aplicado),
             derechos_loza = VALUES(derechos_loza),
             seguro_cartolas = VALUES(seguro_cartolas),
             gps = VALUES(gps),
             boleta_garantia = VALUES(boleta_garantia),
             boleta_garantia_dos = VALUES(boleta_garantia_dos)
        ");

        $derechos_loza = (int)($_POST['derechos_loza'] ?? 0);
        $seguro_cartolas = (int)($_POST['seguro_cartolas'] ?? 0);
        $gps = (int)($_POST['gps'] ?? 0);
        $boleta_garantia = (int)($_POST['boleta_garantia'] ?? 0);
        $boleta_garantia_dos = (int)($_POST['boleta_garantia_dos'] ?? 0);

        $stmt->execute([
            $bus_id,
            $mes,
            $anio,
            $subsidio_operacional,
            $devolucion_minutos,
            $otros_ingresos_1,
            $otros_ingresos_2,
            $otros_ingresos_3,
            $anticipo,
            $asignacion_familiar,
            $pago_minutos,
            $saldo_anterior,
            $ayuda_mutua,
            $servicio_grua,
            $poliza_seguro,
            $valor_vueltas_directo,
            $valor_vueltas_local,
            $cant_vueltas_directo,
            $cant_vueltas_local,
            $monto_leyes_sociales,
            $monto_administracion_aplicado,
            $derechos_loza,
            $seguro_cartolas,
            $gps,
            $boleta_garantia,
            $boleta_garantia_dos
        ]);
        $msg = "Cierre guardado correctamente.";
        $msg_type = "success";

        // Mantener variables para la vista
        $mes_sel = $mes;
        $anio_sel = $anio;
    } catch (Exception $e) {
        $msg = "Error al guardar: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// =================================================================================
// 2. LÓGICA DE VISTA (TABLERO vs EDICIÓN)
// =================================================================================

$totals = [];
$cierre = [];
$admin_global = 0;
$leyes_sociales_calculo = 0;
$maquinas_dashboard = [];

// A. SI ESTAMOS EDITANDO UNA MÁQUINA ESPECÍFICA (Tu lógica de cálculo original)
if ($bus_id > 0) {
    // Inicializar totales en 0
    $keys = ['ingreso', 'gasto_petroleo', 'gasto_boletos', 'gasto_admin', 'gasto_aseo', 'gasto_viatico', 'gasto_cta_extra', 'gasto_varios', 'pago_conductor', 'aporte_previsional'];
    foreach ($keys as $k) $totals[$k] = 0;

    // 1. Totales imported
    $stmtT = $pdo->prepare("
        SELECT 
            SUM(ingreso) as ingreso, SUM(gasto_petroleo) as gasto_petroleo, SUM(gasto_boletos) as gasto_boletos,
            SUM(gasto_administracion) as gasto_admin, SUM(gasto_aseo) as gasto_aseo, SUM(gasto_viatico) as gasto_viatico,
            SUM(gasto_cta_extra) as gasto_cta_extra, SUM(gasto_varios) as gasto_varios, SUM(pago_conductor) as pago_conductor,
            SUM(aporte_previsional) as aporte_previsional
        FROM produccion_buses 
        WHERE bus_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
    ");
    $stmtT->execute([$bus_id, $mes_sel, $anio_sel]);
    $resT = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($resT) {
        foreach ($resT as $k => $v) $totals[$k] = (int)$v;
    }

    // 2. Existing Closure Data
    $stmtC = $pdo->prepare("SELECT * FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ?");
    $stmtC->execute([$bus_id, $mes_sel, $anio_sel]);
    $cierre = $stmtC->fetch(PDO::FETCH_ASSOC) ?: [];

    // 3. Global Values & Defaults & Laws (Refactored)
    $calculos = calcular_cierre_bus($pdo, $bus_id, $mes_sel, $anio_sel);

    $defaults = $calculos['defaults'];
    $leyes_sociales_calculo = $calculos['monto_leyes_sociales'];
    $admin_global = $calculos['admin_global'];
} else {
    // B. SI ESTAMOS EN EL DASHBOARD (LISTA DE MÁQUINAS)
    // Consulta "Inteligente": Trae solo buses con producción en el mes seleccionado
    $sqlDashboard = "SELECT b.id, b.numero_maquina, b.patente, e.nombre as empleador,
                            COUNT(pb.id) as total_guias,
                            SUM(pb.ingreso) as total_ingreso,
                            cm.id as cierre_id
                     FROM buses b
                     JOIN produccion_buses pb ON b.id = pb.bus_id
                     JOIN empleadores e ON b.empleador_id = e.id
                     LEFT JOIN cierres_maquinas cm ON b.id = cm.bus_id AND cm.mes = ? AND cm.anio = ?
                     WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ?
                     GROUP BY b.id
                     ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";

    $stmtDash = $pdo->prepare($sqlDashboard);
    $stmtDash->execute([$mes_sel, $anio_sel, $mes_sel, $anio_sel]);
    $maquinas_dashboard = $stmtDash->fetchAll();
}

// Helper para inputs
function val($key, $default = 0)
{
    global $cierre;
    return isset($cierre[$key]) ? $cierre[$key] : $default;
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php if ($bus_id > 0): ?>
                <a href="cierre_mensual.php?mes=<?= $mes_sel ?>&anio=<?= $anio_sel ?>" class="btn btn-sm btn-secondary me-2"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                Cierre Mensual Máquina
            <?php else: ?>
                <i class="fas fa-file-invoice-dollar"></i> Panel de Cierres Mensuales
            <?php endif; ?>
        </h1>
        <?php if ($bus_id == 0): ?>
            <button onclick="procesarCierreMasivo()" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-magic fa-sm text-white-50"></i> Procesar Cierre Masivo
            </button>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($bus_id == 0): ?>

        <div class="card shadow mb-4">
            <div class="card-body bg-light">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3">
                        <label class="fw-bold">Mes</label>
                        <select name="mes" class="form-select shadow-sm" onchange="this.form.submit()">
                            <?php foreach ($months_list as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $k == $mes_sel ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="fw-bold">Año</label>
                        <input type="number" name="anio" class="form-control shadow-sm" value="<?= $anio_sel ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-7 text-end text-muted">
                        <small>Mostrando solo máquinas con actividad en: <strong><?= $months_list[$mes_sel] ?> <?= $anio_sel ?></strong></small>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-gradient-light">
                <h6 class="m-0 font-weight-bold text-primary">Estado de Cierre de Flota</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover align-middle mb-0" id="tablaStatusFlota" width="100%">
                        <thead class="bg-light text-uppercase text-xs font-weight-bold">
                            <tr>
                                <th class="ps-4">Máquina</th>
                                <th>Producción</th>
                                <th>Ingreso Total</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($maquinas_dashboard) > 0): ?>
                                <?php foreach ($maquinas_dashboard as $m):
                                    $cerrado = !empty($m['cierre_id']);
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-gray-800">Nº <?= $m['numero_maquina'] ?></div>
                                            <small class="text-muted"><?= $m['patente'] ?> &bull; <?= $m['empleador'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= $m['total_guias'] ?> guías</span>
                                        </td>
                                        <td class="text-success fw-bold">$ <?= number_format($m['total_ingreso'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php if ($cerrado): ?>
                                                <span class="badge bg-success shadow-sm"><i class="fas fa-check"></i> Cerrado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark shadow-sm"><i class="fas fa-clock"></i> Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="cierre_mensual.php?bus_id=<?= $m['id'] ?>&mes=<?= $mes_sel ?>&anio=<?= $anio_sel ?>"
                                                class="btn btn-sm shadow-sm <?= $cerrado ? 'btn-outline-secondary' : 'btn-success text-white' ?> rounded-pill px-3">
                                                <?= $cerrado ? '<i class="fas fa-edit me-1"></i> Editar' : '<i class="fas fa-check-circle me-1"></i> Cerrar' ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else:
        // Recuperar info visual del bus
        $stmtB = $pdo->prepare("SELECT numero_maquina, patente FROM buses WHERE id = ?");
        $stmtB->execute([$bus_id]);
        $busVis = $stmtB->fetch();
    ?>
        <form method="POST">
            <input type="hidden" name="bus_id" value="<?= $bus_id ?>">
            <input type="hidden" name="mes" value="<?= $mes_sel ?>">
            <input type="hidden" name="anio" value="<?= $anio_sel ?>">

            <div class="row">
                <div class="col-lg-4">
                    <div class="card shadow mb-4 border-left-info">
                        <div class="card-header py-3 bg-gradient-info">
                            <h6 class="m-0 font-weight-bold">Resumen: Bus Nº <?= $busVis['numero_maquina'] ?></h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td>Ingreso Bruto:</td>
                                    <td class="text-end fw-bold">$ <?= number_format($totals['ingreso'], 0, ',', '.') ?></td>
                                </tr>
                                <tr class="border-top">
                                    <td>(-) Petróleo:</td>
                                    <td class="text-end text-danger"><?= number_format($totals['gasto_petroleo'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>(-) Boletos:</td>
                                    <td class="text-end text-danger"><?= number_format($totals['gasto_boletos'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>(-) Administración:</td>
                                    <td class="text-end text-danger"><?= number_format($totals['gasto_admin'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>(-) Aseo:</td>
                                    <td class="text-end text-danger"><?= number_format($totals['gasto_aseo'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>(-) Viático:</td>
                                    <td class="text-end text-danger"><?= number_format($totals['gasto_viatico'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>(-) Varios/Extra:</td>
                                    <td class="text-end text-danger"><?= number_format($totals['gasto_varios'] + $totals['gasto_cta_extra'], 0, ',', '.') ?></td>
                                </tr>

                                <!-- NUEVOS CARGOS FIJOS -->
                                <tr>
                                    <td>(-) Der. Loza:</td>
                                    <td class="text-end text-danger"><?= number_format(val('derechos_loza', $defaults['derechos_loza']), 0, ',', '.') ?></td>
                                </tr>
                                <?php if ($mes_sel == 9): ?>
                                    <tr>
                                        <td>(-) Seg/Cart:</td>
                                        <td class="text-end text-danger"><?= number_format(val('seguro_cartolas', $defaults['seguro_cartolas']), 0, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>(-) GPS:</td>
                                    <td class="text-end text-danger"><?= number_format(val('gps', $defaults['gps']), 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>(-) Bol. Gtía:</td>
                                    <td class="text-end text-danger"><?= number_format(val('boleta_garantia', $defaults['boleta_garantia']) + val('boleta_garantia_dos', $defaults['boleta_garantia_dos']), 0, ',', '.') ?></td>
                                </tr>

                                <tr class="border-top bg-light">
                                    <td><strong>Utilidad Op:</strong></td>
                                    <?php
                                    // Calcular nuevos cargos para la utilidad
                                    $nuevo_cargos_fijos = val('derechos_loza', $defaults['derechos_loza']) +
                                        (($mes_sel == 9) ? val('seguro_cartolas', $defaults['seguro_cartolas']) : 0) +
                                        val('gps', $defaults['gps']) +
                                        val('boleta_garantia', $defaults['boleta_garantia']) +
                                        val('boleta_garantia_dos', $defaults['boleta_garantia_dos']);

                                    $gastos_op = $totals['gasto_petroleo'] + $totals['gasto_boletos'] + $totals['gasto_admin'] +
                                        $totals['gasto_aseo'] + $totals['gasto_viatico'] + $totals['gasto_varios'] + $totals['gasto_cta_extra'] +
                                        $nuevo_cargos_fijos;

                                    $utilidad_op = $totals['ingreso'] - $gastos_op;
                                    ?>
                                    <td class="text-end fw-bold">$ <?= number_format($utilidad_op, 0, ',', '.') ?></td>
                                </tr>
                            </table>

                            <hr class="my-3">

                            <div class="alert alert-warning mb-0">
                                <h6 class="alert-heading font-weight-bold text-xs text-uppercase">Admin. Mensual</h6>
                                <div class="form-group mb-2">
                                    <label class="small">Valor Configurado:</label>
                                    <input type="text" class="form-control form-control-sm" value="$ <?= number_format($admin_global, 0, ',', '.') ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label class="small fw-bold">Monto a Aplicar:</label>
                                    <input type="number" name="monto_administracion_aplicado" class="form-control form-control-sm border-warning"
                                        value="<?= val('monto_administracion_aplicado', $admin_global) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 border-left-primary">
                            <h6 class="m-0 font-weight-bold text-primary">Datos Financieros del Cierre</h6>
                        </div>
                        <div class="card-body">
                            <h6 class="text-success border-bottom pb-2 mb-3"><i class="fas fa-plus-circle"></i> Ingresos Adicionales</h6>
                            <div class="row mb-3">
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Subsidio Operacional</label>
                                    <input type="number" name="subsidio_operacional" class="form-control" value="<?= val('subsidio_operacional') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Devolución Minutos</label>
                                    <input type="number" name="devolucion_minutos" class="form-control" value="<?= val('devolucion_minutos') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Otros Ingresos 1</label>
                                    <input type="number" name="otros_ingresos_1" class="form-control" value="<?= val('otros_ingresos_1') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Otros Ingresos 2</label>
                                    <input type="number" name="otros_ingresos_2" class="form-control" value="<?= val('otros_ingresos_2') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Otros Ingresos 3</label>
                                    <input type="number" name="otros_ingresos_3" class="form-control" value="<?= val('otros_ingresos_3') ?>">
                                </div>
                            </div>

                            <h6 class="text-danger border-bottom pb-2 mt-4 mb-3"><i class="fas fa-minus-circle"></i> Descuentos / Cargos</h6>
                            <div class="row mb-3">
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Anticipo</label>
                                    <input type="number" name="anticipo" class="form-control" value="<?= val('anticipo') ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Asig. Familiar</label>
                                    <input type="number" name="asignacion_familiar" class="form-control" value="<?= val('asignacion_familiar') ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Pago Minutos</label>
                                    <input type="number" name="pago_minutos" class="form-control" value="<?= val('pago_minutos') ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Saldo Anterior</label>
                                    <input type="number" name="saldo_anterior" class="form-control" value="<?= val('saldo_anterior') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Ayuda Mutua</label>
                                    <input type="number" name="ayuda_mutua" class="form-control" value="<?= val('ayuda_mutua') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Servicio Grúa</label>
                                    <input type="number" name="servicio_grua" class="form-control" value="<?= val('servicio_grua') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Póliza Seguro</label>
                                    <input type="number" name="poliza_seguro" class="form-control" value="<?= val('poliza_seguro') ?>">
                                </div>
                            </div>

                            <h6 class="text-secondary border-bottom pb-2 mt-4 mb-3"><i class="fas fa-file-contract"></i> Cargos Fijos Mensuales (Config)</h6>
                            <div class="row mb-3 bg-light p-3 rounded">
                                <div class="col-md-4 mb-3">
                                    <label class="small fw-bold">Derechos de Loza</label>
                                    <input type="number" name="derechos_loza" class="form-control" value="<?= val('derechos_loza', $defaults['derechos_loza']) ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="small fw-bold">Seguro y Cartolas</label>
                                    <input type="number" name="seguro_cartolas" class="form-control"
                                        value="<?= ($mes_sel == 9) ? val('seguro_cartolas', $defaults['seguro_cartolas']) : 0 ?>"
                                        <?= ($mes_sel != 9) ? 'readonly style="background-color: #e9ecef;"' : '' ?>>
                                    <?php if ($mes_sel != 9): ?>
                                        <small class="text-xs text-muted d-block mt-1">Este campo solo es editable en Septiembre.</small>
                                    <?php else: ?>
                                        <small class="text-xs text-muted d-block mt-1">Sugerido para Septiembre.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="small fw-bold">GPS</label>
                                    <input type="number" name="gps" class="form-control" value="<?= val('gps', $defaults['gps']) ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small fw-bold">Boleta Grantía</label>
                                    <input type="number" name="boleta_garantia" class="form-control" value="<?= val('boleta_garantia', $defaults['boleta_garantia']) ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small fw-bold">Boleta Grantía 2</label>
                                    <input type="number" name="boleta_garantia_dos" class="form-control" value="<?= val('boleta_garantia_dos', $defaults['boleta_garantia_dos']) ?>">
                                </div>
                            </div>

                            <h6 class="text-secondary border-bottom pb-2 mt-4 mb-3"><i class="fas fa-sync-alt"></i> Control de Vueltas</h6>
                            <div class="row mb-3 bg-light py-2 rounded">
                                <div class="col-md-3">
                                    <label class="small">Cant. Directo</label>
                                    <input type="number" name="cant_vueltas_directo" class="form-control form-control-sm" value="<?= val('cant_vueltas_directo') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="small">Valor Directo</label>
                                    <input type="number" name="valor_vueltas_directo" class="form-control form-control-sm" value="<?= val('valor_vueltas_directo') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="small">Cant. Local</label>
                                    <input type="number" name="cant_vueltas_local" class="form-control form-control-sm" value="<?= val('cant_vueltas_local') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="small">Valor Local</label>
                                    <input type="number" name="valor_vueltas_local" class="form-control form-control-sm" value="<?= val('valor_vueltas_local') ?>">
                                </div>
                            </div>

                            <div class="alert alert-primary mt-4 border-0 shadow-sm">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h6 class="font-weight-bold mb-1"><i class="fas fa-users"></i> Pago Leyes Sociales</h6>
                                        <p class="mb-0 small text-gray-600">Monto total pagado por el empleador (Imposiciones + SIS + Etc).</p>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="small fw-bold">Monto a Descontar ($)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-dollar-sign"></i></span>
                                            <input type="number" name="monto_leyes_sociales" class="form-control font-weight-bold text-primary"
                                                value="<?= (val('monto_leyes_sociales') > 0) ? val('monto_leyes_sociales') : $leyes_sociales_calculo ?>">
                                        </div>
                                        <?php if ($leyes_sociales_calculo > 0): ?>
                                            <small class="text-success font-weight-bold"><i class="fas fa-check"></i> Sugerido: $<?= number_format($leyes_sociales_calculo, 0, ',', '.') ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-success btn-lg shadow">
                                    <i class="fas fa-save"></i> Guardar Cierre Mensual
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

</div>


</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Inicializar DataTable "Bonito"
        $('#tablaStatusFlota').DataTable({
            language: {
                url: '<?= BASE_URL ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [
                [3, 'asc'],
                [0, 'asc']
            ], // Ordenar por Estado (Pendientes primero) y luego por Máquina
            columnDefs: [{
                    orderable: false,
                    targets: [4]
                }, // Columna Acciones no ordenable
                {
                    className: "text-center",
                    targets: [1, 3]
                },
                {
                    className: "text-end",
                    targets: [2, 4]
                }
            ],
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            initComplete: function() {
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar máquina...');
                $('.dataTables_length select').addClass('form-select shadow-sm');
            }
        });
    });

    function procesarCierreMasivo() {
        Swal.fire({
            title: '¿Realizar Cierre Masivo?',
            text: "Se generará el cierre para TODOS los buses con producción en este mes. Se calcularán leyes sociales y cargos fijos. Los valores ingresados manualmente NO se borrarán.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#1cc88a', // Success green
            cancelButtonColor: '#858796', // Secondary gray
            confirmButtonText: '<i class="fas fa-magic me-1"></i> Sí, procesar todo',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Loading indicativo
                let timerInterval;
                Swal.fire({
                    title: 'Procesando...',
                    html: 'Calculando cierres de flota. Por favor espere.',
                    timerProgressBar: true,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('mes', '<?= $mes_sel ?>');
                formData.append('anio', '<?= $anio_sel ?>');

                fetch('../ajax/procesar_cierre_masivo.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Completado!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonColor: '#4e73df'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message,
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error de Conexión',
                            text: 'No se pudo contactar con el servidor.',
                            icon: 'error'
                        });
                    });
            }
        });
    }
</script>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>