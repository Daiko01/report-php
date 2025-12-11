<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

try {
    // 1. Cargar Listas Maestras
    $afps = $pdo->query("SELECT id, nombre FROM afps ORDER BY nombre")->fetchAll();
    $sindicatos = $pdo->query("SELECT id, nombre FROM sindicatos ORDER BY nombre")->fetchAll();

    // 2. Cargar Tramos de Asignación Familiar VIGENTES
    // Buscamos la fecha de vigencia más reciente para no traer tramos viejos
    $sql_tramos = "SELECT tramo, monto_por_carga 
                   FROM cargas_tramos_historicos 
                   WHERE fecha_inicio = (SELECT MAX(fecha_inicio) FROM cargas_tramos_historicos)
                   ORDER BY tramo ASC";
    $tramos_bd = $pdo->query($sql_tramos)->fetchAll();

} catch (PDOException $e) { 
    $afps = []; $sindicatos = []; $tramos_bd = [];
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
                
                <h5 class="mb-3 text-primary">Información Personal</h5>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="rut" class="form-label">RUT</label>
                        <input type="text" class="form-control rut-input" id="rut" name="rut" maxlength="12" required>
                        <div class="invalid-feedback">El RUT es obligatorio.</div>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3 text-primary">Información Previsional</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="estado_previsional" class="form-label">Estado</label>
                        <select class="form-select" id="estado_previsional" name="estado_previsional" required>
                            <option value="Activo" selected>Activo</option>
                            <option value="Pensionado">Pensionado</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="sistema_previsional" class="form-label">Sistema Previsional</label>
                        <select class="form-select" id="sistema_previsional" name="sistema_previsional" required>
                            <option value="AFP" selected>AFP</option>
                            <option value="INP">INP (Ex-Caja)</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3" id="afp_wrapper">
                        <label for="afp_id" class="form-label">Seleccionar AFP</label>
                        <select class="form-select" id="afp_id" name="afp_id">
                            <option value="" selected>Seleccione...</option>
                            <?php foreach ($afps as $afp): ?>
                                <option value="<?php echo $afp['id']; ?>"><?php echo htmlspecialchars($afp['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione una AFP.</div>
                    </div>

                    <div class="col-md-3 mb-3" id="inp_wrapper" style="display: none;">
                        <label for="tasa_inp" class="form-label">Tasa INP (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="tasa_inp" name="tasa_inp" placeholder="Ej: 18.84">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="invalid-feedback">Ingrese la tasa INP.</div>
                    </div>
                </div>
                
                <div class="row align-items-start">
                    <div class="col-md-3 mb-3">
                        <label for="sindicato_id" class="form-label">Sindicato (Opcional)</label>
                        <select class="form-select" id="sindicato_id" name="sindicato_id">
                            <option value="">Ninguno</option>
                             <?php foreach ($sindicatos as $sindicato): ?>
                                <option value="<?php echo $sindicato['id']; ?>"><?php echo htmlspecialchars($sindicato['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3 d-flex align-items-center" style="height: 70px;">
                        <div class="form-check form-switch form-check-lg">
                            <input class="form-check-input" type="checkbox" id="tiene_cargas" name="tiene_cargas" value="1">
                            <label class="form-check-label fw-bold" for="tiene_cargas">¿Tiene Cargas?</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3" id="cargas_details_wrapper" style="display: none;">
                        <div class="card bg-light border-0">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="numero_cargas" class="form-label">N° Cargas</label>
                                        <input type="number" class="form-control" id="numero_cargas" name="numero_cargas" value="0" min="0">
                                    </div>
                                    <div class="col-md-8">
                                        <label for="tramo_manual" class="form-label">Tramo Asig. Familiar</label>
                                        <select class="form-select" name="tramo_manual" id="tramo_manual">
                                            <option value="" selected class="fw-bold text-primary">Automático (Según Sueldo)</option>
                                            <option disabled>──────────────</option>
                                            
                                            <?php foreach ($tramos_bd as $t): ?>
                                                <option value="<?php echo $t['tramo']; ?>">
                                                    Tramo <?php echo $t['tramo']; ?> ($<?php echo number_format($t['monto_por_carga'], 0, ',', '.'); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text small">Seleccione solo si desea anular el cálculo automático (ej: Pensionados).</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end">
                    <a href="gestionar_trabajadores.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Trabajador</button>
                </div>
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

    // Lógica AFP vs INP vs Pensionado
    function toggleSistema() {
        // 1. Si es Pensionado: Deshabilitar selectores de previsión
        if ($estadoPrevisional.val() === 'Pensionado') {
            $afpSelect.prop('disabled', true).prop('required', false);
            $inpInput.prop('disabled', true).prop('required', false);
            return; // Salir, no importa si es AFP o INP
        }

        // 2. Si es Activo: Habilitar y mostrar según selección
        $afpSelect.prop('disabled', false);
        $inpInput.prop('disabled', false);

        if ($sistemaSelect.val() === 'INP') {
            $afpWrapper.hide();
            $inpWrapper.show();
            $afpSelect.prop('required', false).val(''); // Limpiar AFP
            $inpInput.prop('required', true);
        } else {
            $afpWrapper.show();
            $inpWrapper.hide();
            $afpSelect.prop('required', true);
            $inpInput.prop('required', false).val(''); // Limpiar Tasa
        }
    }

    // Lógica Cargas Familiares
    function toggleCargas() {
        if ($tieneCargas.is(':checked')) {
            // Mostrar bloque completo
            $cargasWrapper.slideDown(); // Animación suave
            
            // Habilitar campos
            $numeroCargasInput.prop('disabled', false).prop('min', '1');
            $tramoSelect.prop('disabled', false);
            
            // Si estaba en 0, poner 1 para ayudar al usuario
            if ($numeroCargasInput.val() == '0') $numeroCargasInput.val('1');
        } else {
            // Ocultar bloque
            $cargasWrapper.slideUp();
            
            // Deshabilitar y limpiar
            $numeroCargasInput.prop('disabled', true).val('0');
            $tramoSelect.prop('disabled', true).val(''); // Volver a automático
        }
    }

    // Listeners
    $sistemaSelect.on('change', toggleSistema);
    $estadoPrevisional.on('change', toggleSistema);
    $tieneCargas.on('change', toggleCargas);

    // Ejecutar al inicio (por si el navegador guarda el estado al recargar)
    toggleSistema();
    toggleCargas();

    // Validación Bootstrap estándar
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms).forEach(function (form) {
          form.addEventListener('submit', function (event) {
            // Validación visual extra para Select2 (si usas)
            if ($afpWrapper.is(':visible') && !$afpSelect.val()) {
                 $afpSelect.addClass('is-invalid');
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