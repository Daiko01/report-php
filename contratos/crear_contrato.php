<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

try {
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
        <div class="card-body">
            <form action="crear_contrato_process.php" method="POST" id="form-contrato">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="trabajador_id" class="form-label">Trabajador</label>
                        <select class="form-select" id="trabajador_id" name="trabajador_id" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach ($trabajadores as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre']) . ' (' . $t['rut'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="empleador_id" class="form-label">Empleador</label>
                        <select class="form-select" id="empleador_id" name="empleador_id" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach ($empleadores as $e): ?>
                                <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']) . ' (' . $e['rut'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                        <select class="form-select" id="tipo_contrato" name="tipo_contrato" required>
                            <option value="Indefinido">Indefinido</option>
                            <option value="Fijo">Plazo Fijo</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="sueldo_imponible" class="form-label">Sueldo Imponible Base</label>
                        <input type="number" class="form-control" id="sueldo_imponible" name="sueldo_imponible" required>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch form-check-lg mt-4">
                            <input class="form-check-input" type="checkbox" id="es_part_time" name="es_part_time" value="1" disabled>
                            <label class="form-check-label" for="es_part_time">Es Part-Time</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    <div class="col-md-6 mb-3" id="wrapper-fecha-termino">
                        <label for="fecha_termino" class="form-label">Fecha Término</label>
                        <input type="date" class="form-control" id="fecha_termino" name="fecha_termino">
                    </div>
                </div>

                <hr>
                <a href="gestionar_contratos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Contrato</button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        const $tipoContrato = $('#tipo_contrato');
        const $wrapperFechaTermino = $('#wrapper-fecha-termino');
        const $inputFechaTermino = $('#fecha_termino');
        const $checkPartTime = $('#es_part_time');

        function toggleFields() {
            if ($tipoContrato.val() === 'Fijo') {
                // Regla: Plazo Fijo
                $wrapperFechaTermino.show();
                $inputFechaTermino.prop('required', true);
                // Regla: Part Time habilitado
                $checkPartTime.prop('disabled', false);
            } else {
                // Regla: Indefinido
                $wrapperFechaTermino.hide();
                $inputFechaTermino.prop('required', false).val('');
                // Regla: Part Time deshabilitado
                $checkPartTime.prop('disabled', true).prop('checked', false);
            }
        }

        $tipoContrato.on('change', toggleFields);
        // Iniciar
        toggleFields();
    });
</script>