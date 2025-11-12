<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Obtener empleadores para el Select2
try {
    $empleadores = $pdo->query("SELECT id, nombre, rut FROM empleadores ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
    $empleadores = [];
}

// 3. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Cargar Planilla Mensual</h1>

    <div class="row d-flex justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Seleccionar Período y Empleador</h6>
                </div>
                <div class="card-body">
                    <form action="generar_planilla.php" method="POST">

                        <div class="mb-3">
                            <label for="empleador_id" class="form-label">Empleador</label>
                            <select class="form-select" id="empleador_id" name="empleador_id" required>
                                <option value="" disabled selected>Seleccione un empleador...</option>
                                <?php foreach ($empleadores as $e): ?>
                                    <option value="<?php echo $e['id']; ?>">
                                        <?php echo htmlspecialchars($e['nombre']) . ' (' . htmlspecialchars($e['rut']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mes" class="form-label">Mes</label>
                                <select class="form-select" id="mes" name="mes" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>>
                                            <?php echo strftime('%B', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ano" class="form-label">Año</label>
                                <select class="form-select" id="ano" name="ano" required>
                                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-grid-horizontal me-2"></i> Generar Planilla
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 4. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>