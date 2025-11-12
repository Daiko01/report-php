<?php
// La contraseña que queremos usar
$password_a_probar = '1234567890';

echo "Probando la contraseña: " . $password_a_probar . "<br>";

// 1. Generar un nuevo hash usando tu PHP
// PASSWORD_DEFAULT es el algoritmo más fuerte que tu PHP soporta (bcrypt)
$nuevo_hash = password_hash($password_a_probar, PASSWORD_DEFAULT);

echo "Tu PHP ha generado este nuevo hash:<br>";
echo "<strong>" . $nuevo_hash . "</strong><br><br>";

// 2. Probar la verificación INMEDIATAMENTE
echo "Probando password_verify() con este nuevo hash...<br>";

if (password_verify($password_a_probar, $nuevo_hash)) {
    echo "<h2 style='color: green;'>¡ÉXITO! La verificación en este script FUNCIONÓ.</h2>";
    echo "Esto significa que password_verify() en tu servidor está OK.<br>";
    echo "El problema era el hash antiguo en la base de datos.";
} else {
    echo "<h2 style='color: red;'>¡FALLO CRÍTICO!</h2>";
    echo "Tu PHP generó un hash que él mismo no puede verificar. Esto es un error grave del entorno.";
}

echo "<hr>";
echo "<h3>Siguientes Pasos:</h3>";
echo "Si viste '¡ÉXITO!', copia el nuevo hash (el que está en negrita) y úsalo en el Paso 3.";

?>