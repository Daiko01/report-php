<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$mes = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : 0;
$subsidios = isset($_POST['subsidios']) ? $_POST['subsidios'] : [];

if ($mes <= 0 || $mes > 12 || $anio < 2000 || empty($subsidios)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o faltantes.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO subsidios_operacionales (bus_id, mes, anio, monto_subsidio, descuento_gps, descuento_boleta) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            monto_subsidio = VALUES(monto_subsidio),
            descuento_gps = VALUES(descuento_gps),
            descuento_boleta = VALUES(descuento_boleta)
    ");

    $count = 0;
    foreach ($subsidios as $bus_id => $datos) {
        $monto = isset($datos['monto']) ? (float)$datos['monto'] : 0;
        $desc_gps = isset($datos['descuento_gps']) ? (float)$datos['descuento_gps'] : 0;
        $desc_boleta = isset($datos['descuento_boleta']) ? (float)$datos['descuento_boleta'] : 0;

        $stmt->execute([$bus_id, $mes, $anio, $monto, $desc_gps, $desc_boleta]);
        $count++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Se guardaron los subsidios para $count buses exitosamente."]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error guardando subsidios: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al guardar los subsidios.']);
}
