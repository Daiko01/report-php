<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Validar POST y obtener datos
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['empleador_id']) || !isset($_POST['mes']) || !isset($_POST['ano'])) {
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}
$empleador_id = (int)$_POST['empleador_id'];
$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];

try {
    // 3. Requisito: Verificar si el período está cerrado
    $stmt = $pdo->prepare("SELECT esta_cerrado FROM cierres_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
    $stmt->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
    $cierre = $stmt->fetch();

    if ($cierre && $cierre['esta_cerrado']) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "El período $mes/$ano para este empleador ya está CERRADO. No se pueden cargar datos."
        ];
        header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
        exit;
    }

    // 4. Obtener datos del Empleador (para mostrar) y Trabajadores (para el <select>)
    $stmt_e = $pdo->prepare("SELECT nombre, rut FROM empleadores WHERE id = ?");
    $stmt_e->execute([$empleador_id]);
    $empleador = $stmt_e->fetch();

    // 5. Obtener TODOS los trabajadores
    $trabajadores = $pdo->query("SELECT id, nombre, rut, estado_previsional FROM trabajadores ORDER BY nombre")->fetchAll();

    // 6. Obtener datos de planilla YA GUARDADOS (si existen)
    $sql_planilla = "SELECT p.*, t.nombre as nombre_trabajador, t.rut as rut_trabajador, t.estado_previsional
                     FROM planillas_mensuales p
                     JOIN trabajadores t ON p.trabajador_id = t.id
                     WHERE p.empleador_id = :eid AND p.mes = :mes AND p.ano = :ano";
    $stmt_p = $pdo->prepare($sql_planilla);
    $stmt_p->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
    $planilla_guardada = $stmt_p->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// 7. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Planilla Período <?php echo "$mes / $ano"; ?></h1>
            <h5 class="text-muted"><?php echo htmlspecialchars($empleador['nombre']) . " (" . $empleador['rut'] . ")"; ?></h5>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <a href="cargar_selector.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Cambiar Período
            </a>
            <button class="btn btn-success btn-lg shadow-sm" id="btn-guardar-planilla">
                <i class="fas fa-save me-2"></i> Guardar Planilla
            </button>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-10">
                    <label for="select-agregar-trabajador" class="form-label">Agregar Trabajador a la Grilla</label>
                    <select class="form-select" id="select-agregar-trabajador" data-placeholder="Buscar trabajador por RUT o Nombre...">
                        <option></option> <?php foreach ($trabajadores as $t): ?>
                            <option value="<?php echo $t['id']; ?>"
                                data-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
                                data-rut="<?php echo htmlspecialchars($t['rut']); ?>"
                                data-estado="<?php echo htmlspecialchars($t['estado_previsional']); ?>">
                                <?php echo htmlspecialchars($t['nombre']) . " (" . $t['rut'] . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" id="btn-agregar-trabajador">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <form id="form-planilla">
        <input type="hidden" id="empleador_id" value="<?php echo $empleador_id; ?>">
        <input type="hidden" id="mes" value="<?php echo $mes; ?>">
        <input type="hidden" id="ano" value="<?php echo $ano; ?>">

        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="grid-planilla" style="min-width: 1500px;">
                        <thead>
                            <tr class="table-dark">
                                <th>Trabajador</th>
                                <th>Sueldo Imponible</th>
                                <th>Días Trab.</th>
                                <th>Tipo Contrato</th>
                                <th>Fecha Inicio</th>
                                <th class="col-fecha-termino">Fecha Término</th>
                                <th>Aportes</th>
                                <th>Salud Adic/APV</th>
                                <th>Cesantía (Lic. Médica)</th>
                                <th class="col-cotiza-cesantia">Cotiza Cesantía (Pensionado)</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="grid-body">
                            <?php foreach ($planilla_guardada as $p): ?>
                                <tr class="fila-trabajador"
                                    data-id-trabajador="<?php echo $p['trabajador_id']; ?>"
                                    data-estado-previsional="<?php echo $p['estado_previsional']; ?>">

                                    <td> <input type="hidden" name="trabajador_id[]" value="<?php echo $p['trabajador_id']; ?>">
                                        <?php echo htmlspecialchars($p['nombre_trabajador']); ?><br>
                                        <small><?php echo htmlspecialchars($p['rut_trabajador']); ?></small>
                                    </td>
                                    <td><input type="number" class="form-control" name="sueldo_imponible[]" value="<?php echo $p['sueldo_imponible']; ?>"></td>
                                    <td><input type="number" class="form-control dias-trabajados" name="dias_trabajados[]" value="<?php echo $p['dias_trabajados']; ?>" max="30" min="0"></td>
                                    <td> <select class="form-select tipo-contrato" name="tipo_contrato[]">
                                            <option value="Indefinido" <?php echo $p['tipo_contrato'] == 'Indefinido' ? 'selected' : ''; ?>>Indefinido</option>
                                            <option value="Fijo" <?php echo $p['tipo_contrato'] == 'Fijo' ? 'selected' : ''; ?>>Fijo</option>
                                        </select>
                                    </td>
                                    <td><input type="date" class="form-control" name="fecha_inicio[]" value="<?php echo $p['fecha_inicio']; ?>"></td>
                                    <td class="col-fecha-termino">
                                        <input type="date" class="form-control fecha-termino" name="fecha_termino[]" value="<?php echo $p['fecha_termino']; ?>">
                                    </td>

                                    <td><input type="number" class="form-control" name="aportes[]" value="<?php echo $p['aportes']; ?>"></td>
                                    <td><input type="number" class="form-control" name="adicional_salud_apv[]" value="<?php echo $p['adicional_salud_apv']; ?>"></td>
                                    <td><input type="number" class="form-control" name="cesantia_licencia_medica[]" value="<?php echo $p['cesantia_licencia_medica']; ?>"></td>
                                    <td class="col-cotiza-cesantia">
                                        <div class="toggle-wrapper">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="cotiza_cesantia_pensionado[]" value="1" <?php echo $p['cotiza_cesantia_pensionado'] ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                        <input type="hidden" class="cotiza-cesantia-hidden" name="cotiza_cesantia_pensionado_hidden[]" value="<?php echo $p['cotiza_cesantia_pensionado']; ?>">
                                    </td>

                                    <td><button type="button" class="btn btn-danger btn-sm btn-quitar-fila"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<table style="display: none;">
    <tr id="plantilla-fila-trabajador">
        <td><input type="hidden" name="trabajador_id[]" value="{ID}">{NOMBRE}<br><small>{RUT}</small></td>
        <td><input type="number" class="form-control" name="sueldo_imponible[]" value="0"></td>
        <td><input type="number" class="form-control dias-trabajados" name="dias_trabajados[]" value="30" max="30" min="0"></td>
        <td> <select class="form-select tipo-contrato" name="tipo_contrato[]">
                <option value="Indefinido" selected>Indefinido</option>
                <option value="Fijo">Fijo</option>
            </select>
        </td>
        <td><input type="date" class="form-control" name="fecha_inicio[]" value="<?php echo date('Y-m-d'); ?>"></td>
        <td class="col-fecha-termino">
            <input type="date" class="form-control fecha-termino" name="fecha_termino[]" value="">
        </td>

        <td><input type="number" class="form-control" name="aportes[]" value="0"></td>
        <td><input type="number" class="form-control" name="adicional_salud_apv[]" value="0"></td>
        <td><input type="number" class="form-control" name="cesantia_licencia_medica[]" value="0"></td>
        <td class="col-cotiza-cesantia">
            <div class="toggle-wrapper">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="cotiza_cesantia_pensionado[]" value="1">
                </div>
            </div>
            <input type="hidden" class="cotiza-cesantia-hidden" name="cotiza_cesantia_pensionado_hidden[]" value="0">
        </td>

        <td><button type="button" class="btn btn-danger btn-sm btn-quitar-fila"><i class="fas fa-trash"></i></button></td>
    </tr>
</table>


<?php
// 8. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>
<script>
    // Pasamos la URL de AJAX al script JS
    const GUARDAR_PLANILLA_URL = '<?php echo BASE_URL; ?>/ajax/guardar_planilla.php';
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/planilla_grid.js"></script>