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

// --- CARGA DE DATOS PARA FILTROS (Igual que en gestionar_buses.php) ---
// Empleadores del sistema actual
$empleadores_filter = $pdo->query("SELECT id, nombre, empresa_sistema_id FROM empleadores WHERE empresa_sistema_id = " . ID_EMPRESA_SISTEMA . " ORDER BY nombre")->fetchAll();

// Unidades del sistema actual
$unidades_filter = $pdo->query("SELECT id, numero FROM unidades WHERE empresa_asociada_id = " . ID_EMPRESA_SISTEMA . " ORDER BY CAST(numero AS UNSIGNED)")->fetchAll();

// Terminales vinculados a este sistema
$terminales_filter = $pdo->query("SELECT t.id, t.nombre, t.unidad_id FROM terminales t JOIN unidades u ON t.unidad_id = u.id WHERE u.empresa_asociada_id = " . ID_EMPRESA_SISTEMA . " ORDER BY t.nombre")->fetchAll();

$msg = '';
$msg_type = '';

// =================================================================================
// 1. PROCESO DE GUARDADO (POST)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/app/includes/procesar_cierre.php';

    // Capturar acción específica (guardar o reabrir)
    $action = $_POST['action'] ?? 'guardar';
    $userRole = $_SESSION['user_role'] ?? '';

    // =================================================================================
    // CASO PROCESAR REAPERTURA (SOLO ADMIN)
    // =================================================================================
    if ($action === 'reabrir') {
        if ($userRole !== 'admin') {
            $msg = "Error: Solo los administradores pueden reabrir un mes cerrado.";
            $msg_type = "danger";
        } else {
            // Lógica de reapertura directa
            $bus_id_post = (int)$_POST['bus_id'];
            $mes_post = (int)$_POST['mes'];
            $anio_post = (int)$_POST['anio'];

            try {
                // Forzar estado a 'Abierto' manteniendo los datos
                $stmtReopen = $pdo->prepare("UPDATE cierres_maquinas SET estado = 'Abierto' WHERE bus_id = ? AND mes = ? AND anio = ?");
                $stmtReopen->execute([$bus_id_post, $mes_post, $anio_post]);

                $msg = "El mes ha sido reabierto exitosamente. Ya puede editar los valores.";
                $msg_type = "success";

                // Actualizar variables locales para que la vista refleje el cambio inmediatamente
                $cierre['estado'] = 'Abierto';
            } catch (Exception $e) {
                $msg = "Error al reabrir: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
    // =================================================================================
    // CASO GUARDAR / CERRAR (STANDARD)
    // =================================================================================
    else {
        // PRE-CHECK: ¿El mes ya está cerrado en BD?
        // Si está cerrado, NO permitir guardar nada (Inmutabilidad Backend)
        $bus_id_post = (int)$_POST['bus_id'];
        $mes_post = (int)$_POST['mes'];
        $anio_post = (int)$_POST['anio'];

        $stmtCheck = $pdo->prepare("SELECT estado FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ?");
        $stmtCheck->execute([$bus_id_post, $mes_post, $anio_post]);
        $currentState = $stmtCheck->fetchColumn();

        if ($currentState === 'Cerrado') {
            $msg = "Error: Este mes se encuentra CERRADO y no admite modificaciones. Solicite reapertura a un administrador.";
            $msg_type = "danger";
        } else {
            // Lógica Original de Guardado

            // Lógica de Guardado (Sin restricción de fecha)
            $resultado = procesar_grabado_cierre($pdo, $_POST);
            $msg = $resultado['message'];
            $msg_type = $resultado['type'];
            if ($resultado['success']) {
                $mes_sel = $resultado['mes'];
                $anio_sel = $resultado['anio'];
                $bus_id = (int)$_POST['bus_id'];
            }
        }
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

    // 1. Totales imported (ONLY Cerrada)
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

    // DETERMINE IF CLOSED
    $estado_actual = $cierre['estado'] ?? 'Abierto';
    $isClosed = ($estado_actual === 'Cerrado');
    $userRole = $_SESSION['user_role'] ?? '';

    // READONLY ATTRIBUTES
    $readonlyAttr = $isClosed ? 'readonly disabled' : '';
} else {
    // B. SI ESTAMOS EN EL DASHBOARD (LISTA DE MÁQUINAS)
    $sqlDashboard = "SELECT b.id, b.numero_maquina, b.patente, e.nombre as empleador,
                            CASE WHEN t.nombre IS NULL THEN '' ELSE t.nombre END as nombre_terminal,
                            CASE WHEN u.numero IS NULL THEN '' ELSE u.numero END as numero_unidad,
                            COUNT(pb.id) as total_guias,
                            SUM(pb.ingreso) as total_ingreso,
                            cm.id as cierre_id,
                            cm.estado as cierre_estado
                     FROM buses b
                     JOIN produccion_buses pb ON b.id = pb.bus_id
                     JOIN empleadores e ON b.empleador_id = e.id
                     LEFT JOIN terminales t ON b.terminal_id = t.id
                     LEFT JOIN unidades u ON t.unidad_id = u.id
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
            <div class="d-none d-sm-block">
                <button onclick="procesarCierreMasivo()" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-magic fa-sm text-white-50"></i> Procesar Cierre Masivo
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ALERT: CLOSED STATE -->
    <?php if ($bus_id > 0 && $isClosed): ?>
        <div class="alert alert-warning border-left-warning shadow-sm" role="alert">
            <h4 class="alert-heading"><i class="fas fa-lock"></i> Mes Cerrado</h4>
            <p class="mb-0">Este cierre mensual se encuentra FINALIZADO. Los valores son de solo lectura y no pueden modificarse.</p>
            <?php if ($userRole !== 'admin'): ?>
                <hr>
                <p class="mb-0 text-xs">Si necesita realizar correcciones, contacte a un Administrador para reabrir el mes.</p>
            <?php endif; ?>
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
            <div class="card-header py-3 bg-gradient-light d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Estado de Cierre de Flota</h6>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                    <i class="fas fa-filter"></i> Filtros
                </button>
            </div>

            <!-- Filter Panel Reparto -->
            <div class="collapse border-bottom bg-light p-3" id="filterPanel">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filtrar por Unidad:</label>
                        <select class="form-select form-select-sm" id="filtroUnidad">
                            <option value="">Todas</option>
                            <?php foreach ($unidades_filter as $u): ?>
                                <option value="Unidad <?= $u['numero'] ?>">Unidad <?= $u['numero'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filtrar por Empleador:</label>
                        <select class="form-select form-select-sm" id="filtroEmpleador">
                            <option value="">Todos</option>
                            <?php foreach ($empleadores_filter as $e): ?>
                                <option value="<?= htmlspecialchars($e['nombre']) ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filtrar por Terminal:</label>
                        <select class="form-select form-select-sm" id="filtroTerminal">
                            <option value="">Todos</option>
                            <?php foreach ($terminales_filter as $t): ?>
                                <option value="<?= htmlspecialchars($t['nombre']) ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-sm btn-outline-secondary w-100" id="btnLimpiarFiltros">
                            <i class="fas fa-times me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover align-middle mb-0" id="tablaStatusFlota" width="100%">
                        <thead class="bg-light text-uppercase text-xs font-weight-bold">
                            <tr>
                                <th class="ps-4">Máquina</th>
                                <th>Unidad/Terminal</th> <!-- Nuevo -->
                                <th>Producción</th>
                                <th>Ingreso Total</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($maquinas_dashboard) > 0): ?>
                                <?php foreach ($maquinas_dashboard as $m):
                                    $tiene_cierre = !empty($m['cierre_id']);
                                    $estado = $m['cierre_estado'] ?? 'Abierto';
                                    $cerrado = ($tiene_cierre && $estado === 'Cerrado');
                                    $borrador = ($tiene_cierre && $estado === 'Abierto');
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-gray-800">Nº <?= $m['numero_maquina'] ?></div>
                                            <small class="text-muted"><?= $m['patente'] ?> &bull; <?= htmlspecialchars($m['empleador']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($m['numero_unidad']): ?>
                                                <span class="badge bg-light text-dark border me-2">Unidad <?= $m['numero_unidad'] ?></span>
                                            <?php endif; ?>
                                            <small class="text-muted"><?= $m['nombre_terminal'] ?: '-' ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= $m['total_guias'] ?> guías</span>
                                        </td>
                                        <td class="text-success fw-bold">$ <?= number_format($m['total_ingreso'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php if ($cerrado): ?>
                                                <span class="badge bg-success shadow-sm"><i class="fas fa-check"></i> Cerrado</span>
                                            <?php elseif ($borrador): ?>
                                                <span class="badge bg-info text-white shadow-sm"><i class="fas fa-edit"></i> Borrador</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white shadow-sm"><i class="fas fa-clock"></i> Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="cierre_mensual.php?bus_id=<?= $m['id'] ?>&mes=<?= $mes_sel ?>&anio=<?= $anio_sel ?>"
                                                class="btn btn-sm shadow-sm <?= $cerrado ? 'btn-outline-secondary' : 'btn-success text-white' ?> rounded-pill px-3">
                                                <?= $cerrado ? '<i class="fas fa-eye me-1"></i> Ver / Reabrir' : '<i class="fas fa-edit me-1"></i> Gestionar' ?>
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
            <?php if (!$isClosed): ?>
                <input type="hidden" name="action" value="guardar">
            <?php endif; ?>

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
                                        value="<?= val('monto_administracion_aplicado', $admin_global) ?>" <?= $readonlyAttr ?>>
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
                                    <input type="number" name="subsidio_operacional" class="form-control" value="<?= val('subsidio_operacional') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Devolución Minutos</label>
                                    <input type="number" name="devolucion_minutos" class="form-control" value="<?= val('devolucion_minutos') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Otros Ingresos 1</label>
                                    <input type="number" name="otros_ingresos_1" class="form-control" value="<?= val('otros_ingresos_1') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Otros Ingresos 2</label>
                                    <input type="number" name="otros_ingresos_2" class="form-control" value="<?= val('otros_ingresos_2') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Otros Ingresos 3</label>
                                    <input type="number" name="otros_ingresos_3" class="form-control" value="<?= val('otros_ingresos_3') ?>" <?= $readonlyAttr ?>>
                                </div>
                            </div>

                            <h6 class="text-danger border-bottom pb-2 mt-4 mb-3"><i class="fas fa-minus-circle"></i> Descuentos / Cargos</h6>
                            <div class="row mb-3">
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Anticipo</label>
                                    <input type="number" name="anticipo" class="form-control" value="<?= val('anticipo') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Asig. Familiar</label>
                                    <input type="number" name="asignacion_familiar" class="form-control" value="<?= val('asignacion_familiar') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Pago Minutos</label>
                                    <input type="number" name="pago_minutos" class="form-control" value="<?= val('pago_minutos') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Saldo Anterior</label>
                                    <input type="number" name="saldo_anterior" class="form-control" value="<?= val('saldo_anterior') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Ayuda Mutua</label>
                                    <input type="number" name="ayuda_mutua" class="form-control" value="<?= val('ayuda_mutua') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Servicio Grúa</label>
                                    <input type="number" name="servicio_grua" class="form-control" value="<?= val('servicio_grua') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Póliza Seguro</label>
                                    <input type="number" name="poliza_seguro" class="form-control" value="<?= val('poliza_seguro') ?>" <?= $readonlyAttr ?>>
                                </div>
                            </div>

                            <h6 class="text-secondary border-bottom pb-2 mt-4 mb-3"><i class="fas fa-file-contract"></i> Cargos Fijos Mensuales (Config)</h6>
                            <div class="row mb-3 bg-light p-3 rounded">
                                <div class="col-md-4 mb-3">
                                    <label class="small fw-bold">Derechos de Loza</label>
                                    <input type="number" name="derechos_loza" class="form-control" value="<?= val('derechos_loza', $defaults['derechos_loza']) ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="small fw-bold">Seguro y Cartolas</label>
                                    <input type="number" name="seguro_cartolas" class="form-control"
                                        value="<?= ($mes_sel == 9) ? val('seguro_cartolas', $defaults['seguro_cartolas']) : 0 ?>"
                                        <?= ($mes_sel != 9) ? 'readonly style="background-color: #e9ecef;"' : $readonlyAttr ?>>
                                    <?php if ($mes_sel != 9): ?>
                                        <small class="text-xs text-muted d-block mt-1">Este campo solo es editable en Septiembre.</small>
                                    <?php else: ?>
                                        <small class="text-xs text-muted d-block mt-1">Sugerido para Septiembre.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="small fw-bold">GPS</label>
                                    <input type="number" name="gps" class="form-control" value="<?= val('gps', $defaults['gps']) ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small fw-bold">Boleta Grantía</label>
                                    <input type="number" name="boleta_garantia" class="form-control" value="<?= val('boleta_garantia', $defaults['boleta_garantia']) ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small fw-bold">Boleta Grantía 2</label>
                                    <input type="number" name="boleta_garantia_dos" class="form-control" value="<?= val('boleta_garantia_dos', $defaults['boleta_garantia_dos']) ?>" <?= $readonlyAttr ?>>
                                </div>
                            </div>

                            <h6 class="text-secondary border-bottom pb-2 mt-4 mb-3"><i class="fas fa-sync-alt"></i> Control de Vueltas</h6>
                            <div class="row mb-3 bg-light py-2 rounded">
                                <div class="col-md-3">
                                    <label class="small">Cant. Directo</label>
                                    <input type="number" name="cant_vueltas_directo" class="form-control form-control-sm" value="<?= val('cant_vueltas_directo') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-3">
                                    <label class="small">Valor Directo</label>
                                    <input type="number" name="valor_vueltas_directo" class="form-control form-control-sm" value="<?= val('valor_vueltas_directo') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-3">
                                    <label class="small">Cant. Local</label>
                                    <input type="number" name="cant_vueltas_local" class="form-control form-control-sm" value="<?= val('cant_vueltas_local') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-3">
                                    <label class="small">Valor Local</label>
                                    <input type="number" name="valor_vueltas_local" class="form-control form-control-sm" value="<?= val('valor_vueltas_local') ?>" <?= $readonlyAttr ?>>
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
                                            <input type="number" name="monto_leyes_sociales" id="input_monto_leyes_sociales" class="form-control font-weight-bold text-primary"
                                                value="<?= ($isClosed) ? val('monto_leyes_sociales') : (($leyes_sociales_calculo > 0) ? $leyes_sociales_calculo : val('monto_leyes_sociales')) ?>" <?= $readonlyAttr ?>>
                                            <?php if (!$isClosed): ?>
                                                <button type="button" class="btn btn-info text-white" id="btn-calc-sociales" title="Calcular Automáticamente por Leyes Sociales"><i class="fas fa-calculator"></i></button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($leyes_sociales_calculo > 0): ?>
                                            <small class="text-success font-weight-bold"><i class="fas fa-check"></i> Sugerido: $<?= number_format($leyes_sociales_calculo, 0, ',', '.') ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4 d-md-flex justify-content-md-end">
                                <?php if (!$isClosed): ?>
                                    <button type="submit" name="estado" value="Abierto" class="btn btn-secondary btn-lg shadow me-md-2">
                                        <i class="fas fa-save"></i> Guardar(Abrir)
                                    </button>
                                    <button type="submit" name="estado" value="Cerrado" class="btn btn-success btn-lg shadow">
                                        <i class="fas fa-lock"></i> Cerrar Mes
                                    </button>
                                <?php else: ?>
                                    <?php if ($userRole === 'admin'): ?>
                                        <button type="submit" name="action" value="reabrir" class="btn btn-warning btn-lg shadow text-dark">
                                            <i class="fas fa-unlock"></i> REABRIR MES (Admin)
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
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
        const table = $('#tablaStatusFlota').DataTable({
            language: {
                url: '<?= BASE_URL ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [
                [4, 'asc'], // Order by Estado (Column 4)
                [0, 'asc'] // Order by Maquina (Column 0)
            ],
            columnDefs: [{
                    orderable: false,
                    targets: [5] // Acciones
                },
                {
                    className: "text-center",
                    targets: [2, 4] // Produccion, Estado
                },
                {
                    className: "text-end",
                    targets: [3, 5] // Ingreso, Accion
                }
            ],
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            initComplete: function() {
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar máquina...');
                $('.dataTables_length select').addClass('form-select shadow-sm');
            }
        });

        // --- EXTERNAL FILTERS ---
        // Custom filtering function
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                const fUnidad = $('#filtroUnidad').val();
                const fEmp = $('#filtroEmpleador').val();
                const fTerm = $('#filtroTerminal').val();

                // Column indices:
                // 0: Maquina (contains Patente & Employer text)
                // 1: Unidad/Terminal (contains Text)

                // Let's grab text from cells
                const cellMaquina = data[0] || ""; // Contains Employer and Name
                const cellUbi = data[1] || ""; // Contains Unit and Terminal

                if (fUnidad && !cellUbi.includes(fUnidad)) return false;
                if (fTerm && !cellUbi.includes(fTerm)) return false;
                if (fEmp && !cellMaquina.includes(fEmp)) return false;

                return true;
            }
        );

        // Event Listeners for Filters
        $('#filtroUnidad, #filtroEmpleador, #filtroTerminal').on('change', function() {
            table.draw();
        });

        $('#btnLimpiarFiltros').click(function() {
            $('#filtroUnidad').val('');
            $('#filtroEmpleador').val('');
            $('#filtroTerminal').val('');
            table.draw();
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

<script>
    document.getElementById('btn-calc-sociales')?.addEventListener('click', function() {
        const btn = this;
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        const busId = <?php echo $bus_id; ?>;
        const mes = <?php echo $mes_sel; ?>;
        const anio = <?php echo $anio_sel; ?>;

        fetch(`../ajax/calcular_leyes_cierre.php?bus_id=${busId}&mes=${mes}&anio=${anio}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('input_monto_leyes_sociales').value = data.monto_leyes_sociales;
                    const formattedMonto = new Intl.NumberFormat('es-CL').format(data.monto_leyes_sociales);

                    // Construir tabla HTML de detalle
                    let htmlTable = '<div style="max-height: 200px; overflow-y: auto;"><table class="table table-sm table-striped text-start" style="font-size: 0.85rem;">';
                    htmlTable += '<thead class="table-dark"><tr><th>Trabajador</th><th class="text-end">Días</th><th class="text-end">Imponible</th><th class="text-end">Costo Emp.</th></tr></thead><tbody>';

                    if (data.lista_trabajadores && data.lista_trabajadores.length > 0) {
                        data.lista_trabajadores.forEach(t => {
                            let diasText = t.dias;
                            if (t.guias && t.guias > t.dias) {
                                diasText += ` <span class="text-muted text-xs">(${t.guias} Guías)</span>`;
                            }
                            htmlTable += `<tr>
                                <td>${t.nombre}</td>
                                <td class="text-end">${diasText}</td>
                                <td class="text-end">$${new Intl.NumberFormat('es-CL').format(t.imponible)}</td>
                                <td class="text-end fw-bold">$${new Intl.NumberFormat('es-CL').format(t.costo_total)}</td>
                            </tr>`;
                        });
                    } else {
                        htmlTable += '<tr><td colspan="4" class="text-center text-muted">Sin trabajadores procesados.</td></tr>';
                    }
                    htmlTable += '</tbody></table></div>';

                    htmlTable += `<div class="mt-2 text-end fw-bold border-top pt-2">Total Leyes Sociales: $${formattedMonto}</div>`;

                    // Usar SweetAlert en lugar de alert() simple
                    Swal.fire({
                        title: 'Detalle Leyes Sociales (Estimado)',
                        html: htmlTable,
                        icon: 'info',
                        width: '600px'
                    });
                } else {
                    Swal.fire('Atención', 'No se pudo calcular: ' + data.message, 'warning');
                }
            })
            .catch(err => alert('Error de conexión: ' + err))
            .finally(() => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
    });
</script>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>