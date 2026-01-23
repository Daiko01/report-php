<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // 1. Recolección y Limpieza de Datos
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);

    // SEGURIDAD: En lugar de confiar en el POST, usamos la constante global definida en bootstrap.php
    $empresa_sistema_id = ID_EMPRESA_SISTEMA;

    $caja_id = !empty($_POST['caja_compensacion_id']) ? $_POST['caja_compensacion_id'] : null;
    $mutual_id = $_POST['mutual_seguridad_id'];
    $tasa_mutual = (float)$_POST['tasa_mutual'] / 100; // Convertir 0.93 a 0.0093

    // 2. Validaciones de RUT (Mantenemos tus utilidades intactas)
    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'RUT inválido.'];
        header('Location: crear_empleador.php');
        exit;
    }
    $rut_formateado = formatearRUT($rut);

    if (!esUnico($pdo, $rut_formateado, 'empleadores', 'rut')) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El RUT ya existe en el sistema.'];
        header('Location: crear_empleador.php');
        exit;
    }

    try {
        // 3. COMPATIBILIDAD LEGADA: Eliminado fetch de nombre texto.
        // La identidad se maneja exclusivamente por ID ahora.

        // 4. INSERT FINAL: Solo ID
        $sql = "INSERT INTO empleadores 
                (rut, nombre, empresa_sistema_id, caja_compensacion_id, mutual_seguridad_id, tasa_mutual_decimal) 
                VALUES 
                (:rut, :nombre, :sis_id, :caja, :mutual, :tasa)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':sis_id' => $empresa_sistema_id,       // Campo int
            ':caja' => $caja_id,
            ':mutual' => $mutual_id,
            ':tasa' => $tasa_mutual
        ]);

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Empleador creado exitosamente.'];
        header('Location: gestionar_empleadores.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de Base de Datos: ' . $e->getMessage()];
        header('Location: crear_empleador.php');
        exit;
    }
} else {
    // Si intentan entrar por URL sin POST
    header('Location: gestionar_empleadores.php');
    exit;
}
