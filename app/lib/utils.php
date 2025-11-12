<?php
/**
 * Valida un RUT chileno (algoritmo Módulo 11)
 *
 * @param string $rut RUT en formato 12.345.678-K o 12345678K
 * @return boolean True si es válido, False si no.
 */
function validarRUT($rut) {
    // Limpiar RUT
    $rut = preg_replace('/[^0-9kK]/', '', $rut);
    if (strlen($rut) < 2) {
        return false;
    }

    $dv = strtoupper(substr($rut, -1));
    $cuerpo = substr($rut, 0, -1);

    // Validar formato básico
    if (!ctype_digit($cuerpo) || ($dv != 'K' && !ctype_digit($dv))) {
        return false;
    }

    // Algoritmo Módulo 11
    $suma = 0;
    $multiplo = 2;

    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += $cuerpo[$i] * $multiplo;
        $multiplo = ($multiplo == 7) ? 2 : $multiplo + 1;
    }

    $resto = $suma % 11;
    $dvCalculado = 11 - $resto;

    if ($dvCalculado == 11) {
        $dvCalculado = '0';
    } elseif ($dvCalculado == 10) {
        $dvCalculado = 'K';
    }

    return (string)$dvCalculado === $dv;
}

/**
 * Función de unicidad (Ejemplo para verificar RUT/Username de usuario)
 *
 * @param PDO $pdo Conexión a la BD
 * @param string $valor Valor a verificar
 * @param string $tabla Tabla (users, empleadores, trabajadores)
 * @param string $columna Columna (username, rut)
 * @param int|null $idExcluir ID a excluir (para editar)
 * @return boolean True si es único, False si ya existe.
 */
function esUnico($pdo, $valor, $tabla, $columna, $idExcluir = null) {
    $sql = "SELECT id FROM $tabla WHERE $columna = :valor";
    $params = [':valor' => $valor];
    
    if ($idExcluir !== null) {
        $sql .= " AND id != :id";
        $params[':id'] = $idExcluir;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() === false; // True si no encontró nada (es único)
}

/**
 * Formatea un RUT (ej: 123456789 -> 12.345.678-9)
 */
function formatearRUT($rut) {
    $rut = preg_replace('/[^0-9kK]/', '', $rut);
    if(strlen($rut) < 2) return $rut;

    $dv = strtoupper(substr($rut, -1));
    $cuerpo = substr($rut, 0, -1);
    
    // Formatear cuerpo con puntos
    $cuerpo_formateado = number_format($cuerpo, 0, ',', '.');
    
    return $cuerpo_formateado . '-' . $dv;
}
?>