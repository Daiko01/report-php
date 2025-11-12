<?php
echo "Paso 1: Iniciando prueba...<br>";

// Ajustar la ruta para incluir Database.php
require_once __DIR__ . '/app/core/Database.php';

echo "Paso 2: Clase Database cargada.<br>";

$database = new Database();
$pdo = $database->connect();

if ($pdo) {
    echo "Paso 3: ¡ÉXITO! Conexión a la base de datos 'reportes' establecida.<br>";
} else {
    echo "Paso 3: ¡FALLO! No se pudo conectar a la base de datos.<br>";
}
?>