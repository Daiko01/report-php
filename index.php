<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once __DIR__ . '/app/core/bootstrap.php';

// 2. Verificar Sesión (El Guardia)
require_once __DIR__ . '/app/includes/session_check.php';

// --- INICIO DE LÓGICA DEL DASHBOARD ---
try {
    // KPI 1: Total Empleadores
    $kpi_empleadores = $pdo->query("SELECT COUNT(id) FROM empleadores")->fetchColumn();

    // KPI 2: Total Trabajadores
    $kpi_trabajadores = $pdo->query("SELECT COUNT(id) FROM trabajadores")->fetchColumn();

    // KPI 3: Contratos Vigentes
    $kpi_contratos_vigentes = $pdo->query("SELECT COUNT(id) FROM contratos WHERE esta_finiquitado = 0 AND (fecha_termino IS NULL OR fecha_termino >= CURDATE())")
        ->fetchColumn();

    // KPI 4: Planillas Generadas este mes
    $kpi_planillas_mes_actual = $pdo->query("SELECT COUNT(DISTINCT empleador_id) FROM planillas_mensuales WHERE mes = MONTH(CURDATE()) AND ano = YEAR(CURDATE())")
        ->fetchColumn();
} catch (PDOException $e) {
    // En caso de error, mostrar 0
    $kpi_empleadores = 0;
    $kpi_trabajadores = 0;
    $kpi_contratos_vigentes = 0;
    $kpi_planillas_mes_actual = 0;
}
// --- FIN DE LÓGICA DEL DASHBOARD ---


// 3. Cargar el Header (Layout)
require_once __DIR__ . '/app/includes/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800">Dashboard</h1>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Empleadores Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $kpi_empleadores; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Trabajadores Registrados</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $kpi_trabajadores; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Contratos Vigentes</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $kpi_contratos_vigentes; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-signature fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Planillas (Este Mes)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $kpi_planillas_mes_actual; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Accesos Rápidos</h6>
                </div>
                <div class="card-body">
                    <a href="<?php echo BASE_URL; ?>/contratos/crear_contrato.php" class="btn btn-success btn-lg btn-icon-split m-2">
                        <span class="icon text-white-50"><i class="fas fa-file-signature"></i></span>
                        <span class="text">Nuevo Contrato</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/planillas/cargar_selector.php" class="btn btn-primary btn-lg btn-icon-split m-2">
                        <span class="icon text-white-50"><i class="fas fa-cogs"></i></span>
                        <span class="text">Generar Planilla</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/reportes/reportes_selector.php" class="btn btn-info btn-lg btn-icon-split m-2">
                        <span class="icon text-white-50"><i class="fas fa-file-pdf"></i></span>
                        <span class="text">Ver Reportes</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<?php
// 4. Cargar el Footer (Cierre de Layout y JS)
require_once __DIR__ . '/app/includes/footer.php';
?>