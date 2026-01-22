<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 1. Cargar datos para los selectores
try {
    $cajas = $pdo->query("SELECT id, nombre FROM cajas_compensacion ORDER BY nombre")->fetchAll();
    $mutuales = $pdo->query("SELECT id, nombre FROM mutuales_seguridad ORDER BY nombre")->fetchAll();

    // ELIMINADO: Ya no cargamos la lista de empresas madre porque el sistema ya sabe quién es
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Empleador - <?php echo NOMBRE_SISTEMA; ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold">Datos del Empleador</h6>
        </div>
        <div class="card-body">
            <form action="crear_empleador_process.php" method="POST" class="needs-validation" novalidate>

                <input type="hidden" name="empresa_sistema_id" value="<?php echo ID_EMPRESA_SISTEMA; ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="rut" class="form-label fw-bold">RUT</label>
                        <input type="text" class="form-control rut-input" id="rut" name="rut" placeholder="12.345.678-9" maxlength="12" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label fw-bold">Razón Social / Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="caja_compensacion_id" class="form-label fw-bold">Caja de Compensación</label>
                        <select class="form-select" id="caja_compensacion_id" name="caja_compensacion_id">
                            <option value="">Ninguna</option>
                            <?php foreach ($cajas as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="mutual_seguridad_id" class="form-label fw-bold">Mutual de Seguridad</label>
                        <select class="form-select" id="mutual_seguridad_id" name="mutual_seguridad_id" required>
                            <?php foreach ($mutuales as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo ($m['nombre'] == 'ISL') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="tasa_mutual" class="form-label fw-bold">Tasa Mutual (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="tasa_mutual" name="tasa_mutual" value="0.93" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="text-end">
                    <a href="gestionar_empleadores.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-1"></i> Guardar Empleador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>