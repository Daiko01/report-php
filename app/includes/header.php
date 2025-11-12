<?php
// Este archivo asume que bootstrap.php (que define BASE_URL) ya fue cargado
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reportes</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <link href="<?php echo BASE_URL; ?>/public/assets/css/style.css" rel="stylesheet">

    <style>
        /* (El CSS del style... va aquí) */
        body {
            background-color: #f4f7f6;
        }

        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            /* Dark */
            color: #fff;
            transition: all 0.3s;
            min-height: 100vh;
        }

        #sidebar.active {
            margin-left: -250px;
        }

        #sidebar .sidebar-header {
            padding: 20px;
            background: #2c3136;
        }

        #sidebar ul.components {
            padding: 20px 0;
        }

        #sidebar ul p {
            color: #fff;
            padding: 10px;
        }

        #sidebar ul li a {
            padding: 10px 20px;
            font-size: 1.1em;
            display: block;
            color: rgba(255, 255, 255, 0.8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        #sidebar ul li a:hover {
            color: #343a40;
            background: #fff;
        }

        #sidebar ul li.active>a {
            color: #fff;
            background: #007bff;
        }

        #content {
            width: 100%;
            padding: 0;
            min-height: 100vh;
            transition: all 0.3s;
        }

        .topbar {
            padding: 15px 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Gestión Reportes</h3>
            </div>

            <ul class="list-unstyled components">
                <li class="active">
                    <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                </li>

                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                    <li>
                        <a href="#adminMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                            <i class="fas fa-users-cog me-2"></i> Administración
                        </a>
                        <ul class="collapse list-unstyled" id="adminMenu">
                            <li><a href="<?php echo BASE_URL; ?>/admin/gestionar_usuarios.php">Gestión Usuarios</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <li>
                    <a href="#maestrosMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                        <i class="fas fa-book me-2"></i> Gestionar
                    </a>
                    <ul class="collapse list-unstyled" id="maestrosMenu">
                        <li><a href="<?php echo BASE_URL; ?>/maestros/gestionar_empleadores.php">Empleadores</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/maestros/gestionar_trabajadores.php">Trabajadores</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/maestros/gestionar_sindicatos.php">Sindicatos</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/maestros/gestionar_afps.php">AFPs (Comisiones)</a></li>
                    </ul>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/contratos/gestionar_contratos.php"><i class="fas fa-file-signature me-2"></i> Gestión de Contratos</a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/planillas/cargar_selector.php"><i class="fas fa-file-invoice me-2"></i>Generar Reporte</a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/reportes/reportes_selector.php"><i class="fas fa-file-pdf me-2"></i>Ver Reportes</a>
                </li>
                <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'contador')): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/planillas/cierres_mensuales.php"><i class="fas fa-lock me-2"></i> Cierre Mensual</a>
                    </li>
                <?php endif; ?>
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
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <main class="p-4">