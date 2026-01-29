<?php
// Detectar página actual
$pagina_actual = basename($_SERVER['PHP_SELF']);

// --- DEFINICIÓN DE GRUPOS PARA "ACTIVE" (REORGANIZADO CON TODOS LOS ARCHIVOS) ---

// 1. GESTIÓN DE FLOTA (Prioridad Operativa)
$paginas_flota = [
    'configuracion.php',
    'gestionar_buses.php',     // Movido desde Maestros (El listado de máquinas)
    'crear_bus.php',           // Asumiendo existencia
    'editar_bus.php',          // Asumiendo existencia
    'ingreso_guia.php',
    'editar_guia.php',
    'importar_produccion.php',
    'planilla_mensual.php',
    'cierre_mensual.php',
    'seleccionar_reporte.php'
];

// 2. RECURSOS HUMANOS (Consolidado: Personal, Empresas, Contratos, Procesos, Liquidaciones, Reportes)
$paginas_rrhh = [
    // Personal y Empresas
    'gestionar_trabajadores.php',
    'crear_trabajador.php',
    'editar_trabajador.php',
    'gestionar_empleadores.php',
    'crear_empleador.php',
    'editar_empleador.php',
    // Contratos
    'gestionar_contratos.php',
    'crear_contrato.php',
    'editar_contrato.php',
    'gestionar_licencias.php',
    // Procesos de Sueldo (Aportes y Planillas)
    'cargar_aportes.php',
    'resultado_carga.php',
    'generacion_masiva.php',
    'dashboard_mensual.php',
    'cargar_selector.php',
    'cargar_grid.php',
    'generar_planilla.php',
    'cierres_mensuales.php',
    // Liquidaciones
    'generar_liquidacion.php',
    'listar_liquidaciones.php',
    'ver_liquidacion_detalle.php',
    // Reportes (Aquí están los que faltaban)
    'reportes_selector.php',
    'reportes_especiales.php',
    'ver_excedentes.php'
];

// 3. SISTEMA Y PARÁMETROS (Admin + Tablas Maestras Técnicas)
$paginas_sistema = [
    'gestionar_usuarios.php',
    'crear_usuario.php',
    'editar_usuario.php',
    'gestionar_sis.php',
    'gestionar_sindicatos.php',
    'editar_sindicato.php',
    'gestionar_afps.php',
    'gestionar_comisiones_afp.php',
    'gestionar_tramos_cargas.php'
];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Reportes BP</title>
    <link rel="icon" href="<?php echo BASE_URL; ?>/public/assets/img/favicon.png" type="image/png">

    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <link href="<?php echo BASE_URL; ?>/public/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">


</head>

