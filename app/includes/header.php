<?php
// Detectar página actual
$pagina_actual = basename($_SERVER['PHP_SELF']);

// --- DEFINICIÓN DE GRUPOS PARA "ACTIVE" ---

// 1. Administración
$paginas_admin = ['gestionar_usuarios.php', 'crear_usuario.php', 'editar_usuario.php', 'gestionar_sis.php'];

// 2. Maestros
$paginas_maestros = [
    'gestionar_empleadores.php', 'crear_empleador.php', 'editar_empleador.php', 
    'gestionar_trabajadores.php', 'crear_trabajador.php', 'editar_trabajador.php', 
    'gestionar_sindicatos.php', 'editar_sindicato.php', 
    'gestionar_afps.php', 'gestionar_comisiones_afp.php',
    'gestionar_buses.php' // Agregado Buses
];

// 3. Contratos
$paginas_contratos = ['gestionar_contratos.php', 'crear_contrato.php', 'editar_contrato.php'];

// 4. Procesos Mensuales (Aportes -> Planilla -> Cierre)
$paginas_procesos = [
    'cargar_aportes.php', 'resultado_carga.php', // Aportes
    'cargar_selector.php', 'cargar_grid.php', 'generar_planilla.php', // Planilla
    'cierres_mensuales.php' // Cierre
];

// 5. Reportes
$paginas_reportes = [
    'reportes_selector.php', 
    'reportes_especiales.php', 
    'ver_excedentes.php'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Reportes</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/public/assets/css/style.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; }
        .wrapper { display: flex; width: 100%; align-items: stretch; position: relative; }
        
        /* Sidebar Styles */
        #sidebar {
            min-width: 250px; max-width: 250px; background: #343a40; color: #fff;
            transition: all 0.3s; position: fixed; top: 0; bottom: 0; left: -250px;
            height: 100vh; z-index: 1050; overflow-y: auto;
        }
        #sidebar.active { left: 0; }
        #sidebar .sidebar-header { padding: 20px; background: #2c3136; }
        #sidebar ul.components { padding: 20px 0; }
        #sidebar ul li a {
            padding: 10px 20px; font-size: 1.1em; display: block;
            color: rgba(255,255,255,0.8); border-bottom: 1px solid rgba(255,255,255,0.1);
            text-decoration: none !important;
        }
        #sidebar ul li a:hover { color: #343a40; background: #fff; }
        #sidebar ul li.active > a { color: #fff; background: #007bff; font-weight: bold; }
        #sidebar ul ul a { font-size: 0.9em !important; padding-left: 30px !important; background: #4b545c; }
        #sidebar ul ul a:hover { background: #fff !important; color: #343a40 !important; }
        
        #content { width: 100%; min-height: 100vh; transition: all 0.3s; padding-left: 0; }
        .overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7); z-index: 1040;
        }
        .topbar {
            padding: 15px 20px; background: #fff; border-bottom: 1px solid #ddd;
            display: flex; justify-content: space-between; align-items: center;
        }

        /* Desktop */
        @media (min-width: 769px) {
            #sidebar { left: 0; position: relative; height: auto; }
            #sidebar.active { left: -250px; }
            #content { width: 100%; }
        }
        /* Mobile */
        @media (max-width: 768px) {
            #sidebarCollapse { display: block; }
            #sidebar.active ~ .overlay { display: block; }
            main.p-4 { padding: 1rem !important; }
        }
    </style>
</head>
<body>

<div class="overlay"></div>

