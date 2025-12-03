<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Reportes Especiales</h1>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Generar Reporte Específico</h6>
                </div>
                <div class="card-body">
                    <form action="ver_reporte_especial.php" method="GET" target="_blank">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Mes</label>
                                <select class="form-select" name="mes">
                                    <?php 
                                    $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                                    foreach ($meses as $num => $nombre): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($num == date('n')) ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Año</label>
                                <select class="form-select" name="ano">
                                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Filtrar por Empresa</label>
                            <select class="form-select" name="empresa">
                                <option value="BUSES BP">BUSES BP</option>
                                <option value="SOC. INV. SOL DEL PACIFICO">SOC. INV. SOL DEL PACIFICO</option>
                            </select>
                        </div>

                        <hr>

                        <div class="d-grid gap-2">
                            <button type="submit" name="tipo_reporte" value="sindicatos" class="btn btn-info btn-lg">
                                <i class="fas fa-users me-2"></i> Reporte de Sindicatos
                            </button>
                            
                            <button type="submit" name="tipo_reporte" value="excedentes" class="btn btn-success btn-lg">
                                <i class="fas fa-money-bill-wave me-2"></i> Reporte Excedentes (Saldo a Favor)
                            </button>
                            
                            <button type="submit" name="tipo_reporte" value="asignacion" class="btn btn-primary btn-lg">
                                <i class="fas fa-child me-2"></i> Reporte Asignación Familiar
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>