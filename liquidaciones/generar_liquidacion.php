<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

try {
    $empleadores = $pdo->query("SELECT id, nombre, rut FROM empleadores ORDER BY nombre")->fetchAll();
} catch (PDOException $e) { $empleadores = []; }

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Generar Liquidaciones Mensuales</h1>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 border-left-primary">
                    <h6 class="m-0 font-weight-bold text-primary">Procesamiento Masivo o Individual</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        El sistema generará las liquidaciones basándose en las <strong>Planillas Mensuales</strong> ya guardadas.
                        Si selecciona "Todos", se procesarán todas las empresas que tengan planilla para ese mes.
                    </div>

                    <form action="procesar_generacion.php" method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Empleador</label>
                            <select class="form-select" name="empleador_id" required>
                                <option value="todos" selected>--- PROCESAR TODAS LAS EMPRESAS ---</option>
                                <?php foreach ($empleadores as $e): ?>
                                    <option value="<?php echo $e['id']; ?>">
                                        <?php echo htmlspecialchars($e['nombre']) . ' (' . $e['rut'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Mes</label>
                                <select class="form-select" name="mes" required>
                                    <?php 
                                    $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                                    foreach ($meses as $num => $nombre): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($num == date('n')) ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Año</label>
                                <select class="form-select" name="ano" required>
                                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 1; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-cogs me-2"></i> Iniciar Generación
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>