<div class="wrapper">
    <nav id="sidebar">
        <div class="sidebar-header">
            <h3>Gestión Reportes</h3>
        </div>

        <ul class="list-unstyled components">
            
            <li class="<?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            </li>
            
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <li class="<?php echo (in_array($pagina_actual, $paginas_admin)) ? 'active' : ''; ?>">
                <a href="#adminMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_admin)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                    <i class="fas fa-users-cog me-2"></i> Administración
                </a>
                <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_admin)) ? 'show' : ''; ?>" id="adminMenu">
                    <li class="<?php echo ($pagina_actual == 'gestionar_usuarios.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/admin/gestionar_usuarios.php">Gestión Usuarios</a></li>
                    <li class="<?php echo ($pagina_actual == 'gestionar_sis.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/admin/gestionar_sis.php">Registro Histórico SIS</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li class="<?php echo (in_array($pagina_actual, $paginas_maestros)) ? 'active' : ''; ?>">
                <a href="#maestrosMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_maestros)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                    <i class="fas fa-book me-2"></i> Maestros
                </a>
                <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_maestros)) ? 'show' : ''; ?>" id="maestrosMenu">
                    <li class="<?php echo ($pagina_actual == 'gestionar_empleadores.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/maestros/gestionar_empleadores.php">Empleadores</a></li>
                    <li class="<?php echo ($pagina_actual == 'gestionar_trabajadores.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/maestros/gestionar_trabajadores.php">Trabajadores</a></li>
                    <li class="<?php echo ($pagina_actual == 'gestionar_buses.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/maestros/gestionar_buses.php">Buses (Máquinas)</a></li>
                    <li class="<?php echo ($pagina_actual == 'gestionar_sindicatos.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/maestros/gestionar_sindicatos.php">Sindicatos</a></li>
                    <li class="<?php echo ($pagina_actual == 'gestionar_afps.php') ? 'active' : ''; ?>"><a href="<?php echo BASE_URL; ?>/maestros/gestionar_afps.php">AFPs</a></li>
                </ul>
            </li>

            <li class="<?php echo (in_array($pagina_actual, $paginas_contratos)) ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/contratos/gestionar_contratos.php"><i class="fas fa-file-signature me-2"></i> Gestión de Contratos</a>
            </li>

            <li class="<?php echo (in_array($pagina_actual, $paginas_procesos)) ? 'active' : ''; ?>">
                <a href="#procesosMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_procesos)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                    <i class="fas fa-cogs me-2"></i> Procesos Mensuales
                </a>
                <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_procesos)) ? 'show' : ''; ?>" id="procesosMenu">
                    <li class="<?php echo ($pagina_actual == 'cargar_aportes.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/aportes/cargar_aportes.php">1. Cargar Aportes (CSV)</a>
                    </li>
                    <li class="<?php echo ($pagina_actual == 'cargar_selector.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/planillas/cargar_selector.php">2. Generar Planilla</a>
                    </li>
                    <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin')): ?>
                    <li class="<?php echo ($pagina_actual == 'cierres_mensuales.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/planillas/cierres_mensuales.php">3. Cierre Mensual</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <li class="<?php echo (in_array($pagina_actual, $paginas_reportes)) ? 'active' : ''; ?>">
                <a href="#reportesMenu" data-bs-toggle="collapse" aria-expanded="<?php echo (in_array($pagina_actual, $paginas_reportes)) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                    <i class="fas fa-print me-2"></i> Centro de Reportes
                </a>
                <ul class="collapse list-unstyled <?php echo (in_array($pagina_actual, $paginas_reportes)) ? 'show' : ''; ?>" id="reportesMenu">
                    <li class="<?php echo ($pagina_actual == 'reportes_selector.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/reportes/reportes_selector.php">Reportes Mensuales</a>
                    </li>
                    <li class="<?php echo ($pagina_actual == 'reportes_especiales.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/reportes/reportes_especiales.php">Reportes Especiales</a>
                    </li>
                    <li class="<?php echo ($pagina_actual == 'ver_excedentes.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/aportes/ver_excedentes.php">Historial Excedentes</a>
                    </li>
                </ul>
            </li>

        </ul>
    </nav>

    <div id="content">

        <nav class="navbar navbar-expand-lg navbar-light bg-light topbar">
            <div>
                <button type="button" id="sidebarCollapse" class="btn btn-dark">
                    <i class="fas fa-align-left"></i>
                </button>
            </div>
            
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user me-2"></i>
                        <?php echo isset($_SESSION['user_nombre']) ? htmlspecialchars($_SESSION['user_nombre']) : 'Usuario'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/perfil.php">Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </nav>

        <main class="p-4">