<body>

    <div class="overlay"></div>

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <a href="<?php echo BASE_URL; ?>/index.php" class="d-flex align-items-center text-decoration-none text-white">
                    <img src="<?php echo BASE_URL; ?>/public/assets/img/isotipo_transreport.svg" alt="TransReport" style="height: 32px; width: auto;" class="me-2">
                    <h3 class="h5 mb-0 fw-bold">TransReport BP</h3>
                </a>
            </div>

            <ul class="list-unstyled components">

                <?php $user_role = $_SESSION['user_role'] ?? ''; ?>

                <?php if ($user_role !== 'recaudador'): ?>
                    <li class="<?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    </li>
                <?php endif; ?>

                <li class="<?php echo (in_array($pagina_actual, $paginas_flota)) ? 'active' : ''; ?>">
                    <a href="#flotaMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_flota)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                        <i class="fas fa-bus me-2"></i> Gestión de Flota
                    </a>
                    <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_flota)) ? 'show' : ''; ?>" id="flotaMenu">

                        <?php if ($user_role !== 'recaudador'): ?>
                            <li class="<?php echo ($pagina_actual == 'configuracion.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/buses/configuracion.php">1. Configuración del Mes</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'gestionar_buses.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/maestros/gestionar_buses.php">2. Directorio de Buses</a>
                            </li>
                        <?php endif; ?>

                        <li class="<?php echo ($pagina_actual == 'ingreso_guia.php') ? 'active' : ''; ?>">
                            <a href="<?php echo BASE_URL; ?>/buses/ingreso_guia.php">3. Guía Diaria</a>
                        </li>

                        <?php if ($user_role !== 'recaudador'): ?>
                            <li class="<?php echo ($pagina_actual == 'importar_produccion.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/buses/importar_produccion.php">4. Importar Producción (CSV)</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'planilla_mensual.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/buses/planilla_mensual.php">5. Editar Planilla</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'cierre_mensual.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/buses/cierre_mensual.php">6. Cierre Mensual</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'seleccionar_reporte.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/buses/seleccionar_reporte.php">7. Reportes de Flota</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if ($user_role !== 'recaudador'): ?>
                    <li class="<?php echo (in_array($pagina_actual, $paginas_rrhh)) ? 'active' : ''; ?>">
                        <a href="#rrhhMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_rrhh)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                            <i class="fas fa-users me-2"></i> Recursos Humanos
                        </a>
                        <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_rrhh)) ? 'show' : ''; ?>" id="rrhhMenu">

                            <div class="sidebar-heading">Personal</div>
                            <li class="<?php echo ($pagina_actual == 'gestionar_trabajadores.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/maestros/gestionar_trabajadores.php">Directorio Trabajadores</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'gestionar_empleadores.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/maestros/gestionar_empleadores.php">Empresas / Empleadores</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'gestionar_contratos.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/contratos/gestionar_contratos.php">Gestión de Contratos</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'gestionar_licencias.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/maestros/gestionar_licencias.php">Licencias Médicas</a>
                            </li>

                            <div class="sidebar-heading">Procesos de Sueldo</div>
                            <li class="<?php echo ($pagina_actual == 'cargar_aportes.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/aportes/cargar_aportes.php">Cargar Movimientos (CSV)</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'generacion_masiva.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/planillas/generacion_masiva.php">Generación Masiva</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'dashboard_mensual.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/planillas/dashboard_mensual.php">Aportes Patronales</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'cargar_selector.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/planillas/cargar_selector.php">Generar Previsionales</a>
                            </li>
                            <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin')): ?>
                                <li class="<?php echo ($pagina_actual == 'cierres_mensuales.php') ? 'active' : ''; ?>">
                                    <a href="<?php echo BASE_URL; ?>/planillas/cierres_mensuales.php">Cierre Remuneraciones</a>
                                </li>
                            <?php endif; ?>

                            <div class="sidebar-heading">Liquidaciones</div>
                            <li class="<?php echo ($pagina_actual == 'generar_liquidacion.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/liquidaciones/generar_liquidacion.php">Generar Liquidaciones</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'listar_liquidaciones.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/liquidaciones/listar_liquidaciones.php">Historial / Descargas</a>
                            </li>

                            <div class="sidebar-heading">Reportabilidad</div>

                            <li class="<?php echo ($pagina_actual == 'reportes_especiales.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/reportes/reportes_especiales.php">Reportes Especiales</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'ver_excedentes.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/aportes/ver_excedentes.php">Historial Excedentes</a>
                            </li>
                        </ul>
                    </li>

                    <li class="<?php echo (in_array($pagina_actual, $paginas_sistema)) ? 'active' : ''; ?>">
                        <a href="#sistemaMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_sistema)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                            <i class="fas fa-cogs me-2"></i> Sistema y Parámetros
                        </a>
                        <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_sistema)) ? 'show' : ''; ?>" id="sistemaMenu">

                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                                <li class="<?php echo ($pagina_actual == 'gestionar_usuarios.php') ? 'active' : ''; ?>">
                                    <a href="<?php echo BASE_URL; ?>/admin/gestionar_usuarios.php"><i class="fas fa-user-shield me-2"></i> Usuarios del Sistema</a>
                                </li>
                                <li class="<?php echo ($pagina_actual == 'gestionar_sis.php') ? 'active' : ''; ?>">
                                    <a href="<?php echo BASE_URL; ?>/admin/gestionar_sis.php"><i class="fas fa-chart-line me-2"></i> Indicadores (SIS)</a>
                                </li>
                                <li class="<?php echo ($pagina_actual == 'gestionar_tramos_cargas.php') ? 'active' : ''; ?>">
                                    <a href="<?php echo BASE_URL; ?>/maestros/gestionar_tramos_cargas.php"><i class="fas fa-child me-2"></i> Tramos Asignación Familiar</a>
                                </li>
                            <?php endif; ?>

                            <li class="<?php echo ($pagina_actual == 'gestionar_sindicatos.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/maestros/gestionar_sindicatos.php">Sindicatos</a>
                            </li>
                            <li class="<?php echo ($pagina_actual == 'gestionar_afps.php') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL; ?>/maestros/gestionar_afps.php">AFPs y Comisiones</a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>

            <div class="sidebar-profile-section text-start">
                <small class="text-white-50" style="font-size: 0.7rem; letter-spacing: 1px;">
                    DEVELOPER BY <strong>DAIKO</strong>
                </small>
                <div class="text-white-50 mt-1" style="font-size: 0.65rem;">
                    Beta 1.1.0
                </div>
            </div>

        </nav>

        <div id="content">

            <nav class="navbar navbar-expand-lg navbar-light bg-light topbar shadow-sm">
                <!-- Container for Title/Breadcrumb, Search, Notification -->
                <div class="d-flex w-100 align-items-center justify-content-between pe-4">

                    <!-- Left: Hamburger + Breadcrumbs -->
                    <div class="d-flex align-items-center">
                        <div class="container-fluid ps-0 d-inline-block w-auto">
                            <button type="button" id="sidebarCollapse" class="btn btn-link text-dark">
                                <i class="fas fa-bars fa-lg"></i>
                            </button>
                        </div>
                        <div class="ms-3 d-none d-md-block">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>" class="text-decoration-none text-muted">Inicio</a></li>
                                    <?php
                                    // Mapping of filenames to friendly sidebar titles
                                    $page_titles = [
                                        // Flota
                                        'configuracion.php' => 'Configuración del Mes',
                                        'gestionar_buses.php' => 'Directorio de Buses',
                                        'crear_bus.php' => 'Registrar Nuevo Bus',
                                        'editar_bus.php' => 'Editar Ficha del Bus',
                                        'importar_produccion.php' => 'Importar Producción',
                                        'planilla_mensual.php' => 'Editar Planilla',
                                        'cierre_mensual.php' => 'Cierre Mensual',
                                        'seleccionar_reporte.php' => 'Reportes de Flota',

                                        // RRHH
                                        'gestionar_trabajadores.php' => 'Directorio Trabajadores',
                                        'crear_trabajador.php' => 'Registrar Trabajador',
                                        'editar_trabajador.php' => 'Editar Ficha Trabajador',
                                        'gestionar_empleadores.php' => 'Empresas / Empleadores',
                                        'crear_empleador.php' => 'Registrar Empleador',
                                        'editar_empleador.php' => 'Editar Empleador',
                                        'gestionar_contratos.php' => 'Gestión de Contratos',
                                        'crear_contrato.php' => 'Nuevo Contrato',
                                        'editar_contrato.php' => 'Editar Contrato',
                                        'gestionar_licencias.php' => 'Licencias Médicas',

                                        // Procesos
                                        'cargar_aportes.php' => 'Cargar Movimientos',
                                        'generacion_masiva.php' => 'Generación Masiva',
                                        'dashboard_mensual.php' => 'Aportes Patronales',
                                        'cargar_selector.php' => 'Generar Previsionales',
                                        'cierres_mensuales.php' => 'Cierre Remuneraciones',

                                        // Liquidaciones & Reports
                                        'generar_liquidacion.php' => 'Generar Liquidaciones',
                                        'listar_liquidaciones.php' => 'Historial / Descargas',
                                        'ver_liquidacion_detalle.php' => 'Detalle Liquidación',
                                        'reportes_especiales.php' => 'Reportes Especiales',
                                        'ver_excedentes.php' => 'Historial Excedentes',

                                        // Sistema
                                        'gestionar_usuarios.php' => 'Usuarios del Sistema',
                                        'gestionar_sis.php' => 'Indicadores (SIS)',
                                        'gestionar_tramos_cargas.php' => 'Tramos Asignación Familiar',
                                        'gestionar_sindicatos.php' => 'Sindicatos',
                                        'gestionar_afps.php' => 'AFPs y Comisiones',
                                        'perfil.php' => 'Mi Perfil'
                                    ];

                                    // If index.php, we show nothing else (or could show "Dashboard")
                                    if ($pagina_actual !== 'index.php') {
                                        // Display category
                                        if (in_array($pagina_actual, $paginas_flota)) {
                                            echo '<li class="breadcrumb-item text-muted">Gestión de Flota</li>';
                                        } elseif (in_array($pagina_actual, $paginas_rrhh)) {
                                            echo '<li class="breadcrumb-item text-muted">Recursos Humanos</li>';
                                        } elseif (in_array($pagina_actual, $paginas_sistema)) {
                                            echo '<li class="breadcrumb-item text-muted">Sistema</li>';
                                        }

                                        // Display Friendly Name or Fallback
                                        $friendly_name = isset($page_titles[$pagina_actual]) ? $page_titles[$pagina_actual] : ucwords(str_replace(['.php', '_'], ['', ' '], $pagina_actual));

                                        echo '<li class="breadcrumb-item active" aria-current="page">' . $friendly_name . '</li>';
                                    } else {
                                        // Optionally explicitly say Dashboard if you want "Inicio > Dashboard"
                                        // But user asked for "Dashboard or Inicio only". 
                                        // Since "Inicio" is hardcoded first li, if we do nothing here, it's just "Inicio".
                                        // If we want "Inicio > Dashboard":
                                        echo '<li class="breadcrumb-item active" aria-current="page">Dashboard</li>';
                                    }
                                    ?>
                                </ol>
                            </nav>
                        </div>
                    </div>

                    <!-- Right: Search + Notifications -->
                    <div class="d-flex align-items-center gap-4">

                        <!-- Search Bar -->
                        <div class="search-container d-none d-md-block">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="form-control search-input" id="globalSearch" placeholder="Buscar bus, trabajador...">
                            <div class="search-results" id="searchResults"></div>
                        </div>

                        <!-- Notification Bell -->
                        <div class="dropdown">
                            <a href="#" class="notification-icon" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="far fa-bell"></i>
                                <span class="badge-counter" id="notifBadge">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notifDropdown" id="notifList">
                                <li class="notification-header">Notificaciones</li>
                                <li>
                                    <div class="p-3 text-center text-muted"><small>Cargando...</small></div>
                                </li>
                            </ul>
                        </div>

                        <!-- User Profile Dropdown -->
                        <div class="dropdown ms-3 border-start ps-3">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php
                                $nombre_mostrar = 'Usuario';
                                $rol_mostrar = 'Invitado';
                                if (isset($_SESSION['user_nombre'])) {
                                    $parts = explode(' ', trim($_SESSION['user_nombre']));
                                    $nombre_mostrar = (count($parts) >= 2) ? $parts[0] . ' ' . $parts[1] : $_SESSION['user_nombre'];
                                    if (isset($_SESSION['user_role'])) $rol_mostrar = ucfirst($_SESSION['user_role']);
                                }
                                ?>
                                <div class="text-end me-2 d-none d-lg-block">
                                    <div class="fw-bold text-dark" style="font-size: 0.85rem; line-height: 1.2;"><?php echo htmlspecialchars($nombre_mostrar); ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($rol_mostrar); ?></div>
                                </div>
                                <div class="avatar-circle" style="width: 35px; height: 35px; font-size: 0.9rem; background: var(--primary-color); color: #fff; box-shadow: none;">
                                    <?php echo strtoupper(substr($nombre_mostrar, 0, 1)); ?>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/perfil.php"><i class="fas fa-user-circle me-2 text-primary"></i> Mi Perfil</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
                            </ul>
                        </div>

                    </div>
                </div>
            </nav>

            <!-- Global JS for Search and Notifications -->
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // --- Global Search Logic ---
                    const searchInput = document.getElementById('globalSearch');
                    const searchResults = document.getElementById('searchResults');
                    let timeout = null;

                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            const query = this.value.trim();
                            clearTimeout(timeout);

                            if (query.length < 2) {
                                searchResults.style.display = 'none';
                                return;
                            }

                            timeout = setTimeout(() => {
                                fetch('<?php echo BASE_URL; ?>/ajax/search_global.php?q=' + encodeURIComponent(query))
                                    .then(response => response.json())
                                    .then(data => {
                                        searchResults.innerHTML = '';
                                        if (data.length > 0) {
                                            searchResults.style.display = 'block';
                                            let currentCategory = '';
                                            data.forEach(item => {
                                                if (item.category !== currentCategory) {
                                                    const catHeader = document.createElement('div');
                                                    catHeader.className = 'search-category';
                                                    catHeader.textContent = item.category;
                                                    searchResults.appendChild(catHeader);
                                                    currentCategory = item.category;
                                                }
                                                const link = document.createElement('a');
                                                link.href = item.url;
                                                link.className = 'search-item';
                                                link.innerHTML = `<i class="fas ${item.icon} me-2 text-muted"></i> ${item.label}`;
                                                searchResults.appendChild(link);
                                            });
                                        } else {
                                            searchResults.style.display = 'block';
                                            searchResults.innerHTML = '<div class="p-3 text-center text-muted"><small>No se encontraron resultados</small></div>';
                                        }
                                    })
                                    .catch(err => console.error('Error searching:', err));
                            }, 300); // 300ms Update debounce
                        });

                        // Close search on click outside
                        document.addEventListener('click', function(e) {
                            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                                searchResults.style.display = 'none';
                            }
                        });
                    }

                    // --- Notification Logic ---
                    const notifBadge = document.getElementById('notifBadge');
                    const notifList = document.getElementById('notifList');

                    function fetchNotifications() {
                        fetch('<?php echo BASE_URL; ?>/ajax/get_notifications.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.count > 0) {
                                    notifBadge.textContent = data.count;
                                    notifBadge.style.display = 'block';

                                    // Build list
                                    let html = '<li class="notification-header">Notificaciones</li>';
                                    data.items.forEach(item => {
                                        html += `
                                        <li>
                                            <a href="${item.url}" class="notification-item">
                                                <span class="notification-title">${item.mensaje}</span>
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <span class="notification-urgent">${item.subtexto}</span>
                                                    <span class="notification-meta">${item.fecha}</span>
                                                </div>
                                            </a>
                                        </li>
                                    `;
                                    });
                                    notifList.innerHTML = html;
                                } else {
                                    notifBadge.style.display = 'none';
                                    notifList.innerHTML = '<li class="notification-header">Notificaciones</li><li><div class="p-3 text-center text-muted"><small>Sin notificaciones nuevas</small></div></li>';
                                }
                            })
                            .catch(err => console.error('Error fetching notifications:', err));
                    }

                    // Fetch initially
                    fetchNotifications();
                });
            </script>

            <main class="p-4">