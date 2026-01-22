<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Validar POST
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['empleador_id']) || !isset($_POST['mes']) || !isset($_POST['ano'])) {
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}
$empleador_id = (int)$_POST['empleador_id'];
$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];
$source = isset($_POST['source']) ? $_POST['source'] : '';

// Verificación de Seguridad (Anti-IDOR)
// Validar que el empleador pertenezca a la empresa del sistema (ID_EMPRESA_SISTEMA)
$stmt_idor = $pdo->prepare("SELECT COUNT(*) FROM empleadores WHERE id = :id AND empresa_sistema_id = :sys_id");
$stmt_idor->execute(['id' => $empleador_id, 'sys_id' => ID_EMPRESA_SISTEMA]);
if ($stmt_idor->fetchColumn() == 0) {
    http_response_code(403); // Forbidden
    die("Error de Seguridad: El empleador solicitado no pertenece a la empresa del sistema.");
}

try {
    $stmt = $pdo->prepare("SELECT esta_cerrado FROM cierres_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
    $stmt->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
    $cierre = $stmt->fetch();

    if ($cierre && $cierre['esta_cerrado']) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Período CERRADO."];
        if ($source === 'dashboard') {
            header('Location: ' . BASE_URL . '/planillas/dashboard_mensual.php?empleador_id=' . $empleador_id . '&mes=' . $mes . '&ano=' . $ano);
        } else {
            header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
        }
        exit;
    }

    $stmt_e = $pdo->prepare("SELECT nombre, rut FROM empleadores WHERE id = ?");
    $stmt_e->execute([$empleador_id]);
    $empleador = $stmt_e->fetch();

    // Calcular rango del mes seleccionado para filtrar trabajadores vigentes EN ESE PERIODO
    $primer_dia_mes = "$ano-$mes-01";
    $ultimo_dia_mes = date('Y-m-t', strtotime($primer_dia_mes));

    // OPTIMIZACIÓN: Traer datos históricos (Anexos) directamente en la consulta principal para evitar N+1
    // Se usan subconsultas para obtener el último valor vigente de sueldo y tipo de contrato a la fecha de corte.
    $trabajadores = $pdo->prepare("
        SELECT 
            t.id, t.nombre, t.rut, t.estado_previsional, 
            c.id as id_contrato,
            -- Sueldo Base: Último anexo con sueldo > 0, o el del contrato original
            COALESCE(
                (SELECT nuevo_sueldo FROM anexos_contrato ac 
                 WHERE ac.contrato_id = c.id AND ac.fecha_anexo <= :ultimo_dia AND ac.nuevo_sueldo > 0 
                 ORDER BY ac.fecha_anexo DESC LIMIT 1),
                c.sueldo_imponible
            ) as sueldo_base,
            -- Part Time: Último anexo con valor definido (no null), o el del contrato original (default 0)
            COALESCE(
                (SELECT nuevo_es_part_time FROM anexos_contrato ac 
                 WHERE ac.contrato_id = c.id AND ac.fecha_anexo <= :ultimo_dia AND ac.nuevo_es_part_time IS NOT NULL 
                 ORDER BY ac.fecha_anexo DESC LIMIT 1),
                c.es_part_time,
                0
            ) as es_part_time
        FROM trabajadores t
        JOIN contratos c ON t.id = c.trabajador_id 
        WHERE c.empleador_id = :eid
        AND c.fecha_inicio <= :ultimo_dia
        AND (c.fecha_termino IS NULL OR c.fecha_termino >= :primer_dia)
        AND (c.fecha_finiquito IS NULL OR c.fecha_finiquito >= :primer_dia)
        GROUP BY t.id, c.id
        ORDER BY t.nombre
    ");
    $trabajadores->execute([
        'eid' => $empleador_id,
        'primer_dia' => $primer_dia_mes,
        'ultimo_dia' => $ultimo_dia_mes
    ]);
    $trabajadores = $trabajadores->fetchAll();
    // (Bucle N+1 eliminado porque los datos ya vienen en la consulta)

    $sql_planilla = "SELECT p.*, t.nombre as nombre_trabajador, t.rut as rut_trabajador, t.estado_previsional,
                            c.sueldo_imponible as sueldo_base_contrato
                     FROM planillas_mensuales p
                     JOIN trabajadores t ON p.trabajador_id = t.id
                     LEFT JOIN contratos c ON t.id = c.trabajador_id AND c.esta_finiquitado = 0 AND c.empleador_id = :eid
                     WHERE p.empleador_id = :eid AND p.mes = :mes AND p.ano = :ano";
    $stmt_p = $pdo->prepare($sql_planilla);
    $stmt_p->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
    $planilla_guardada = $stmt_p->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/app/includes/header.php';

$meses_nombres = [
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
$nombre_mes = $meses_nombres[$mes] ?? $mes;
?>

<style>
    :root {
        --primary-color: <?php echo COLOR_SISTEMA; ?>;
    }

    .bg-grid {
        background-color: #f8f9fc;
        min-height: 100vh;
    }

    .sticky-actions {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: #fff;
        border-bottom: 2px solid var(--primary-color);
    }

    .table-pro {
        border-collapse: separate;
        border-spacing: 0 10px;
    }

    .fila-trabajador {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .fila-trabajador td {
        padding: 15px 10px;
        border: none !important;
    }

    .field-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .field-label {
        font-size: 0.6rem;
        font-weight: 800;
        color: #b0b3c5;
        text-transform: uppercase;
    }

    .input-pro {
        border: 1px solid #d1d3e2;
        border-radius: 6px;
        padding: 6px;
        font-size: 0.85rem;
        font-weight: 700;
        width: 100%;
    }

    .badge-pensionado {
        font-size: 0.6rem;
        background: #f6c23e;
        color: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 4px;
    }
</style>

<div class="bg-grid pb-5">
    <div class="sticky-actions py-3 mb-4 shadow-sm">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <?php
                $back_url = 'cargar_selector.php';
                if ($source === 'dashboard') {
                    $back_url = "dashboard_mensual.php?mes=$mes&ano=$ano";
                }
                ?>
                <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-sm rounded-circle shadow-sm" title="Volver">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h4 mb-0 fw-bold">Planilla <?php echo "$nombre_mes $ano"; ?></h1>
                    <small class="text-primary fw-bold"><?php echo htmlspecialchars($empleador['nombre']); ?></small>
                </div>
            </div>
            <button class="btn btn-success fw-bold px-5 shadow-sm rounded-pill" id="btn-guardar-planilla">
                <i class="fas fa-save me-2"></i> GUARDAR PLANILLA
            </button>
        </div>
    </div>

    <div class="container-fluid px-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3 row g-2 align-items-end">
                <div class="col-md-10">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1"><i class="fas fa-user-plus me-1"></i> Añadir Trabajador a la Planilla</label>
                    <select class="form-select border-0 bg-light" id="select-agregar-trabajador">
                        <option></option>
                        <?php foreach ($trabajadores as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                data-nombre="<?= htmlspecialchars($t['nombre']) ?>"
                                data-es-part-time="<?= $t['es_part_time'] ?>"
                                data-rut="<?= htmlspecialchars($t['rut']) ?>"
                                data-estado="<?= htmlspecialchars($t['estado_previsional']) ?>"
                                data-sueldo-base="<?= (int)$t['sueldo_base'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?> (<?= $t['rut'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100 fw-bold" id="btn-agregar-trabajador">AGREGAR</button>
                </div>
            </div>
        </div>

        <form id="form-planilla">
            <input type="hidden" id="empleador_id" value="<?php echo $empleador_id; ?>">
            <input type="hidden" id="mes" value="<?php echo $mes; ?>">
            <input type="hidden" id="ano" value="<?php echo $ano; ?>">

            <div class="table-responsive">
                <table class="table table-pro align-middle" id="grid-planilla" style="min-width: 1500px;">
                    <thead class="text-muted small">
                        <tr>
                            <th class="ps-4">Trabajador</th>
                            <th>Ingresos Imponibles</th>
                            <th class="text-center">Días</th>
                            <th>Contrato & Periodo</th>
                            <th>Previsión / Salud / Licencia</th>
                            <th class="text-center">Cesantía pensionado</th>
                            <th class="text-center pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <?php foreach ($planilla_guardada as $p):
                            $es_pensionado = (trim(strtolower($p['estado_previsional'])) == 'pensionado');
                            $sueldo_base_row = $p['sueldo_base_contrato'] ?? 0;
                        ?>
                            <tr class="fila-trabajador" data-id-trabajador="<?= $p['trabajador_id'] ?>" data-estado-previsional="<?= $p['estado_previsional'] ?>" data-sueldo-base="<?= $sueldo_base_row ?>">
                                <td class="ps-4">
                                    <input type="hidden" name="trabajador_id[]" value="<?= $p['trabajador_id'] ?>">
                                    <input type="hidden" name="es_part_time[]" value="<?= $p['es_part_time_snapshot'] ?? $p['es_part_time'] ?? 0 ?>" class="es-part-time-input">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['nombre_trabajador']) ?></div>
                                    <div class="text-muted small"><?= $p['rut_trabajador'] ?></div>
                                    <?php if ($es_pensionado): ?><span class="badge-pensionado">PENSIONADO</span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <div class="field-group flex-fill"><label class="field-label">Sueldo Base</label><input type="number" class="input-pro" name="sueldo_imponible[]" value="<?= $p['sueldo_imponible'] ?>"></div>
                                        <div class="field-group flex-fill"><label class="field-label">Bonos</label><input type="number" class="input-pro" name="bonos_imponibles[]" value="<?= $p['bonos_imponibles'] ?? 0 ?>"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="field-group"><label class="field-label">Días</label><input type="number" class="input-pro text-center dias-trabajados" name="dias_trabajados[]" value="<?= $p['dias_trabajados'] ?>" max="30"></div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 mb-2">
                                        <div class="field-group flex-fill"><label class="field-label">Tipo</label>
                                            <select class="input-pro tipo-contrato" name="tipo_contrato[]">
                                                <option value="Indefinido" <?= $p['tipo_contrato'] == 'Indefinido' ? 'selected' : '' ?>>Indefinido</option>
                                                <option value="Fijo" <?= $p['tipo_contrato'] == 'Fijo' ? 'selected' : '' ?>>Fijo</option>
                                            </select>
                                        </div>
                                        <div class="field-group flex-fill"><label class="field-label">Inicio</label><input type="date" class="input-pro" name="fecha_inicio[]" value="<?= $p['fecha_inicio'] ?>"></div>
                                    </div>
                                    <div class="field-group col-fecha-termino"><label class="field-label">Término</label><input type="date" class="input-pro fecha-termino" name="fecha_termino[]" value="<?= $p['fecha_termino'] ?>"></div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <div class="field-group"><label class="field-label">Aportes</label><input type="number" class="input-pro" name="aportes[]" value="<?= $p['aportes'] ?>"></div>
                                        <div class="field-group"><label class="field-label">Salud Adic</label><input type="number" class="input-pro" name="adicional_salud_apv[]" value="<?= $p['adicional_salud_apv'] ?>"></div>
                                        <div class="field-group"><label class="field-label">Licencia</label><input type="number" class="input-pro" name="cesantia_licencia_medica[]" value="<?= $p['cesantia_licencia_medica'] ?>"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($es_pensionado): ?>
                                        <label class="field-label">Cesantía</label>
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input check-cesantia" type="checkbox" <?= $p['cotiza_cesantia_pensionado'] ? 'checked' : '' ?>>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                    <input type="hidden" class="cotiza-cesantia-hidden" name="cotiza_cesantia_pensionado_hidden[]" value="<?= $p['cotiza_cesantia_pensionado'] ?>">
                                </td>
                                <td class="text-center pe-4">
                                    <button type="button" class="btn btn-link text-danger btn-quitar-fila"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<table style="display: none;">
    <tr id="plantilla-fila-trabajador">
        <td class="ps-4">
            <input type="hidden" name="trabajador_id[]" value="{ID}">
            <input type="hidden" name="es_part_time[]" value="{ES_PART_TIME}" class="es-part-time-input">
            <div class="fw-bold text-dark">{NOMBRE}</div>
            <div class="text-muted small">{RUT}</div>
            <div class="badge-container"></div>
        </td>
        <td>
            <div class="d-flex gap-2">
                <div class="field-group flex-fill"><label class="field-label">Sueldo Base</label><input type="number" class="input-pro" name="sueldo_imponible[]" value="0"></div>
                <div class="field-group flex-fill"><label class="field-label">Bonos</label><input type="number" class="input-pro" name="bonos_imponibles[]" value="0"></div>
            </div>
        </td>
        <td class="text-center">
            <div class="field-group"><label class="field-label">Días</label><input type="number" class="input-pro text-center dias-trabajados" name="dias_trabajados[]" value="30"></div>
        </td>
        <td>
            <div class="d-flex gap-1 mb-2">
                <div class="field-group flex-fill"><label class="field-label">Tipo</label><select class="input-pro tipo-contrato" name="tipo_contrato[]">
                        <option value="Indefinido">Indefinido</option>
                        <option value="Fijo">Fijo</option>
                    </select></div>
                <div class="field-group flex-fill"><label class="field-label">Inicio</label><input type="date" class="input-pro" name="fecha_inicio[]" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <div class="field-group col-fecha-termino"><label class="field-label">Término</label><input type="date" class="input-pro fecha-termino" name="fecha_termino[]" value=""></div>
        </td>
        <td>
            <div class="d-flex gap-1">
                <div class="field-group"><label class="field-label">Aportes</label><input type="number" class="input-pro" name="aportes[]" value="0"></div>
                <div class="field-group"><label class="field-label">Salud Adic</label><input type="number" class="input-pro" name="adicional_salud_apv[]" value="0"></div>
                <div class="field-group"><label class="field-label">Licencia</label><input type="number" class="input-pro" name="cesantia_licencia_medica[]" value="0"></div>
            </div>
        </td>
        <td class="text-center">
            <div class="toggle-container"><span class="text-muted small">N/A</span></div>
            <input type="hidden" class="cotiza-cesantia-hidden" name="cotiza_cesantia_pensionado_hidden[]" value="0">
        </td>
        <td class="text-center pe-4"><button type="button" class="btn btn-link text-danger btn-quitar-fila"><i class="fas fa-trash-alt"></i></button></td>
    </tr>
</table>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    const GUARDAR_PLANILLA_URL = '<?php echo BASE_URL; ?>/ajax/guardar_planilla.php';

    // SINCRONIZACIÓN DE CHECKBOX Y HIDDEN (Crucial para que funcione el reporte)
    $(document).on('change', '.check-cesantia', function() {
        const isChecked = $(this).is(':checked') ? 1 : 0;
        $(this).closest('td').find('.cotiza-cesantia-hidden').val(isChecked);
    });

    // Lógica dinámica al agregar trabajador
    $(document).on('click', '#btn-agregar-trabajador', function() {
        const sel = $('#select-agregar-trabajador option:selected');
        const estado = sel.data('estado') || '';
        setTimeout(() => {
            const row = $('#grid-body tr').last();
            if (estado.toLowerCase() === 'pensionado') {
                row.find('.badge-container').html('<span class="badge-pensionado">PENSIONADO</span>');
                row.find('.toggle-container').html('<label class="field-label">Cesantía</label><div class="form-check form-switch d-inline-block"><input class="form-check-input check-cesantia" type="checkbox"></div>');
            }
        }, 50);
    });
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/planilla_grid.js"></script>