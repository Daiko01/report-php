<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

$contrato_id = (int)$_POST['id'];
$fecha_finiquito = $_POST['fecha_finiquito'];

try {
    $sql = "UPDATE contratos SET 
                esta_finiquitado = 1,
                fecha_finiquito = :fecha
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fecha' => $fecha_finiquito,
        ':id' => $contrato_id
    ]);

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Contrato finiquitado exitosamente!'];
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
}
