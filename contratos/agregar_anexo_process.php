<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

// 1. Recoger datos
$contrato_id = (int)$_POST['contrato_id'];
$descripcion = trim($_POST['descripcion']);
$fecha_anexo = $_POST['fecha_anexo'];
$nuevo_sueldo = !empty($_POST['nuevo_sueldo']) ? (int)$_POST['nuevo_sueldo'] : null;
$nueva_fecha_termino = !empty($_POST['nueva_fecha_termino']) ? $_POST['nueva_fecha_termino'] : null;

$pdo->beginTransaction();
try {
    // 2. Guardar el registro del anexo
    $sql_anexo = "INSERT INTO anexos_contrato (contrato_id, fecha_anexo, descripcion, nuevo_sueldo, nueva_fecha_termino)
                  VALUES (:cid, :fecha, :desc, :sueldo, :f_termino)";
    $stmt_anexo = $pdo->prepare($sql_anexo);
    $stmt_anexo->execute([
        ':cid' => $contrato_id,
        ':fecha' => $fecha_anexo,
        ':desc' => $descripcion,
        ':sueldo' => $nuevo_sueldo,
        ':f_termino' => $nueva_fecha_termino
    ]);

    // 3. Actualizar el contrato principal (si aplica)
    if ($nuevo_sueldo !== null) {
        $pdo->prepare("UPDATE contratos SET sueldo_imponible = ? WHERE id = ?")
            ->execute([$nuevo_sueldo, $contrato_id]);
    }
    if ($nueva_fecha_termino !== null) {
        $pdo->prepare("UPDATE contratos SET fecha_termino = ? WHERE id = ?")
            ->execute([$nueva_fecha_termino, $contrato_id]);
    }

    $pdo->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Anexo agregado y contrato actualizado!'];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
}
