<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Obtener AFPs y Sindicatos para los <select>
try {
    $afps = $pdo->query("SELECT id, nombre FROM afps ORDER BY nombre")->fetchAll();
    $sindicatos = $pdo->query("SELECT id, nombre FROM sindicatos ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
    $afps = [];
    $sindicatos = [];
}

// 3. Cargar el Header
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
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="rut" class="form-label">RUT</label>
                        <input type="text" class="form-control rut-input" id="rut" name="rut" maxlength="12" required>
                        <div class="invalid-feedback">El RUT es obligatorio y debe ser válido.</div>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Información Previsional</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="estado_previsional" class="form-label">Estado Previsional</label>
                        <select class="form-select" id="estado_previsional" name="estado_previsional" required>
                            <option value="Activo" selected>Activo</option>
                            <option value="Pensionado">Pensionado</option>
                        </select>
                    </div>

                    <div class="col-md-8 mb-3" id="afp_wrapper">
                        <label for="afp_id" class="form-label">AFP</label>
                        <select class="form-select" id="afp_id" name="afp_id">
                            <option value="" selected>Seleccione...</option>
                            <?php foreach ($afps as $afp): ?>
                                <option value="<?php echo $afp['id']; ?>"><?php echo htmlspecialchars($afp['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">La AFP es obligatoria si el trabajador está Activo.</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sindicato_id" class="form-label">Sindicato (Opcional)</label>
                        <select class="form-select" id="sindicato_id" name="sindicato_id">
                            <option value="" selected>Ninguno</option>
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

<?php
// 4. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
$(document).ready(function() {
    
    // --- Lógica Condicional (Requisitos) ---
    var $estadoPrevisional = $('#estado_previsional');
    var $afpWrapper = $('#afp_wrapper');
    var $afpSelect = $('#afp_id');
    
    var $tieneCargas = $('#tiene_cargas');
    var $numeroCargasWrapper = $('#numero_cargas_wrapper');
    var $numeroCargasInput = $('#numero_cargas');

    function actualizarCamposCondicionales() {
        
        // 1. Lógica de AFP (Requisito)
        if ($estadoPrevisional.val() === 'Pensionado') {
            $afpWrapper.hide();
            $afpSelect.prop('disabled', true).prop('required', false); // Deshabilitar y quitar 'required'
            $afpSelect.val(null).trigger('change'); // Limpiar Select2
        } else {
            // Es 'Activo'
            $afpWrapper.show();
            $afpSelect.prop('disabled', false).prop('required', true); // Habilitar y hacer 'required'
        }

        // 2. Lógica de Cargas (Requisito)
        if ($tieneCargas.is(':checked')) {
            $numeroCargasWrapper.show();
            $numeroCargasInput.prop('disabled', false).prop('min', '1'); // Mínimo 1 si está chequeado
            if ($numeroCargasInput.val() == '0') {
                 $numeroCargasInput.val('1'); // Poner 1 por defecto
            }
        } else {
            // No tiene cargas
            $numeroCargasWrapper.hide();
            $numeroCargasInput.prop('disabled', true).val('0'); // Deshabilitar y setear a 0
        }
    }

    // --- Listeners de Eventos ---
    $estadoPrevisional.on('change', actualizarCamposCondicionales);
    $tieneCargas.on('change', actualizarCamposCondicionales);

    // --- Ejecutar al cargar la página ---
    actualizarCamposCondicionales();

    // --- Validación Bootstrap ---
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
          form.addEventListener('submit', function (event) {
            // Forzar validación de Select2
            $('.form-select[required]').each(function() {
                if ($(this).val() === null || $(this).val() === "") {
                    $(this).closest('.mb-3').find('.invalid-feedback').show();
                    event.preventDefault();
                    event.stopPropagation();
                }
            });

            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })()
});
</script>