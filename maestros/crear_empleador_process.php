<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    
    // NUEVO: Recibimos el ID
    $empresa_sistema_id = (int)$_POST['empresa_sistema_id'];
    
    $caja_id = !empty($_POST['caja_compensacion_id']) ? $_POST['caja_compensacion_id'] : null;
    $mutual_id = $_POST['mutual_seguridad_id'];
    $tasa_mutual = (float)$_POST['tasa_mutual'] / 100; // Convertir 0.93 a 0.0093

    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'RUT inválido.'];
        header('Location: crear_empleador.php'); exit;
    }
    $rut_formateado = formatearRUT($rut);

    if (!esUnico($pdo, $rut_formateado, 'empleadores', 'rut')) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El RUT ya existe.'];
        header('Location: crear_empleador.php'); exit;
    }

    try {
        // TRUCO DE COMPATIBILIDAD:
        // Buscamos el nombre del sistema (Ej: "BUSES BP") usando el ID para guardar ambos.
        // Esto mantiene felices a los reportes viejos (texto) y al sistema nuevo (ID).
        $stmt_sys = $pdo->prepare("SELECT nombre FROM empresas_sistema WHERE id = ?");
        $stmt_sys->execute([$empresa_sistema_id]);
        $nombre_sistema = $stmt_sys->fetchColumn(); 

        if (!$nombre_sistema) {
            throw new Exception("Empresa Sistema no válida.");
        }

        // INSERT ACTUALIZADO: Guarda ID y TEXTO
        $sql = "INSERT INTO empleadores 
                (rut, nombre, empresa_sistema, empresa_sistema_id, caja_compensacion_id, mutual_seguridad_id, tasa_mutual_decimal) 
                VALUES 
                (:rut, :nombre, :sis_nombre, :sis_id, :caja, :mutual, :tasa)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':sis_nombre' => $nombre_sistema, // Texto legado
            ':sis_id' => $empresa_sistema_id, // ID Nuevo
            ':caja' => $caja_id,
            ':mutual' => $mutual_id,
            ':tasa' => $tasa_mutual
        ]);
        
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Empleador creado exitosamente.'];
        header('Location: gestionar_empleadores.php'); exit;

    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error BD: ' . $e->getMessage()];
        header('Location: crear_empleador.php'); exit;
    }
}
?>