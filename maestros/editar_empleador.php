<?php

// 1. Cargar el núcleo

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

require_once dirname(__DIR__) . '/app/includes/session_check.php';



// 2. Validar ID

if (!isset($_GET['id'])) {

    header('Location: ' . BASE_URL . '/maestros/gestionar_empleadores.php');

    exit;

}

$id = $_GET['id'];



try {

    // 3. Obtener datos del empleador

    $stmt = $pdo->prepare("SELECT * FROM empleadores WHERE id = :id");

    $stmt->execute(['id' => $id]);

    $empleador = $stmt->fetch();



    if (!$empleador) {

        header('Location: ' . BASE_URL . '/maestros/gestionar_empleadores.php');

        exit;

    }



    // 4. Obtener Cajas y Mutuales para los <select>

    $cajas = $pdo->query("SELECT id, nombre FROM cajas_compensacion ORDER BY nombre")->fetchAll();

    $mutuales = $pdo->query("SELECT id, nombre FROM mutuales_seguridad ORDER BY nombre")->fetchAll();



} catch (PDOException $e) {

    die("Error al cargar datos.");

}



// 5. Cargar Header

require_once dirname(__DIR__) . '/app/includes/header.php';

?>



<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800">Editar Empleador</h1>



    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($empleador['nombre']); ?></h6>

        </div>

        <div class="card-body">

            

            <form action="editar_empleador_process.php" method="POST" class="needs-validation" novalidate>

                <input type="hidden" name="id" value="<?php echo $empleador['id']; ?>">



            <div class="row">
                    <div class="col-md-5 mb-3">
                        <label for="nombre" class="form-label">Nombre o Razón Social</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($empleador['nombre']); ?>" readonly disabled>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="empresa_sistema" class="form-label">Empresa Madre</label>
                        <select class="form-select" id="empresa_sistema" name="empresa_sistema" required>
                            <option value="BUSES BP" <?php echo ($empleador['empresa_sistema'] == 'BUSES BP') ? 'selected' : ''; ?>>BUSES BP</option>
                            <option value="SOC. INV. SOL DEL PACIFICO" <?php echo ($empleador['empresa_sistema'] == 'SOC. INV. SOL DEL PACIFICO') ? 'selected' : ''; ?>>SOL DEL PACIFICO</option>
                        </select>
                    </div>

                    

                    <div class="col-md-4 mb-3">

                        <label for="rut" class="form-label">RUT</label>

                        <input type="text" class="form-control" id="rut" name="rut" value="<?php echo htmlspecialchars($empleador['rut']); ?>" readonly disabled>

                        <div class="form-text">El RUT no se puede modificar.</div>

                    </div>

                </div>



                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label for="caja_compensacion_id" class="form-label">Caja de Compensación (Opcional)</label>

                        <select class="form-select" id="caja_compensacion_id" name="caja_compensacion_id">

                            <option value="" <?php echo ($empleador['caja_compensacion_id'] == null) ? 'selected' : ''; ?>>Ninguna (Sin Caja)</option>

                            <?php foreach ($cajas as $caja): ?>

                                <option value="<?php echo $caja['id']; ?>" <?php echo ($caja['id'] == $empleador['caja_compensacion_id']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($caja['nombre']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <div class="col-md-4 mb-3">

                        <label for="mutual_seguridad_id" class="form-label">Mutual de Seguridad</label>

                        <select class="form-select" id="mutual_seguridad_id" name="mutual_seguridad_id" required>

                            <option value="" disabled>Seleccione...</option>

                             <?php foreach ($mutuales as $mutual): ?>

                                <option value="<?php echo $mutual['id']; ?>" <?php echo ($mutual['id'] == $empleador['mutual_seguridad_id']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($mutual['nombre']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="invalid-feedback">Seleccione una mutual.</div>

                    </div>

                </div>

                

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label for="tasa_mutual" class="form-label">Tasa Mutual (Ej: 0.93)</label>

                        <div class="input-group">

                            <input type="number" step="0.01" class="form-control" id="tasa_mutual" name="tasa_mutual" value="<?php echo $empleador['tasa_mutual_decimal'] * 100; ?>" required>

                            <span class="input-group-text">%</span>

                        </div>

                    </div>

                </div>



                <hr>

                <a href="gestionar_empleadores.php" class="btn btn-secondary">Cancelar</a>

                <button type="submit" class="btn btn-primary">Actualizar Empleador</button>

            </form>



        </div>

    </div>

</div>



<?php

// 6. Cargar Footer

require_once dirname(__DIR__) . '/app/includes/footer.php';

?>

<script>

(function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation'); Array.prototype.slice.call(forms).forEach(function (form) { form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); }); })();

</script>