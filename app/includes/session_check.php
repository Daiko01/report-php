<?php
// Este archivo asume que 'bootstrap.php' ya fue cargado
// (lo cual inicia la sesión)

$pagina_actual = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['user_id']) && $pagina_actual != 'login.php' && $pagina_actual != 'login_process.php') {
    // Si no hay sesión y no estamos en login, redirigir a login
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id']) && $pagina_actual == 'login.php') {
    // Si ya hay sesión y estamos en login, redirigir al dashboard
    header('Location: index.php');
    exit;
}
?>