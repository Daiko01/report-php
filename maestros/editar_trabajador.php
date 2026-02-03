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

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-900 fw-bold">Editar Trabajador</h1>
            <p class="mb-0 text-muted small">Modificando ficha de: <strong><?php echo htmlspecialchars($trabajador['nombre']); ?></strong></p>
        </div>
        <a href="gestionar_trabajadores.php" class="btn btn-light btn-sm text-secondary border">
            <i class="fas fa-times me-1"></i> Cancelar
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-10">
            <form action="editar_trabajador_process.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $trabajador['id']; ?>">

                <!-- Main Card (Minimalist) -->
                <div class="card shadow-sm border-0 mb-5" style="border-radius: 12px; background: #fff;">
                    <div class="card-body p-4 p-md-5">

                        <!-- 1. Datos Personales -->
                        <div class="mb-5">
                            <h6 class="text-uppercase text-xs font-weight-bold text-gray-500 mb-3 tracking-wide">
                                Información Personal
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-8">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Nombre Completo</label>
                                    <input type="text" class="form-control form-control-lg bg-light border-0" value="<?php echo htmlspecialchars($trabajador['nombre']); ?>" readonly disabled style="border-radius: 8px; opacity: 0.7;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">RUT</label>
                                    <input type="text" class="form-control form-control-lg bg-light border-0" value="<?php echo htmlspecialchars($trabajador['rut']); ?>" readonly disabled style="border-radius: 8px; opacity: 0.7;">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Régimen -->
                        <div class="mb-5">
                            <h6 class="text-uppercase text-xs font-weight-bold text-gray-500 mb-3 tracking-wide">
                                Régimen Contractual
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="es_excedente" id="regimen_normal" value="0" <?php echo ($trabajador['es_excedente'] == 0) ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light text-dark d-flex align-items-center p-3 w-100 border text-start" for="regimen_normal" style="border-radius: 10px;">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <i class="fas fa-check fa-xs"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold small">Contrato Normal</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Con leyes sociales (AFP/Salud).</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="es_excedente" id="regimen_exento" value="1" <?php echo ($trabajador['es_excedente'] == 1) ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light text-dark d-flex align-items-center p-3 w-100 border text-start" for="regimen_exento" style="border-radius: 10px;">
                                        <div class="bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <i class="fas fa-ban fa-xs"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold small">Exento / Excedente</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Sin leyes sociales.</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Previsión -->
                        <div class="mb-5" id="section_prevision">
                            <h6 class="text-uppercase text-xs font-weight-bold text-gray-500 mb-3 tracking-wide">
                                Previsión y Salud
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="estado_previsional" class="form-label text-muted small fw-bold text-uppercase">Estado</label>
                                    <select class="form-select bg-light border-0" id="estado_previsional" name="estado_previsional" required style="border-radius: 8px;">
                                        <option value="Activo" <?php echo ($trabajador['estado_previsional'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                        <option value="Pensionado" <?php echo ($trabajador['estado_previsional'] == 'Pensionado') ? 'selected' : ''; ?>>Pensionado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div id="sistema_wrapper">
                                        <label for="sistema_previsional" class="form-label text-muted small fw-bold text-uppercase">Sistema</label>
                                        <select class="form-select bg-light border-0" id="sistema_previsional" name="sistema_previsional" required style="border-radius: 8px;">
                                            <option value="AFP" <?php echo ($trabajador['sistema_previsional'] == 'AFP') ? 'selected' : ''; ?>>AFP</option>
                                            <option value="INP" <?php echo ($trabajador['sistema_previsional'] == 'INP') ? 'selected' : ''; ?>>INP (Ex-Caja)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4" id="afp_wrapper">
                                    <label for="afp_id" class="form-label text-muted small fw-bold text-uppercase">AFP</label>
                                    <select class="form-select bg-light border-0" id="afp_id" name="afp_id" style="border-radius: 8px;">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($afps as $afp): ?>
                                            <option value="<?php echo $afp['id']; ?>" <?php echo ($afp['id'] == $trabajador['afp_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($afp['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Seleccione AFP.</div>
                                </div>
                                <div class="col-md-3" id="inp_wrapper" style="display: none;">
                                    <label for="tasa_inp" class="form-label text-muted small fw-bold text-uppercase">Tasa INP (%)</label>
                                    <input type="number" step="0.01" class="form-control bg-light border-0" id="tasa_inp" name="tasa_inp" value="<?php echo ($trabajador['tasa_inp_decimal'] * 100); ?>" placeholder="0.00" style="border-radius: 8px;">
                                </div>
                            </div>
                        </div>

                        <!-- 4. Extras -->
                        <div class="mb-4">
                            <h6 class="text-uppercase text-xs font-weight-bold text-gray-500 mb-3 tracking-wide">
                                Extras
                            </h6>
                            <div class="row align-items-center g-3">
                                <div class="col-md-4">
                                    <label for="sindicato_id" class="form-label text-muted small fw-bold text-uppercase">Sindicato</label>
                                    <select class="form-select bg-light border-0" id="sindicato_id" name="sindicato_id" style="border-radius: 8px;">
                                        <option value="">No afiliado</option>
                                        <?php foreach ($sindicatos as $sindicato): ?>
                                            <option value="<?php echo $sindicato['id']; ?>" <?php echo ($sindicato['id'] == $trabajador['sindicato_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sindicato['nombre']) . ' ($' . number_format($sindicato['descuento'], 0, ',', '.') . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-8">
                                    <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between" style="min-height: 80px;">
                                        <div class="form-check form-switch ps-0 ms-1">
                                            <input class="form-check-input ms-0 me-3" type="checkbox" id="tiene_cargas" name="tiene_cargas" value="1" <?php echo $trabajador['tiene_cargas'] ? 'checked' : ''; ?> style="transform: scale(1.2);">
                                            <label class="form-check-label fw-bold text-dark small" for="tiene_cargas">
                                                Cargas Familiares
                                            </label>
                                        </div>

                                        <div id="cargas_simple_input" style="display: none;">
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="number" class="form-control text-center border-0 bg-white shadow-sm" id="numero_cargas" name="numero_cargas" value="<?php echo $trabajador['numero_cargas']; ?>" min="0" placeholder="#" style="width: 60px; border-radius: 6px;">
                                                <select class="form-select border-0 bg-white shadow-sm text-xs" name="tramo_manual" id="tramo_manual" style="width: 200px; border-radius: 6px;">
                                                    <option value="" <?php echo is_null($trabajador['tramo_asignacion_manual']) ? 'selected' : ''; ?>>Tramo Auto</option>
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
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="mt-5 text-end">
                            <button type="submit" class="btn btn-dark btn-lg px-5" style="border-radius: 10px; font-weight: 600;">
                                Actualizar Trabajador
                            </button>
                        </div>

                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<style>
    /* 2026 Minimalist Tweaks */
    .tracking-wide {
        letter-spacing: 0.1em;
    }

    .text-xs {
        font-size: 0.75rem;
    }

    .btn-check:checked+label {
        background-color: #f8fafc;
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 1px var(--primary-color);
    }

    .form-control:focus,
    .form-select:focus {
        background-color: #fff;
        box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
        /* Very subtle focus ring */
        border-color: #e2e8f0;
    }
</style>

<script>
    $(document).ready(function() {
        const $sistemaSelect = $('#sistema_previsional');
        const $afpWrapper = $('#afp_wrapper');
        const $inpWrapper = $('#inp_wrapper');
        const $afpSelect = $('#afp_id');
        const $inpInput = $('#tasa_inp');
        const $estadoPrevisional = $('#estado_previsional');
        const $sectionPrevision = $('#section_prevision');
        const $tieneCargas = $('#tiene_cargas');
        const $cargasInputWrapper = $('#cargas_simple_input');
        const $numeroCargasInput = $('#numero_cargas');
        const $tramoSelect = $('#tramo_manual');

        function toggleSistema() {
            // 0. Si es EXENTO: Bloquear inputs específicos, pero mantener selectores principales activos (para POST)
            if ($('#regimen_exento').is(':checked')) {
                // Visualmente "desactivar" la sección de previsión
                $sectionPrevision.css({
                    opacity: 0.5,
                    pointerEvents: 'none'
                });

                // Limpiar valores dependientes
                $afpSelect.prop('disabled', true).prop('required', false).val('');
                $inpInput.prop('disabled', true).prop('required', false).val('');
                $('#sindicato_id').prop('disabled', true).val('');

                // MANTENER ACTIVOS estos campos para que se envíen en el POST
                // El backend los necesita, aunque sean "dummy" para este caso
                $sistemaSelect.prop('disabled', false);
                $estadoPrevisional.prop('disabled', false);

                // Opcional: Resetear a valores por defecto seguros si es necesario
                // $sistemaSelect.val('AFP');
                // $estadoPrevisional.val('Activo');

                if ($tieneCargas.is(':checked')) $tieneCargas.trigger('click');
                $tieneCargas.prop('disabled', true);

                return;
            } else {
                $sectionPrevision.css({
                    opacity: 1,
                    pointerEvents: 'auto'
                });
                $sistemaSelect.prop('disabled', false);
                $estadoPrevisional.prop('disabled', false);
                $tieneCargas.prop('disabled', false);
                $('#sindicato_id').prop('disabled', false);
            }

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
                $cargasInputWrapper.stop().fadeIn(200);
                $numeroCargasInput.prop('disabled', false);
                $tramoSelect.prop('disabled', false);
            } else {
                $cargasInputWrapper.stop().fadeOut(200);
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