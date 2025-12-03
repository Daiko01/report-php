<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Carga Masiva de Aportes</h1>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Subir Archivo CSV</h6>
                </div>
                <div class="card-body">
                    <form action="procesar_carga.php" method="POST" enctype="multipart/form-data">
                        
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

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> La empresa se detectará automáticamente según el N° de Máquina del archivo.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Archivo (CSV)</label>
                            <input type="file" class="form-control" name="archivo_aportes" accept=".csv" required>
                            <div class="form-text">Formato: N° Máquina, RUT, Nombre, Monto.</div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i> Procesar Archivo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4 border-left-warning">
                <div class="card-body">
                    <h5 class="card-title">Gestión de Excedentes</h5>
                    <p class="card-text">Revise los registros que no pudieron ser asignados a un contrato vigente (máquina no existe o conductor sin contrato).</p>
                    <a href="ver_excedentes.php" class="btn btn-warning">
                        <i class="fas fa-list-ul me-2"></i> Ver Historial de Excedentes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>