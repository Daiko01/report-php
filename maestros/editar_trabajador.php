<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Validar ID
if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
    exit;
}
$id = $_GET['id'];

try {
    // 3. Obtener datos del trabajador
    $stmt = $pdo->prepare("SELECT * FROM trabajadores WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $trabajador = $stmt->fetch();

    if (!$trabajador) {
        header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
        exit;
    }

    // 4. Obtener AFPs y Sindicatos
    $afps = $pdo->query("SELECT id, nombre FROM afps ORDER BY nombre")->fetchAll();
    $sindicatos = $pdo->query("SELECT id, nombre FROM sindicatos ORDER BY nombre")->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar datos.");
}

// 5. Cargar Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Trabajador</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($trabajador['nombre']); ?></h6>
        </div>
        <div class="card-body">
            
            <form action="editar_trabajador_process.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $trabajador['id']; ?>">

                <h5 class="mb-3">Información Personal</h5>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($trabajador['nombre']); ?>" readonly disabled>
                        <div class="form-text">El nombre no se puede modificar.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="rut" class="form-label">RUT</label>
                        <input type="text" class="form-control" id="rut" name="rut" value="<?php echo htmlspecialchars($trabajador['rut']); ?>" readonly disabled>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Información Previsional</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="estado_previsional" class="form-label">Estado Previsional</label>
                        <select class="form-select" id="estado_previsional" name="estado_previsional" required>
                            <option value="Activo" <?php echo ($trabajador['estado_previsional'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Pensionado" <?php echo ($trabajador['estado_previsional'] == 'Pensionado') ? 'selected' : ''; ?>>Pensionado</option>
                        </select>
                    </div>

                    <div class="col-md-8 mb-3" id="afp_wrapper">
                        <label for="afp_id" class="form-label">AFP</label>
                        <select class="form-select" id="afp_id" name="afp_id">
                            <option value="">Seleccione...</option>
                            <?php foreach ($afps as $afp): ?>
                                <option value="<?php echo $afp['id']; ?>" <?php echo ($afp['id'] == $trabajador['afp_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($afp['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">La AFP es obligatoria si el trabajador está Activo.</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sindicato_id" class="form-label">Sindicato (Opcional)</label>
                        <select class="form-select" id="sindicato_id" name="sindicato_id">
                            <option value="">Ninguno</option>
                             <?php foreach ($sindicatos as $sindicato): ?>
                                <option value="<?php echo $sindicato['id']; ?>" <?php echo ($sindicato['id'] == $trabajador['sindicato_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sindicato['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch form-check-lg mt-4">
                            <input class="form-check-input" type="checkbox" id="tiene_cargas" name="tiene_cargas" value="1" <?php echo $trabajador['tiene_cargas'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tiene_cargas">¿Tiene Cargas Familiares?</label>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3" id="numero_cargas_wrapper" style="display: none;">
                        <label for="numero_cargas" class="form-label">N° de Cargas</label>
                        <input type="number" class="form-control" id="numero_cargas" name="numero_cargas" value="<?php echo $trabajador['numero_cargas']; ?>" min="0">
                    </div>
                </div>

                <hr>
                <a href="gestionar_trabajadores.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Trabajador</button>
            </form>

        </div>
    </div>
</div>

<?php
// 6. Cargar Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
$(document).ready(function() {
    
    var $estadoPrevisional = $('#estado_previsional');
    var $afpWrapper = $('#afp_wrapper');
    var $afpSelect = $('#afp_id');
    var $tieneCargas = $('#tiene_cargas');
    var $numeroCargasWrapper = $('#numero_cargas_wrapper');
    var $numeroCargasInput = $('#numero_cargas');

    function actualizarCamposCondicionales() {
        if ($estadoPrevisional.val() === 'Pensionado') {
            $afpWrapper.hide();
            $afpSelect.prop('disabled', true).prop('required', false);
            $afpSelect.val(null).trigger('change');
        } else {
            $afpWrapper.show();
            $afpSelect.prop('disabled', false).prop('required', true);
        }

        if ($tieneCargas.is(':checked')) {
            $numeroCargasWrapper.show();
            $numeroCargasInput.prop('disabled', false).prop('min', '1');
            if ($numeroCargasInput.val() == '0') {
                 $numeroCargasInput.val('1');
            }
        } else {
            $numeroCargasWrapper.hide();
            $numeroCargasInput.prop('disabled', true).val('0');
        }
    }

    $estadoPrevisional.on('change', actualizarCamposCondicionales);
    $tieneCargas.on('change', actualizarCamposCondicionales);
    actualizarCamposCondicionales(); // Ejecutar al cargar

    // Validación Bootstrap
    (function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation'); Array.prototype.slice.call(forms).forEach(function (form) { form.addEventListener('submit', function (event) { $('.form-select[required]').each(function() { if ($(this).val() === null || $(this).val() === "") { $(this).closest('.mb-3').find('.invalid-feedback').show(); event.preventDefault(); event.stopPropagation(); } }); if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); }); })();
});
</script>