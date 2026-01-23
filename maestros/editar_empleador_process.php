<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = (int)$_POST['id'];
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);

    // SEGURIDAD: En lugar de confiar en el POST, usamos la constante global del bootstrap
    $empresa_sistema_id = ID_EMPRESA_SISTEMA;

    $caja_id = !empty($_POST['caja_compensacion_id']) ? $_POST['caja_compensacion_id'] : null;
    $mutual_id = $_POST['mutual_seguridad_id'];
    $tasa_mutual = (float)$_POST['tasa_mutual'] / 100;

    // 1. Validación básica de RUT
    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'RUT inválido.'];
        header('Location: editar_empleador.php?id=' . $id);
        exit;
    }
    $rut_formateado = formatearRUT($rut);

    // 2. Verificar duplicado de RUT (excluyendo al mismo ID y filtrando por sistema)
    $stmt_check = $pdo->prepare("SELECT id FROM empleadores WHERE rut = ? AND id != ? AND empresa_sistema_id = ?");
    $stmt_check->execute([$rut_formateado, $id, $empresa_sistema_id]);
    if ($stmt_check->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El RUT ya está registrado por otro empleador en este sistema.'];
        header('Location: editar_empleador.php?id=' . $id);
        exit;
    }

    try {
        // 3. COMPATIBILIDAD: Eliminado fetch.

        // 4. UPDATE CON SEGURIDAD: Solo actualizamos si el ID pertenece a esta empresa sistema
        $sql = "UPDATE empleadores SET 
                    rut = :rut,
                    nombre = :nombre,
                    empresa_sistema_id = :sis_id, 
                    caja_compensacion_id = :caja,
                    mutual_seguridad_id = :mutual,
                    tasa_mutual_decimal = :tasa
                WHERE id = :id AND empresa_sistema_id = :sis_id_security";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':sis_id' => $empresa_sistema_id,
            ':caja' => $caja_id,
            ':mutual' => $mutual_id,
            ':tasa' => $tasa_mutual,
            ':id' => $id,
            ':sis_id_security' => $empresa_sistema_id // Doble validación en el WHERE
        ]);

        if ($stmt->rowCount() === 0) {
            // Esto sucede si el ID no existe o si pertenece a la otra empresa
            throw new Exception("No se encontró el registro o no tienes permisos para editarlo.");
        }

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Empleador actualizado exitosamente.'];
        header('Location: gestionar_empleadores.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()];
        header('Location: editar_empleador.php?id=' . $id);
        exit;
    }
} else {
    header('Location: gestionar_empleadores.php');
    exit;
}
