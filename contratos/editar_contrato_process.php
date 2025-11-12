<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

// 1. Recoger datos
$contrato_id = (int)$_POST['id'];
$sueldo_imponible = (int)$_POST['sueldo_imponible'];
$tipo_contrato = $_POST['tipo_contrato'];
$es_part_time = isset($_POST['es_part_time']) ? 1 : 0;
// (Las fechas no se editan aquí, se editan con ANEXOS)

// 2. Validaciones (Regla Part Time)
if ($es_part_time == 1 && $tipo_contrato != 'Fijo') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: El Part-Time solo puede seleccionarse para contratos de Plazo Fijo.'];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
}

// 3. Guardar
try {
    $sql = "UPDATE contratos SET 
                sueldo_imponible = :sueldo,
                tipo_contrato = :tipo,
                es_part_time = :part_time
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sueldo' => $sueldo_imponible,
        ':tipo' => $tipo_contrato,
        ':part_time' => $es_part_time,
        ':id' => $contrato_id
    ]);

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Contrato actualizado!'];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
}
