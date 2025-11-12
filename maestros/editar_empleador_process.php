<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Verificar que sea un POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 3. Recoger y limpiar datos
    $id = $_POST['id'];
    $caja_id = !empty($_POST['caja_compensacion_id']) ? $_POST['caja_compensacion_id'] : null;
    $mutual_id = $_POST['mutual_seguridad_id'];
    $tasa_mutual_decimal = (float)$_POST['tasa_mutual'] / 100.0;
    
    // El RUT no se valida porque no se puede cambiar.
    
    // 4. Actualizar.
    try {
        $sql = "UPDATE empleadores SET 
                    caja_compensacion_id = :caja_id, 
                    mutual_seguridad_id = :mutual_id, 
                    tasa_mutual_decimal = :tasa
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':caja_id' => $caja_id,
            ':mutual_id' => $mutual_id,
            ':tasa' => $tasa_mutual_decimal,
            ':id' => $id
        ]);
        
        // 5. Mensaje de Éxito (Tostada)
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => '¡Empleador actualizado exitosamente!'
        ];
        header('Location: ' . BASE_URL . '/maestros/gestionar_empleadores.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error de Base de Datos: ' . $e->getMessage()
        ];
        header('Location: ' . BASE_URL . '/maestros/editar_empleador.php?id=' . $id);
        exit;
    }

} else {
    // Si no es POST, redirigir
    header('Location: ' . BASE_URL . '/maestros/gestionar_empleadores.php');
    exit;
}
?>