<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

try {
    $afps = $pdo->query("SELECT id, nombre FROM afps ORDER BY nombre")->fetchAll();
    $sindicatos = $pdo->query("SELECT id, nombre FROM sindicatos ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
    $afps = [];
    $sindicatos = [];
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Trabajador</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Datos del Trabajador</h6>
        </div>
        <div class="card-body">
            <form action="crear_trabajador_process.php" method="POST" class="needs-validation" novalidate>

                <h5 class="mb-3">Información Personal</h5>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="rut" class="form-label">RUT</label>
                        <input type="text" class="form-control rut-input" id="rut" name="rut" maxlength="12" required>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Información Previsional</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sistema_previsional" class="form-label">Sistema Previsional</label>
                        <select class="form-select" id="sistema_previsional" name="sistema_previsional" required>
                            <option value="AFP" selected>AFP</option>
                            <option value="INP">INP (Ex-Caja)</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3" id="afp_wrapper">
                        <label for="afp_id" class="form-label">AFP</label>
                        <select class="form-select" id="afp_id" name="afp_id">
                            <option value="" selected>Seleccione...</option>
                            <?php foreach ($afps as $afp): ?>
                                <option value="<?php echo $afp['id']; ?>"><?php echo htmlspecialchars($afp['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione una AFP.</div>
                    </div>

                    <div class="col-md-4 mb-3" id="inp_wrapper" style="display: none;">
                        <label for="tasa_inp" class="form-label">Tasa INP (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="tasa_inp" name="tasa_inp" placeholder="Ej: 18.84">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="invalid-feedback">Ingrese la tasa INP.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="estado_previsional" class="form-label">Estado</label>
                        <select class="form-select" id="estado_previsional" name="estado_previsional" required>
                            <option value="Activo" selected>Activo</option>
                            <option value="Pensionado">Pensionado</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sindicato_id" class="form-label">Sindicato (Opcional)</label>
                        <select class="form-select" id="sindicato_id" name="sindicato_id">
                            <option value="">Ninguno</option>
                            <?php foreach ($sindicatos as $sindicato): ?>
                                <option value="<?php echo $sindicato['id']; ?>"><?php echo htmlspecialchars($sindicato['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch form-check-lg mt-4">
                            <input class="form-check-input" type="checkbox" id="tiene_cargas" name="tiene_cargas" value="1">
                            <label class="form-check-label" for="tiene_cargas">¿Tiene Cargas Familiares?</label>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3" id="numero_cargas_wrapper" style="display: none;">
                        <label for="numero_cargas" class="form-label">N° de Cargas</label>
                        <input type="number" class="form-control" id="numero_cargas" name="numero_cargas" value="0" min="0">
                    </div>
                </div>

                <hr>
                <a href="gestionar_trabajadores.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Trabajador</button>
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
        const $numeroCargasWrapper = $('#numero_cargas_wrapper');
        const $numeroCargasInput = $('#numero_cargas');

        function toggleSistema() {
            if ($sistemaSelect.val() === 'INP') {
                $afpWrapper.hide();
                $inpWrapper.show();
                $afpSelect.prop('required', false).val(null).trigger('change');
                $inpInput.prop('required', true);
            } else {
                $afpWrapper.show();
                $inpWrapper.hide();
                $afpSelect.prop('required', true);
                $inpInput.prop('required', false).val('');
            }
        }

        function toggleCargas() {
            if ($tieneCargas.is(':checked')) {
                $numeroCargasWrapper.show();
                $numeroCargasInput.prop('disabled', false).prop('min', '1');
                if ($numeroCargasInput.val() == '0') $numeroCargasInput.val('1');
            } else {
                $numeroCargasWrapper.hide();
                $numeroCargasInput.prop('disabled', true).val('0');
            }
        }

        // Lógica de pensionado: Si es pensionado, desactivar AFP/INP
        function togglePensionado() {
            if ($estadoPrevisional.val() === 'Pensionado') {
                $afpSelect.prop('disabled', true).prop('required', false);
                $inpInput.prop('disabled', true).prop('required', false);
            } else {
                $afpSelect.prop('disabled', false).prop('required', ($sistemaSelect.val() === 'AFP'));
                $inpInput.prop('disabled', false).prop('required', ($sistemaSelect.val() === 'INP'));
            }
        }

        $sistemaSelect.on('change', toggleSistema);
        $estadoPrevisional.on('change', togglePensionado);
        $tieneCargas.on('change', toggleCargas);

        toggleSistema();
        togglePensionado();
        toggleCargas();

        // Validación Bootstrap
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
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