<?php
// buses/configuracion.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// --- CONFIGURACIÓN & LÓGICA ---
$msg = '';
$msg_type = '';

// 1. Contexto (Mes/Año)
$mes_sel = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio_sel = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
if ($mes_sel < 1 || $mes_sel > 12) $mes_sel = date('n');

// 2. Procesar Guardado (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_all') {
    $mes = (int)$_POST['mes'];
    $anio = (int)$_POST['anio'];
    $monto_global = (int)$_POST['monto_admin'];

    try {
        $pdo->beginTransaction();

        // A. Guardar Monto Global
        $stmtGlobal = $pdo->prepare("INSERT INTO parametros_mensuales 
            (mes, anio, monto_administracion_global, derechos_loza_global, seguro_cartolas_global, gps_global, boleta_garantia_global, boleta_garantia_dos_global) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            monto_administracion_global = VALUES(monto_administracion_global),
            derechos_loza_global = VALUES(derechos_loza_global),
            seguro_cartolas_global = VALUES(seguro_cartolas_global),
            gps_global = VALUES(gps_global),
            boleta_garantia_global = VALUES(boleta_garantia_global),
            boleta_garantia_dos_global = VALUES(boleta_garantia_dos_global)
        ");

        $derechos_loza = (int)($_POST['derechos_loza'] ?? 0);
        $seguro_cartolas = (int)($_POST['seguro_cartolas'] ?? 0);
        $gps = (int)($_POST['gps'] ?? 0);
        $boleta_garantia = (int)($_POST['boleta_garantia'] ?? 0);
        $boleta_garantia_dos = (int)($_POST['boleta_garantia_dos'] ?? 0);

        $stmtGlobal->execute([$mes, $anio, $monto_global, $derechos_loza, $seguro_cartolas, $gps, $boleta_garantia, $boleta_garantia_dos]);

        // --- ACTUALIZACIÓN MASIVA DE CIERRES EXISTENTES ---
        // Actualiza TODAS las máquinas (cerradas o pendientes) con los nuevos valores
        $stmtUpdateMasivo = $pdo->prepare("UPDATE cierres_maquinas 
            SET derechos_loza = ?, 
                seguro_cartolas = ?, 
                gps = ?, 
                boleta_garantia = ?, 
                boleta_garantia_dos = ? 
            WHERE mes = ? AND anio = ?");

        // Aplicar regla de Septiembre para el update masivo también
        $seguro_cartolas_final = ($mes == 9) ? $seguro_cartolas : 0;

        $stmtUpdateMasivo->execute([
            $derechos_loza,
            $seguro_cartolas_final,
            $gps,
            $boleta_garantia,
            $boleta_garantia_dos,
            $mes,
            $anio
        ]);

        // B. Guardar Bus Pagador
        if (isset($_POST['bus_pagador']) && is_array($_POST['bus_pagador'])) {
            $stmtLeyes = $pdo->prepare("INSERT INTO configuracion_leyes_mensual (mes, anio, empleador_id, bus_pagador_id) 
                                        VALUES (?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE bus_pagador_id = VALUES(bus_pagador_id)");

            foreach ($_POST['bus_pagador'] as $empleador_id => $bus_id) {
                $bus_final = !empty($bus_id) ? $bus_id : null;
                $stmtLeyes->execute([$mes, $anio, $empleador_id, $bus_final]);
            }
        }

        $pdo->commit();
        $msg = "Configuración de " . str_pad($mes, 2, '0', STR_PAD_LEFT) . "/$anio guardada correctamente.";
        $msg_type = "success";

        $mes_sel = $mes;
        $anio_sel = $anio;
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error al guardar: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// B. Datos Globales
// Monto Admin y Nuevos Campos (5)
$stmtG = $pdo->prepare("SELECT 
    monto_administracion_global,
    derechos_loza_global,
    seguro_cartolas_global,
    gps_global,
    boleta_garantia_global,
    boleta_garantia_dos_global
FROM parametros_mensuales WHERE mes = ? AND anio = ?");
$stmtG->execute([$mes_sel, $anio_sel]);
$global = $stmtG->fetch(PDO::FETCH_ASSOC);

// Valores por defecto
$defaults = [
    'monto_administracion_global' => 350000,
    'derechos_loza_global' => 0,
    'seguro_cartolas_global' => 0,
    'gps_global' => 0,
    'boleta_garantia_global' => 0,
    'boleta_garantia_dos_global' => 0
];

if (!$global) {
    // Buscar historial anterior
    $stmtHistG = $pdo->prepare("SELECT 
        monto_administracion_global,
        derechos_loza_global,
        seguro_cartolas_global,
        gps_global,
        boleta_garantia_global,
        boleta_garantia_dos_global
    FROM parametros_mensuales WHERE (anio < ?) OR (anio = ? AND mes < ?) ORDER BY anio DESC, mes DESC LIMIT 1");
    $stmtHistG->execute([$anio_sel, $anio_sel, $mes_sel]);
    $hist_global = $stmtHistG->fetch(PDO::FETCH_ASSOC);

    if ($hist_global) {
        $defaults = array_merge($defaults, $hist_global);
    }
} else {
    $defaults = array_merge($defaults, $global);
}

// B. Empleadores y Buses
$empleadores_raw = $pdo->query("SELECT * FROM empleadores ORDER BY nombre ASC")->fetchAll();
$buses_raw = $pdo->query("SELECT id, numero_maquina, patente, empleador_id FROM buses ORDER BY CAST(numero_maquina AS UNSIGNED) ASC")->fetchAll();

$data_empleadores = [];
foreach ($empleadores_raw as $emp) {
    $data_empleadores[$emp['id']] = [
        'nombre' => $emp['nombre'],
        'rut' => $emp['rut'],
        'buses' => []
    ];
}
foreach ($buses_raw as $bus) {
    if (isset($data_empleadores[$bus['empleador_id']])) {
        $data_empleadores[$bus['empleador_id']]['buses'][] = $bus;
    }
}

// C. Configuración Actual de Leyes
$stmtL = $pdo->prepare("SELECT empleador_id, bus_pagador_id FROM configuracion_leyes_mensual WHERE mes = ? AND anio = ?");
$stmtL->execute([$mes_sel, $anio_sel]);
$config_leyes = $stmtL->fetchAll(PDO::FETCH_KEY_PAIR);

// LÓGICA DE CASCADA (BUSES): Preparar statement para buscar histórico
$stmtHistBus = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual 
                              WHERE empleador_id = ? 
                              AND ((anio < ?) OR (anio = ? AND mes < ?)) 
                              ORDER BY anio DESC, mes DESC LIMIT 1");

$months_list = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cogs"></i> Configuración Mensual</h1>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="mainForm">
        <input type="hidden" name="action" value="save_all">

        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-gradient-primary d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">1. Seleccionar Período</h6>
                <button type="submit" class="btn btn-custom-cyan btn-sm fw-bold">
                    <i class="fas fa-save"></i> Guardar Todo
                </button>
            </div>
            <div class="card-body bg-light">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Mes</label>
                        <select class="form-select" onchange="window.location.href='configuracion.php?anio=<?= $anio_sel ?>&mes='+this.value">
                            <?php foreach ($months_list as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $k == $mes_sel ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="mes" value="<?= $mes_sel ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Año</label>
                        <input type="number" class="form-control" value="<?= $anio_sel ?>" onchange="window.location.href='configuracion.php?mes=<?= $mes_sel ?>&anio='+this.value">
                        <input type="hidden" name="anio" value="<?= $anio_sel ?>">
                    </div>
                    <div class="col-md-6 text-end text-muted align-self-center">
                        <small>Editando: <strong><?= $months_list[$mes_sel] ?> <?= $anio_sel ?></strong></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-success">
                        <h6 class="m-0 font-weight-bold text-custom-cyan">2. Valores Globales</h6>
                    </div>
                    <div class="card-body">
                        <label class="form-label fw-bold">Monto Admin. Global ($)</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text">$</span>
                            <input type="number" name="monto_admin" class="form-control" value="<?= $defaults['monto_administracion_global'] ?>" required>
                        </div>

                        <h6 class="font-weight-bold text-gray-800 border-bottom pb-2 mb-3 mt-4">Otros Descuentos Mensuales</h6>

                        <div class="mb-3">
                            <label class="small fw-bold">Derechos de Loza</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="derechos_loza" class="form-control" value="<?= $defaults['derechos_loza_global'] ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold">Seguro y Cartolas (Solo Septiembre)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="seguro_cartolas" class="form-control"
                                    value="<?= ($mes_sel == 9) ? $defaults['seguro_cartolas_global'] : 0 ?>"
                                    <?= ($mes_sel != 9) ? 'readonly style="background-color: #e9ecef;"' : '' ?>>
                            </div>
                            <?php if ($mes_sel != 9): ?>
                                <small class="text-xs text-muted">Este campo solo es editable en Septiembre.</small>
                            <?php else: ?>
                                <small class="text-xs text-muted">Aplica automáticamente para Septiembre.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold">GPS</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="gps" class="form-control" value="<?= $defaults['gps_global'] ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold">Boleta Grantía</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="boleta_garantia" class="form-control" value="<?= $defaults['boleta_garantia_global'] ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold">Boleta Grantía 2</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="boleta_garantia_dos" class="form-control" value="<?= $defaults['boleta_garantia_dos_global'] ?>">
                            </div>
                        </div>

                        <div class="form-text mt-3">
                            <?php if (!$global): ?>
                                <i class="fas fa-info-circle text-info"></i> Valores sugeridos del mes anterior.
                            <?php else: ?>
                                <span class="text-highlight-success"><i class="fas fa-check-circle"></i> Configuración guardada para este mes.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-info d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-custom-cyan">3. Asignación de Leyes Sociales</h6>
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-gray-400"></i></span>
                            <input type="text" id="filterInput" class="form-control border-start-0" placeholder="Buscar empleador...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover mb-0" id="employersTable">
                                <thead class="table-light sticky-top" style="top: 0; z-index: 1;">
                                    <tr>
                                        <th class="ps-4">Empleador</th>
                                        <th>Bus Responsable del Pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $con_buses = 0;
                                    foreach ($data_empleadores as $empID => $datos):
                                        if (count($datos['buses']) === 0) continue;
                                        $con_buses++;

                                        // --- LÓGICA INTELIGENTE DE SELECCIÓN ---
                                        $seleccionado = '';
                                        $fuente = 'nuevo'; // nuevo, guardado, historico, automatico

                                        // 1. ¿Existe ya guardado para este mes?
                                        if (isset($config_leyes[$empID])) {
                                            $seleccionado = $config_leyes[$empID];
                                            $fuente = 'guardado';
                                        }
                                        // 2. Si no, ¿Tiene UN SOLO bus? -> Auto-seleccionar
                                        elseif (count($datos['buses']) === 1) {
                                            $seleccionado = $datos['buses'][0]['id'];
                                            $fuente = 'automatico';
                                        }
                                        // 3. Si no, ¿Existe configuración de un mes anterior? (Cascada)
                                        else {
                                            $stmtHistBus->execute([$empID, $anio_sel, $anio_sel, $mes_sel]);
                                            $historia = $stmtHistBus->fetchColumn();
                                            if ($historia) {
                                                $seleccionado = $historia;
                                                $fuente = 'historico';
                                            }
                                        }
                                    ?>
                                        <tr class="employer-row">
                                            <td class="ps-4 align-middle">
                                                <div class="fw-bold employer-name text-gray-800"><?= $datos['nombre'] ?></div>
                                                <small class="text-muted"><?= count($datos['buses']) ?> máquinas &bull; Rut: <?= $datos['rut'] ?></small>

                                                <?php if ($fuente === 'historico'): ?>
                                                    <span class="badge bg-warning text-dark ms-2" style="font-size: 0.65rem;">Copiado de mes anterior</span>
                                                <?php elseif ($fuente === 'automatico'): ?>
                                                    <span class="badge bg-info text-white ms-2" style="font-size: 0.65rem;">Único Bus</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle">
                                                <select name="bus_pagador[<?= $empID ?>]" class="form-select select2-bus form-select-sm">
                                                    <option value="">-- No paga Leyes este mes --</option>
                                                    <?php foreach ($datos['buses'] as $b): ?>
                                                        <option value="<?= $b['id'] ?>" <?= $b['id'] == $seleccionado ? 'selected' : '' ?>>
                                                            Nº <?= $b['numero_maquina'] ?> (<?= $b['patente'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if ($con_buses === 0): ?>
                                        <tr>
                                            <td colspan="2" class="text-center p-4">No hay empleadores con máquinas registradas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-end">
                        <button type="submit" class="btn btn-primary shadow-sm">
                            <i class="fas fa-check-circle"></i> Guardar Configuración
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Select2
        $('.select2-bus').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Seleccione máquina',
            allowClear: true
        });

        // Buscador
        $('#filterInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $("#employersTable .employer-row").filter(function() {
                var text = $(this).find(".employer-name").text().toLowerCase();
                $(this).toggle(text.indexOf(value) > -1)
            });
        });
    });
</script>