<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

$registrados = $_GET['registrados'];
$excedentes = $_GET['excedentes'];
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Resultado de la Carga</h6>
        </div>
        <div class="card-body text-center">
            <div class="alert alert-success">
                <h4><i class="fas fa-check-circle"></i> <?php echo $registrados; ?> Aportes procesados correctamente.</h4>
                <p>Estos montos se cargarán automáticamente al generar las planillas de este mes.</p>
            </div>

            <?php if ($excedentes > 0): ?>
                <div class="alert alert-warning">
                    <h4><i class="fas fa-exclamation-triangle"></i> <?php echo $excedentes; ?> Trabajadores NO encontrados.</h4>
                    <p>Estos trabajadores no están en tu base de datos. Se consideran "Excedentes".</p>
                    <hr>
                    <a href="generar_pdf_excedentes.php" target="_blank" class="btn btn-danger btn-lg">
                        <i class="fas fa-file-pdf me-2"></i> Descargar Reporte de Excedentes (PDF)
                    </a>
                </div>
            <?php endif; ?>
            
            <a href="cargar_aportes.php" class="btn btn-secondary">Volver</a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>