<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

// 2. Verificar que sea un POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 3. Recoger y limpiar datos
    $nombre = trim($_POST['nombre']);
    $rut = trim($_POST['rut']);
    $estado_previsional = trim($_POST['estado_previsional']);
    
    // Campos que pueden ser NULL
    $afp_id = !empty($_POST['afp_id']) ? $_POST['afp_id'] : null;
    $sindicato_id = !empty($_POST['sindicato_id']) ? $_POST['sindicato_id'] : null;

    // Campos condicionales
    $tiene_cargas = isset($_POST['tiene_cargas']) ? 1 : 0;
    $numero_cargas = ($tiene_cargas == 1) ? (int)$_POST['numero_cargas'] : 0;
    
    // 4. Validaciones de Backend
    
    // 4.1. Validar formato RUT
    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El RUT ingresado no es válido.'];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }
    
    $rut_formateado = formatearRUT($rut);

    // 4.2. Validar Unicidad de RUT
    if (!esUnico($pdo, $rut_formateado, 'trabajadores', 'rut')) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "El RUT '$rut_formateado' ya está registrado en otro trabajador."];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }
    
    // 4.3. Lógica de AFP (Si es Activo, NO PUEDE ser nulo)
    if ($estado_previsional == 'Activo' && $afp_id == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Un trabajador 'Activo' debe tener una AFP seleccionada."];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }
    
    // Si es Pensionado, forzar AFP a NULL
    if ($estado_previsional == 'Pensionado') {
        $afp_id = null;
    }

    // 5. Todos los chequeos OK. Guardar.
    try {
        $sql = "INSERT INTO trabajadores (rut, nombre, estado_previsional, afp_id, sindicato_id, tiene_cargas, numero_cargas) 
                VALUES (:rut, :nombre, :estado, :afp, :sindicato, :tiene, :numero)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':estado' => $estado_previsional,
            ':afp' => $afp_id,
            ':sindicato' => $sindicato_id,
            ':tiene' => $tiene_cargas,
            ':numero' => $numero_cargas
        ]);
        
        // 6. Mensaje de Éxito
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Trabajador creado exitosamente!'];
        header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de Base de Datos: ' . $e->getMessage()];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }

} else {
    // Si no es POST
    header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
    exit;
}
?>