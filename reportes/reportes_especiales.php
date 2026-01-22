<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// Definimos los meses para el selector
$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-invoice-dollar text-primary me-2"></i>Reportes Especiales
        </h1>
        <span class="badge p-2 shadow-sm" style="background-color: <?php echo COLOR_SISTEMA; ?>; color: white;">
            Sistema: <?php echo NOMBRE_SISTEMA; ?>
        </span>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow mb-4 border-bottom-primary">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary text-center text-uppercase small">
                        Configuración de Reporte Mensual
                    </h6>
                </div>
                <div class="card-body">
                    <form action="ver_reporte_especial.php" method="GET" target="_blank">

                        <input type="hidden" name="empresa" value="<?php echo (ID_EMPRESA_SISTEMA == 1) ? 'BUSES BP' : 'SOC. INV. SOL DEL PACIFICO'; ?>">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">MES DEL REPORTE</label>
                                <select class="form-select form-select-lg" name="mes">
                                    <?php foreach ($meses as $num => $nombre): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($num == date('n')) ? 'selected' : ''; ?>>
                                            <?php echo $nombre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">AÑO</label>
                                <select class="form-select form-select-lg" name="ano">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 shadow-sm small mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Seleccione el tipo de reporte que desea generar para el periodo seleccionado. El reporte se abrirá en una pestaña nueva.
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" name="tipo_reporte" value="sindicatos" class="btn btn-outline-info btn-lg text-start px-4 shadow-sm py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-users-cog me-3"></i>Reporte de <strong>Sindicatos</strong></span>
                                    <i class="fas fa-chevron-right opacity-50"></i>
                                </div>
                            </button>

                            <button type="submit" name="tipo_reporte" value="excedentes" class="btn btn-outline-success btn-lg text-start px-4 shadow-sm py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-piggy-bank me-3"></i>Reporte de <strong>Excedentes</strong> (Saldo a Favor)</span>
                                    <i class="fas fa-chevron-right opacity-50"></i>
                                </div>
                            </button>

                            <button type="submit" name="tipo_reporte" value="asignacion" class="btn btn-outline-primary btn-lg text-start px-4 shadow-sm py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-baby me-3"></i>Reporte de <strong>Asignación Familiar</strong></span>
                                    <i class="fas fa-chevron-right opacity-50"></i>
                                </div>
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <p class="text-center text-muted small mt-3">
                <i class="fas fa-lock me-1"></i> Filtros de seguridad activos para <strong><?php echo NOMBRE_SISTEMA; ?></strong>
            </p>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>