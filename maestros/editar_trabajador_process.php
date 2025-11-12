<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php'; // Para esUnico

// 2. Verificar que sea un POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 3. Recoger y limpiar datos
    $id = $_POST['id'];
    // El RUT no se edita, no lo leemos del POST.
    
    $estado_previsional = trim($_POST['estado_previsional']);
    
    $afp_id = !empty($_POST['afp_id']) ? $_POST['afp_id'] : null;
    $sindicato_id = !empty($_POST['sindicato_id']) ? $_POST['sindicato_id'] : null;

    $tiene_cargas = isset($_POST['tiene_cargas']) ? 1 : 0;
    $numero_cargas = ($tiene_cargas == 1) ? (int)$_POST['numero_cargas'] : 0;
    
    // 4. Validaciones de Backend
    
    // 4.1. Lógica de AFP (Si es Activo, NO PUEDE ser nulo)
    if ($estado_previsional == 'Activo' && $afp_id == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Un trabajador 'Activo' debe tener una AFP seleccionada."];
        header('Location: ' . BASE_URL . '/maestros/editar_trabajador.php?id=' . $id);
        exit;
    }
    
    // Si es Pensionado, forzar AFP a NULL
    if ($estado_previsional == 'Pensionado') {
        $afp_id = null;
    }

    // 5. Todos los chequeos OK. Actualizar.
    try {
        $sql = "UPDATE trabajadores SET  
                    estado_previsional = :estado, 
                    afp_id = :afp, 
                    sindicato_id = :sindicato, 
                    tiene_cargas = :tiene, 
                    numero_cargas = :numero
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':estado' => $estado_previsional,
            ':afp' => $afp_id,
            ':sindicato' => $sindicato_id,
            ':tiene' => $tiene_cargas,
            ':numero' => $numero_cargas,
            ':id' => $id
        ]);
        
        // 6. Mensaje de Éxito
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Trabajador actualizado exitosamente!'];
        header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de Base de Datos: ' . $e->getMessage()];
        header('Location: ' . BASE_URL . '/maestros/editar_trabajador.php?id=' . $id);
        exit;
    }

} else {
    // Si no es POST
    header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
    exit;
}
?>