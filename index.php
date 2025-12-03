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

<div class="container-fluid px-3 px-md-4">

    <!-- Header con Bienvenida -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gradient-primary">Dashboard</h1>
            <p class="text-muted mb-0">Bienvenido de vuelta, aquí tienes un resumen de tu sistema</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-light text-dark">
                <i class="fas fa-calendar me-1"></i>
                <?php echo date('d M Y'); ?>
            </span>
        </div>
    </div>

    <!-- KPI Cards Modernas -->
    <div class="row g-3 mb-4">
        <!-- Empleadores Activos -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-kpi card-employers h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="kpi-content">
                            <div class="kpi-label">Empleadores</div>
                            <div class="kpi-value"><?php echo $kpi_empleadores; ?></div>
                            <div class="kpi-trend text-success">
                                <i class="fas fa-chart-line me-1"></i>
                                <span>Registrados</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <div class="icon-wrapper bg-primary">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trabajadores Registrados -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-kpi card-workers h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="kpi-content">
                            <div class="kpi-label">Trabajadores</div>
                            <div class="kpi-value"><?php echo $kpi_trabajadores; ?></div>
                            <div class="kpi-trend text-info">
                                <i class="fas fa-users me-1"></i>
                                <span>Registrados</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <div class="icon-wrapper bg-success">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contratos Vigentes -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-kpi card-contracts h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="kpi-content">
                            <div class="kpi-label">Contratos</div>
                            <div class="kpi-value"><?php echo $kpi_contratos_vigentes; ?></div>
                            <div class="kpi-trend text-warning">
                                <i class="fas fa-file-contract me-1"></i>
                                <span>Vigentes</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <div class="icon-wrapper bg-info">
                                <i class="fas fa-file-signature"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planillas Este Mes -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-kpi card-payrolls h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="kpi-content">
                            <div class="kpi-label">Planillas</div>
                            <div class="kpi-value"><?php echo $kpi_planillas_mes_actual; ?></div>
                            <div class="kpi-trend text-primary">
                                <i class="fas fa-calendar-check me-1"></i>
                                <span>Este Mes</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <div class="icon-wrapper bg-warning">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección Principal: Acciones y Estadísticas -->
    <div class="row g-4">
        <!-- Acciones Rápidas -->
        <div class="col-12 col-lg-8">
            <div class="card card-actions h-100">
                <div class="card-header bg-transparent border-0 pb-2">
                    <h5 class="card-title mb-0 text-dark">
                        <i class="fas fa-bolt text-warning me-2"></i>
                        Acciones Rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Nuevo Contrato -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?php echo BASE_URL; ?>/contratos/crear_contrato.php" class="action-card">
                                <div class="action-icon bg-success">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                <div class="action-content">
                                    <h6 class="text-dark">Nuevo Contrato</h6>
                                    <p class="text-muted">Crear nuevo contrato laboral</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                        </div>

                        <!-- Generar Planilla -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?php echo BASE_URL; ?>/planillas/cargar_selector.php" class="action-card">
                                <div class="action-icon bg-primary">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="action-content">
                                    <h6 class="text-dark">Generar Planilla</h6>
                                    <p class="text-muted">Procesar planillas mensuales</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                        </div>

                        <!-- Ver Reportes -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?php echo BASE_URL; ?>/reportes/reportes_selector.php" class="action-card">
                                <div class="action-icon bg-info">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="action-content">
                                    <h6 class="text-dark">Ver Reportes</h6>
                                    <p class="text-muted">Reportes y documentos PDF</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                        </div>

                        <!-- Gestión Empleadores -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?php echo BASE_URL; ?>/maestros/gestionar_empleadores.php" class="action-card">
                                <div class="action-icon bg-warning">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="action-content">
                                    <h6 class="text-dark">Gestión Empleadores</h6>
                                    <p class="text-muted">Administrar empresas</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                        </div>

                        <!-- Gestión Trabajadores -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <a href="<?php echo BASE_URL; ?>/maestros/gestionar_trabajadores.php" class="action-card">
                                <div class="action-icon bg-purple">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <div class="action-content">
                                    <h6 class="text-dark">Gestión Trabajadores</h6>
                                    <p class="text-muted">Administrar personal</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas Rápidas -->
        <div class="col-12 col-lg-4">
            <div class="card card-stats h-100">
                <div class="card-header bg-transparent border-0 pb-2">
                    <h5 class="card-title mb-0 text-dark">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Resumen Mensual
                    </h5>
                </div>
                <div class="card-body">
                    <div class="stats-list">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-calendar text-primary"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label text-muted">Planillas del Mes</span>
                                <span class="stat-value text-dark"><?php echo $kpi_planillas_mes_actual; ?></span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-user-plus text-success"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label text-muted">Contratos Activos</span>
                                <span class="stat-value text-dark"><?php echo $kpi_contratos_vigentes; ?></span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-building text-info"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label text-muted">Empleadores Activos</span>
                                <span class="stat-value text-dark"><?php echo $kpi_empleadores; ?></span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-users text-warning"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label text-muted">Trabajadores Total</span>
                                <span class="stat-value text-dark"><?php echo $kpi_trabajadores; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
// 4. Cargar el Footer (Cierre de Layout y JS)
require_once __DIR__ . '/app/includes/footer.php';
?>