<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

try {
    // Cargar listas para los select
    $trabajadores = $pdo->query("SELECT id, nombre, rut FROM trabajadores ORDER BY nombre")->fetchAll();
    $empleadores = $pdo->query("SELECT id, nombre, rut FROM empleadores ORDER BY nombre")->fetchAll();
} catch (PDOException $e) { 
    $trabajadores = []; 
    $empleadores = []; 
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Contrato</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ficha del Contrato</h6>
        </div>
        <div class="card-body">
            <form action="crear_contrato_process.php" method="POST" id="form-contrato">
                
                <h5 class="mb-3 text-primary"><i class="fas fa-users me-2"></i>Las Partes</h5>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="trabajador_id" class="form-label fw-bold">Trabajador</label>
                        <select class="form-select select2" id="trabajador_id" name="trabajador_id" required>
                            <option value="" disabled selected>Buscar Trabajador...</option>
                            <?php foreach ($trabajadores as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre']) . ' (' . $t['rut'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="empleador_id" class="form-label fw-bold">Empleador</label>
                        <select class="form-select select2" id="empleador_id" name="empleador_id" required>
                            <option value="" disabled selected>Buscar Empleador...</option>
                            <?php foreach ($empleadores as $e): ?>
                                <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']) . ' (' . $e['rut'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3 text-primary"><i class="fas fa-calendar-alt me-2"></i>Vigencia y Tipo</h5>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="tipo_contrato" class="form-label fw-bold">Tipo de Contrato</label>
                        <select class="form-select no-select2" id="tipo_contrato" name="tipo_contrato" required>
                            <option value="Indefinido" selected>Indefinido</option>
                            <option value="Fijo">Plazo Fijo</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label fw-bold">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                    </div>

                    <div class="col-md-3" id="wrapper-fecha-termino" style="display: none;">
                        <label for="fecha_termino" class="form-label fw-bold text-danger">Fecha Término</label>
                        <input type="date" class="form-control" id="fecha_termino" name="fecha_termino">
                    </div>

                    <div class="col-md-3 d-flex align-items-end pb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="es_part_time" name="es_part_time" value="1">
                            <label class="form-check-label fw-bold" for="es_part_time">¿Es Part-Time?</label>
                        </div>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3 text-primary"><i class="fas fa-money-bill-wave me-2"></i>Remuneraciones (Valores Mensuales)</h5>
                <div class="row bg-light p-3 rounded border">
                    <div class="col-md-4">
                        <label for="sueldo_imponible" class="form-label fw-bold">Sueldo Base (Imponible)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="sueldo_imponible" name="sueldo_imponible" required placeholder="0">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="pacto_colacion" class="form-label">Asignación Colación (Fijo)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="pacto_colacion" name="pacto_colacion" value="0">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="pacto_movilizacion" class="form-label">Asignación Movilización (Fijo)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="pacto_movilizacion" name="pacto_movilizacion" value="0">
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 d-flex justify-content-end">
                    <a href="gestionar_contratos.php" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save me-1"></i> Guardar Contrato
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Inicializar Select2 solo en los buscadores de personas
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    const $tipoContrato = $('#tipo_contrato');
    const $wrapperFechaTermino = $('#wrapper-fecha-termino');
    const $inputFechaTermino = $('#fecha_termino');

    function toggleFields() {
        if ($tipoContrato.val() === 'Fijo') {
            // Si es Fijo, mostramos y hacemos requerida la fecha
            $wrapperFechaTermino.fadeIn();
            $inputFechaTermino.prop('required', true);
        } else {
            // Si es Indefinido, ocultamos y limpiamos
            $wrapperFechaTermino.hide();
            $inputFechaTermino.prop('required', false).val('');
        }
    }

    $tipoContrato.on('change', toggleFields);
    toggleFields(); // Ejecutar al cargar
});
</script>