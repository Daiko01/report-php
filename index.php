<?php
// 1. Cargar el núcleo
require_once __DIR__ . '/app/core/bootstrap.php';
require_once __DIR__ . '/app/includes/session_check.php';

// --- LÓGICA DEL DASHBOARD FILTRADA POR SISTEMA ---
try {
    $id_sistema = ID_EMPRESA_SISTEMA;
    $hoy = date('Y-m-d');

    // KPI 1: Contratos por Vencer (En 1 Mes)
    // Rango: Desde HOY hasta HOY + 1 MES
    $fecha_limite = date('Y-m-d', strtotime('+1 month'));

    $sql_cont = "SELECT COUNT(c.id) FROM contratos c 
                 JOIN empleadores e ON c.empleador_id = e.id 
                 WHERE e.empresa_sistema_id = ? 
                 AND c.esta_finiquitado = 0 
                 AND c.fecha_termino BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql_cont);
    $stmt->execute([$id_sistema, $hoy, $fecha_limite]);
    $kpi_contratos_vencer = $stmt->fetchColumn();

    // KPI 2: Licencias Médicas Activas
    // Activas = Fecha Fin >= HOY (Es decir, aun no terminan)
    $sql_lic = "SELECT COUNT(l.id) FROM trabajador_licencias l
                JOIN trabajadores t ON l.trabajador_id = t.id
                JOIN contratos c ON t.id = c.trabajador_id
                JOIN empleadores e ON c.empleador_id = e.id
                WHERE e.empresa_sistema_id = ?
                AND l.fecha_fin >= ?";
    $stmt = $pdo->prepare($sql_lic);
    $stmt->execute([$id_sistema, $hoy]);
    $kpi_licencias = $stmt->fetchColumn();

    // KPI 3: Tasa SIS Vigente
    $stmt = $pdo->query("SELECT * FROM sis_historico ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");
    $sis_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $tasa_sis = $sis_data ? $sis_data['tasa_sis_decimal'] : 0;
    $tasa_sis_pct = number_format($tasa_sis * 100, 2) . '%';
    $sis_fecha = $sis_data ? ($sis_data['mes_inicio'] . '/' . $sis_data['ano_inicio']) : 'N/A';
} catch (PDOException $e) {
    // Si fallan los KPIs básicos
    $kpi_contratos_vencer = $kpi_licencias = 0;
    $tasa_sis_pct = "0.00%";
}

// ACTIVIDAD RECIENTE (Separado para no afectar KPIs)
// AGENDA DE VENCIMIENTOS (Próximos 30 días)
$expirations = [];
try {
    // 1. Contratos por vencer
    $fecha_30 = date('Y-m-d', strtotime('+30 days'));

    $sql_c = "SELECT t.nombre, c.tipo_contrato as detalle, c.fecha_termino, 'contrato' as tipo
              FROM contratos c
              JOIN trabajadores t ON c.trabajador_id = t.id
              JOIN empleadores e ON c.empleador_id = e.id
              WHERE e.empresa_sistema_id = ? 
              AND c.esta_finiquitado = 0 
              AND c.fecha_termino BETWEEN ? AND ?
              ORDER BY c.fecha_termino ASC LIMIT 10";
    $stmt = $pdo->prepare($sql_c);
    $stmt->execute([$id_sistema, $hoy, $fecha_30]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $expirations[] = $row;
    }

    // 2. Licencias por terminar (Próximos 15 días)
    $fecha_15 = date('Y-m-d', strtotime('+15 days'));

    $sql_l = "SELECT t.nombre, 'Término de Licencia' as detalle, l.fecha_fin as fecha_termino, 'licencia' as tipo
              FROM trabajador_licencias l
              JOIN trabajadores t ON l.trabajador_id = t.id
              JOIN contratos c ON t.id = c.trabajador_id
              JOIN empleadores e ON c.empleador_id = e.id
              WHERE e.empresa_sistema_id = ?
              AND l.fecha_fin >= ? AND l.fecha_fin <= ?
              GROUP BY l.id
              ORDER BY l.fecha_fin ASC LIMIT 10";
    $stmt = $pdo->prepare($sql_l);
    $stmt->execute([$id_sistema, $hoy, $fecha_15]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $expirations[] = $row;
    }

    // Ordenar cronológicamente (lo más próximo primero)
    usort($expirations, function ($a, $b) {
        return strtotime($a['fecha_termino']) - strtotime($b['fecha_termino']);
    });
} catch (PDOException $e) {
    $expirations = [];
}


