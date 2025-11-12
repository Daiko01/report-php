<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

// 2. Verificar Sesión
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 3. Cargar la librería de utilidades (para validarRUT y esUnico)
require_once dirname(__DIR__) . '/app/lib/utils.php';

// 4. Verificar que sea un POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 5. Recoger y limpiar datos
    $nombre = trim($_POST['nombre']);
    $rut = trim($_POST['rut']);
    $caja_id = !empty($_POST['caja_compensacion_id']) ? $_POST['caja_compensacion_id'] : null;
    $mutual_id = $_POST['mutual_seguridad_id'];
    // Requisito: Guardar tasa como decimal (0.0093)
    $tasa_mutual_decimal = (float)$_POST['tasa_mutual'] / 100.0;
    
    // 6. Validaciones de Backend
    
    // 6.1. Validar formato RUT (Módulo 11)
    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = [
            'type' => 'error', // Popup de error
            'message' => 'El RUT ingresado no es válido (Dígito verificador incorrecto).'
        ];
        header('Location: ' . BASE_URL . '/maestros/crear_empleador.php');
        exit;
    }
    
    // Formatear el RUT antes de guardarlo
    $rut_formateado = formatearRUT($rut);

    // 6.2. Validar Unicidad de RUT
    if (!esUnico($pdo, $rut_formateado, 'empleadores', 'rut')) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "El RUT '$rut_formateado' ya está registrado en otro empleador."
        ];
        header('Location: ' . BASE_URL . '/maestros/crear_empleador.php');
        exit;
    }
    
    // 7. Todos los chequeos OK. Guardar.
    try {
        $sql = "INSERT INTO empleadores (rut, nombre, caja_compensacion_id, mutual_seguridad_id, tasa_mutual_decimal) 
                VALUES (:rut, :nombre, :caja_id, :mutual_id, :tasa)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':caja_id' => $caja_id,
            ':mutual_id' => $mutual_id,
            ':tasa' => $tasa_mutual_decimal
        ]);
        
        // 8. Mensaje de Éxito (Tostada)
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => '¡Empleador creado exitosamente!'
        ];
        header('Location: ' . BASE_URL . '/maestros/gestionar_empleadores.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error de Base de Datos: ' . $e->getMessage()
        ];
        header('Location: ' . BASE_URL . '/maestros/crear_empleador.php');
        exit;
    }

} else {
    // Si no es POST, redirigir
    header('Location: ' . BASE_URL . '/maestros/gestionar_empleadores.php');
    exit;
}
?>