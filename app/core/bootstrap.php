<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

// Iniciar la sesión en todas las páginas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definir la ruta base del proyecto para includes fáciles
define('BASE_PATH', dirname(__DIR__, 2)); // Sube 2 niveles (de core -> app -> reportes)

$folder_name = basename(BASE_PATH);

// Definir constante BASE_URL si no está definida en .env
if (!defined('BASE_URL')) {
    define('BASE_URL', $_ENV['BASE_URL'] ?? '/' . $folder_name);
}

// Cargar la clase de la Base de Datos
require_once BASE_PATH . '/app/core/Database.php';
require_once BASE_PATH . '/app/includes/csrf.php';

// Crear instancia de la BD y conectar
$database = new Database();
$pdo = $database->connect(); // $pdo será nuestra variable global de conexión

// Configuración de zona horaria (Importante para fechas)
date_default_timezone_set('America/Santiago');
