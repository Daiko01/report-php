<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) {
    header('Location: gestionar_trabajadores.php');
    exit;
}
$id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM trabajadores WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $trabajador = $stmt->fetch();
    if (!$trabajador) {
        header('Location: gestionar_trabajadores.php');
        exit;
    }

    $afps = $pdo->query("SELECT id, nombre FROM afps ORDER BY nombre")->fetchAll();
    $sindicatos = $pdo->query("SELECT id, nombre, descuento FROM sindicatos ORDER BY nombre")->fetchAll();

    // CARGAR TRAMOS PARA EL SELECTOR
    $sql_tramos = "SELECT tramo, monto_por_carga 
                   FROM cargas_tramos_historicos 
                   WHERE fecha_inicio = (SELECT MAX(fecha_inicio) FROM cargas_tramos_historicos)
                   ORDER BY tramo ASC";
    $tramos_bd = $pdo->query($sql_tramos)->fetchAll();
} catch (PDOException $e) {
    die("Error de conexión.");
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Trabajador</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Editando: <?php echo htmlspecialchars($trabajador['nombre']); ?></h6>
        </div>
        <div class="card-body">
            <form action="editar_trabajador_process.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $trabajador['id']; ?>">

                <h5 class="mb-3 text-primary">Información Personal</h5>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($trabajador['nombre']); ?>" readonly disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">RUT</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($trabajador['rut']); ?>" readonly disabled>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3 text-primary">Régimen Contractual</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label d-block text-gray-800 fw-bold">Tipo de Contratación</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="es_excedente" id="regimen_normal" value="0" <?php echo ($trabajador['es_excedente'] == 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="regimen_normal">Normal (Con Leyes Sociales)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="es_excedente" id="regimen_exento" value="1" <?php echo ($trabajador['es_excedente'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label text-danger fw-bold" for="regimen_exento">Exento / Excedente</label>
                        </div>
                        <div class="form-text small mt-2">
                            <i class="fas fa-info-circle"></i> Los trabajadores <strong>Exentos</strong> registran producción pero <strong>NO generan leyes sociales</strong>. Sus aportes van a "Excedentes".
                        </div>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3 text-primary">Información Previsional</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="estado_previsional" class="form-label">Estado</label>
                        <select class="form-select" id="estado_previsional" name="estado_previsional" required>
                            <option value="Activo" <?php echo ($trabajador['estado_previsional'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Pensionado" <?php echo ($trabajador['estado_previsional'] == 'Pensionado') ? 'selected' : ''; ?>>Pensionado</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="sistema_previsional" class="form-label">Sistema Previsional</label>
                        <select class="form-select" id="sistema_previsional" name="sistema_previsional" required>
                            <option value="AFP" <?php echo ($trabajador['sistema_previsional'] == 'AFP') ? 'selected' : ''; ?>>AFP</option>
                            <option value="INP" <?php echo ($trabajador['sistema_previsional'] == 'INP') ? 'selected' : ''; ?>>INP (Ex-Caja)</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3" id="afp_wrapper">
                        <label for="afp_id" class="form-label">AFP</label>
                        <select class="form-select" id="afp_id" name="afp_id">
                            <option value="">Seleccione...</option>
                            <?php foreach ($afps as $afp): ?>
                                <option value="<?php echo $afp['id']; ?>" <?php echo ($afp['id'] == $trabajador['afp_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($afp['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3" id="inp_wrapper" style="display: none;">
                        <label for="tasa_inp" class="form-label">Tasa INP (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="tasa_inp" name="tasa_inp" value="<?php echo ($trabajador['tasa_inp_decimal'] * 100); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sindicato_id" class="form-label">Sindicato</label>
                        <select class="form-select" id="sindicato_id" name="sindicato_id">
                            <option value="">Ninguno</option>
                            <?php foreach ($sindicatos as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $trabajador['sindicato_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['nombre']) . ' ($ ' . number_format($s['descuento'], 0, ',', '.') . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch form-check-lg mt-4">
                            <input class="form-check-input" type="checkbox" id="tiene_cargas" name="tiene_cargas" value="1" <?php echo $trabajador['tiene_cargas'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tiene_cargas">¿Tiene Cargas?</label>
                        </div>
                    </div>

                    <div class="col-md-5 mb-3" id="cargas_details_wrapper" style="display: none;">
                        <div class="row">
                            <div class="col-5">
                                <label for="numero_cargas" class="form-label">N° Cargas</label>
                                <input type="number" class="form-control" id="numero_cargas" name="numero_cargas" value="<?php echo $trabajador['numero_cargas']; ?>" min="0">
                            </div>
                            <div class="col-7">
                                <label for="tramo_manual" class="form-label">Tramo (Opcional)</label>
                                <select class="form-select" name="tramo_manual" id="tramo_manual">
                                    <option value="" <?php echo is_null($trabajador['tramo_asignacion_manual']) ? 'selected' : ''; ?>>Automático</option>

                                    <?php foreach ($tramos_bd as $t): ?>
                                        <option value="<?php echo $t['tramo']; ?>" <?php echo ($trabajador['tramo_asignacion_manual'] == $t['tramo']) ? 'selected' : ''; ?>>
                                            Tramo <?php echo $t['tramo']; ?> ($<?php echo number_format($t['monto_por_carga'], 0, ',', '.'); ?>)
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>
                <a href="gestionar_trabajadores.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        const $sistemaSelect = $('#sistema_previsional');
        const $afpWrapper = $('#afp_wrapper');
        const $inpWrapper = $('#inp_wrapper');
        const $afpSelect = $('#afp_id');
        const $inpInput = $('#tasa_inp');
        const $estadoPrevisional = $('#estado_previsional');
        const $tieneCargas = $('#tiene_cargas');
        const $cargasWrapper = $('#cargas_details_wrapper');
        const $numeroCargasInput = $('#numero_cargas');
        const $tramoSelect = $('#tramo_manual');

        // Lógica AFP vs INP vs Pensionado vs Exento
        function toggleSistema() {
            // 0. Si es EXENTO: Bloquear todo
            if ($('#regimen_exento').is(':checked')) {
                $afpSelect.prop('disabled', true).prop('required', false).val('');
                $inpInput.prop('disabled', true).prop('required', false).val('');
                $sistemaSelect.prop('disabled', true);
                $estadoPrevisional.prop('disabled', true);

                // Disable Sindicato
                $('#sindicato_id').prop('disabled', true).val('');

                // Opcional: También deshabilitar Cargas
                $tieneCargas.prop('checked', false).prop('disabled', true);
                toggleCargas(); // Actualizar estado visual de cargas
                return;
            } else {
                // Reactivar si vuelve a Normal
                $sistemaSelect.prop('disabled', false);
                $estadoPrevisional.prop('disabled', false);
                $tieneCargas.prop('disabled', false);
                $('#sindicato_id').prop('disabled', false);
            }

            // 1. Si es Pensionado: Deshabilitar selectores de previsión
            if ($estadoPrevisional.val() === 'Pensionado') {
                $afpSelect.prop('disabled', true).prop('required', false);
                $inpInput.prop('disabled', true).prop('required', false);
                return;
            }
            $afpSelect.prop('disabled', false);
            $inpInput.prop('disabled', false);

            if ($sistemaSelect.val() === 'INP') {
                $afpWrapper.hide();
                $inpWrapper.show();
                $afpSelect.prop('required', false);
                $inpInput.prop('required', true);
            } else {
                $afpWrapper.show();
                $inpWrapper.hide();
                $afpSelect.prop('required', true);
                $inpInput.prop('required', false);
            }
        }

        function toggleCargas() {
            if ($tieneCargas.is(':checked')) {
                $cargasWrapper.fadeIn();
                $numeroCargasInput.prop('disabled', false);
                $tramoSelect.prop('disabled', false);
            } else {
                $cargasWrapper.hide();
                $numeroCargasInput.prop('disabled', true);
                $tramoSelect.prop('disabled', true);
            }
        }

        $('input[name="es_excedente"]').on('change', toggleSistema);
        $sistemaSelect.on('change', toggleSistema);
        $estadoPrevisional.on('change', toggleSistema);
        $tieneCargas.on('change', toggleCargas);

        toggleSistema();
        toggleCargas();

        // Validación Bootstrap
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if ($afpWrapper.is(':visible') && !$afpSelect.prop('disabled') && !$afpSelect.val()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    });
</script>