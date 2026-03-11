<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

// Validar que el usuario sea administrador
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere rol de administrador.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_anexo = isset($_POST['id_anexo']) ? (int)$_POST['id_anexo'] : 0;
$id_contrato = isset($_POST['id_contrato']) ? (int)$_POST['id_contrato'] : 0;

if ($id_anexo <= 0 || $id_contrato <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit;
}

try {
    // Verificar que el anexo existe y pertenece al contrato indicado
    $stmt = $pdo->prepare("SELECT id FROM anexos_contrato WHERE id = ? AND contrato_id = ?");
    $stmt->execute([$id_anexo, $id_contrato]);
    $anexo = $stmt->fetch();

    if (!$anexo) {
        echo json_encode(['success' => false, 'message' => 'El anexo no existe o no corresponde a este contrato.']);
        exit;
    }

    $pdo->beginTransaction();

    // Eliminar el anexo
    $stmtDelete = $pdo->prepare("DELETE FROM anexos_contrato WHERE id = ?");
    $stmtDelete->execute([$id_anexo]);

    // Opcional: El contrato principal mantendrá la información que tenía luego de crear el anexo.
    // Esto significa que si se borra el anexo, no se le "resta" el sueldo al contrato de forma automática.
    // Dado que restaurar el estado anterior puede ser complejo si hubo múltiples anexos traslapados,
    // se le advirtió al usuario en la alerta JS que el contrato base requerirá modificación manual 
    // si el anexo contenía cambios de sueldo o tipo de contrato.

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Anexo eliminado permanentemente del historial.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
