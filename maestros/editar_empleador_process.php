<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id = (int)$_POST['id'];
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    
    // NUEVO: Recibimos ID
    $empresa_sistema_id = (int)$_POST['empresa_sistema_id'];
    
    $caja_id = !empty($_POST['caja_compensacion_id']) ? $_POST['caja_compensacion_id'] : null;
    $mutual_id = $_POST['mutual_seguridad_id'];
    $tasa_mutual = (float)$_POST['tasa_mutual'] / 100;

    // Validación básica de RUT (si lo permites editar)
    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'RUT inválido.'];
        header('Location: editar_empleador.php?id='.$id); exit;
    }
    $rut_formateado = formatearRUT($rut);

    // Verificar duplicado de RUT (excluyendo al mismo ID)
    $stmt_check = $pdo->prepare("SELECT id FROM empleadores WHERE rut = ? AND id != ?");
    $stmt_check->execute([$rut_formateado, $id]);
    if ($stmt_check->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El RUT ya está registrado por otro empleador.'];
        header('Location: editar_empleador.php?id='.$id); exit;
    }

    try {
        // TRUCO DE SEGURIDAD Y COMPATIBILIDAD:
        // Buscamos el nombre limpio correspondiente al ID seleccionado
        $stmt_sys = $pdo->prepare("SELECT nombre FROM empresas_sistema WHERE id = ?");
        $stmt_sys->execute([$empresa_sistema_id]);
        $nombre_sistema = $stmt_sys->fetchColumn(); 

        if (!$nombre_sistema) {
            throw new Exception("Empresa Sistema no válida.");
        }

        // UPDATE ACTUALIZADO: Actualiza ID y TEXTO al mismo tiempo
        $sql = "UPDATE empleadores SET 
                    rut = :rut,
                    nombre = :nombre,
                    empresa_sistema = :sis_nombre,    -- Actualizamos texto (compatibilidad)
                    empresa_sistema_id = :sis_id,     -- Actualizamos ID (lógica nueva)
                    caja_compensacion_id = :caja,
                    mutual_seguridad_id = :mutual,
                    tasa_mutual_decimal = :tasa
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':sis_nombre' => $nombre_sistema,
            ':sis_id' => $empresa_sistema_id,
            ':caja' => $caja_id,
            ':mutual' => $mutual_id,
            ':tasa' => $tasa_mutual,
            ':id' => $id
        ]);
        
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Empleador actualizado exitosamente.'];
        header('Location: gestionar_empleadores.php'); exit;

    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error BD: ' . $e->getMessage()];
        header('Location: editar_empleador.php?id='.$id); exit;
    }
}
?>