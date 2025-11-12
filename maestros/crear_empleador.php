<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 3. Obtener Cajas y Mutuales para los <select>
try {
    $cajas = $pdo->query("SELECT id, nombre FROM cajas_compensacion ORDER BY nombre")->fetchAll();
    $mutuales = $pdo->query("SELECT id, nombre FROM mutuales_seguridad ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
    $cajas = [];
    $mutuales = [];
}

// 4. Cargar el Header (Layout)
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Empleador</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Datos del Empleador</h6>
        </div>
        <div class="card-body">
            
            <form action="crear_empleador_process.php" method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nombre" class="form-label">Nombre o Razón Social</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="rut" class="form-label">RUT</label>
                        <input type="text" class="form-control rut-input" id="rut" name="rut" maxlength="12" required>
                        <div class="invalid-feedback">El RUT es obligatorio y debe ser válido.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="caja_compensacion_id" class="form-label">Caja de Compensación (Opcional)</label>
                        <select class="form-select" id="caja_compensacion_id" name="caja_compensacion_id">
                            <option value="" selected>Ninguna (Sin Caja)</option>
                            <?php foreach ($cajas as $caja): ?>
                                <option value="<?php echo $caja['id']; ?>"><?php echo htmlspecialchars($caja['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione una caja.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="mutual_seguridad_id" class="form-label">Mutual de Seguridad</label>
                        <select class="form-select" id="mutual_seguridad_id" name="mutual_seguridad_id" required>
                            <option value="" disabled selected>Seleccione...</option>
                             <?php foreach ($mutuales as $mutual): ?>
                                <option value="<?php echo $mutual['id']; ?>"><?php echo htmlspecialchars($mutual['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione una mutual.</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="tasa_mutual" class="form-label">Tasa Mutual (Ej: 0.93)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="tasa_mutual" name="tasa_mutual" value="0.93" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Tasa base es 0.90 + 0.03 (Ley SANNA).</div>
                    </div>
                </div>

                <hr>
                <a href="gestionar_empleadores.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Empleador</button>
            </form>

        </div>
    </div>
</div>

<?php
// 5. Cargar el Footer (Layout y JS)
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>