<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

// Solo administradores pueden eliminar contratos
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere rol de administrador.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$idsParam = $_POST['ids'] ?? '';
$idsArray = json_decode($idsParam, true);

if (!is_array($idsArray) || empty($idsArray)) {
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron contratos válidos.']);
    exit;
}

// Sanitizar IDs
$idsRaw = array_map('intval', $idsArray);
$ids = array_filter($idsRaw, function ($v) {
    return $v > 0;
});

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'IDs proporcionados no son válidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Crear la cadena de placeholders (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // 1. Opcional pero recomendado: Eliminar dependencias manuales (anexos)
    // Esto asegura que no queden registros huérfanos o falle por llaves foráneas.
    $sqlAnexos = "DELETE FROM anexos_contrato WHERE contrato_id IN ($placeholders)";
    $stmtAnexos = $pdo->prepare($sqlAnexos);
    $stmtAnexos->execute($ids);

    // TODO: Si un contrato tiene liquidaciones o planillas asociadas, decidir qué hacer
    // En este punto, asumiré que o no tienen dependencias restrictivas,
    // o el usuario asume esa responsabilidad.
    // Si hubieran liquidaciones con foreign key restrictiva, la eliminación fallará y el catch lo capturará, 
    // lo cual es un comportamiento de seguridad aceptable.

    // 2. Eliminar Contratos
    $sqlContratos = "DELETE FROM contratos WHERE id IN ($placeholders)";
    $stmtContratos = $pdo->prepare($sqlContratos);
    $stmtContratos->execute($ids);

    $deletedCount = $stmtContratos->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Se han eliminado $deletedCount contrato(s) exitosamente."
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Verificar si el error es de restricción de integridad (ej: liquidaciones existentes)
    if ($e->getCode() == 23000) {
        $msgError = "No se puede eliminar uno o más contratos seleccionados porque están siendo utilizados en liquidaciones o planillas generadas.";
    } else {
        $msgError = "Error en la base de datos: " . $e->getMessage();
    }

    echo json_encode(['success' => false, 'message' => $msgError]);
}