require_once __DIR__ . '/app/includes/header.php';
?>

<style>
    :root {
        --primary-color: <?php echo COLOR_SISTEMA; ?>;
    }

    .dashboard-header {
        padding: 2rem 0;
        margin-bottom: 2rem;
    }

    .kpi-card {
        border: none;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        transition: transform 0.2s;
        height: 100%;
        position: relative;
        overflow: hidden;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .kpi-icon-bg {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 5rem;
        opacity: 0.05;
        transform: rotate(-15deg);
    }

    .kpi-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .kpi-label {
        color: #6c757d;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kpi-link {
        color: var(--primary-color);
        font-size: 0.85rem;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        margin-top: 1rem;
    }

    .kpi-link:hover {
        text-decoration: underline;
    }

    .activity-feed {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        padding: 1rem 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #eef2f7;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        flex-shrink: 0;
    }

    .activity-content h6 {
        margin: 0;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .activity-content p {
        margin: 2px 0 0;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .activity-time {
        font-size: 0.75rem;
        color: #adb5bd;
        margin-left: auto;
        white-space: nowrap;
    }
</style>

<div class="container-fluid px-4 py-4">

    <!-- KPI Section -->
    <div class="row g-4 mb-5">

        <!-- Contracts Expiring -->
        <div class="col-md-4">
            <div class="kpi-card p-4 border-start border-4 border-warning">
                <i class="fas fa-file-contract kpi-icon-bg text-warning"></i>
                <div class="kpi-value text-warning"><?php echo $kpi_contratos_vencer; ?></div>
                <div class="kpi-label">Contratos por Vencer (1 Mes)</div>
                <a href="contratos/gestionar_contratos.php" class="kpi-link">
                    Ver Detalle <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>

        <!-- Active Licenses -->
        <div class="col-md-4">
            <div class="kpi-card p-4 border-start border-4 border-info">
                <i class="fas fa-notes-medical kpi-icon-bg text-info"></i>
                <div class="kpi-value text-info"><?php echo $kpi_licencias; ?></div>
                <div class="kpi-label">Licencias Médicas Activas</div>
                <a href="maestros/gestionar_licencias.php" class="kpi-link">
                    Gestionar Licencias <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>

        <!-- SIS Rate -->
        <div class="col-md-4">
            <div class="kpi-card p-4 border-start border-4 border-success">
                <i class="fas fa-percentage kpi-icon-bg text-success"></i>
                <div class="kpi-value text-success"><?php echo $tasa_sis_pct; ?></div>
                <div class="kpi-label">Tasa SIS (Actualizada: <?php echo $sis_fecha; ?>)</div>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                    <a href="admin/gestionar_sis.php" class="kpi-link">
                        Ver Historial <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <!-- Agenda de Vencimientos -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary mb-0">Agenda de Vencimientos (Próximos 30 días)</h5>
                <small class="text-muted">Contratos y Licencias</small>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Tipo</th>
                                    <th>Trabajador</th>
                                    <th>Detalle</th>
                                    <th>Vencimiento</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($expirations) > 0): ?>
                                    <?php foreach ($expirations as $item): ?>
                                        <?php
                                        // Cálculos visuales
                                        $dias_restantes = (strtotime($item['fecha_termino']) - strtotime(date('Y-m-d'))) / 86400;
                                        $dias_restantes = ceil($dias_restantes);

                                        $badgeClass = 'bg-success';
                                        $badgeText = $dias_restantes . ' días';

                                        if ($dias_restantes <= 5) $badgeClass = 'bg-danger';
                                        elseif ($dias_restantes <= 15) $badgeClass = 'bg-warning text-dark';

                                        $icon = ($item['tipo'] == 'contrato') ? 'fa-file-signature text-warning' : 'fa-user-md text-info';
                                        $link = ($item['tipo'] == 'contrato') ? 'contratos/gestionar_contratos.php' : 'maestros/gestionar_licencias.php';
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                </div>
                                            </td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($item['nombre']); ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($item['detalle']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $badgeClass; ?> rounded-pill">
                                                    <?php echo ($dias_restantes == 0) ? 'Hoy' : ($dias_restantes == 1 ? 'Mañana' : $badgeText); ?>
                                                </span>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                    <?php echo date('d/m/Y', strtotime($item['fecha_termino'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="<?php echo $link; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    Gestionar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-calendar-check fa-2x mb-2 text-success opacity-50"></i>
                                            <p class="mb-0">¡Todo en orden! No hay vencimientos próximos.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>