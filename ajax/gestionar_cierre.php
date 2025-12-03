<?php
// ajax/gestionar_cierre.php (Corregido - sin 'eliminar')
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!in_array($_SESSION['user_role'], ['admin', 'contador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['accion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no especificada.']);
    exit;
}

$accion = $data['accion'];

try {

    // Acción 1: Consultar el estado
    if ($accion == 'consultar') {
        if (!isset($data['empleador_id']) || !isset($data['mes']) || !isset($data['ano'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos para consultar.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT esta_cerrado FROM cierres_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
        $stmt->execute(['eid' => $data['empleador_id'], 'mes' => $data['mes'], 'ano' => $data['ano']]);
        $cierre = $stmt->fetch();
        $esta_cerrado = ($cierre && $cierre['esta_cerrado'] == 1);
        echo json_encode(['success' => true, 'esta_cerrado' => $esta_cerrado]);
        exit;
    }

    // Acción 2: Actualizar el estado (Cerrar/Reabrir)
    if ($accion == 'actualizar') {
        if (!isset($data['empleador_id']) || !isset($data['mes']) || !isset($data['ano']) || !isset($data['nuevo_estado'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos para actualizar.']);
            exit;
        }
        $sql = "INSERT INTO cierres_mensuales (empleador_id, mes, ano, esta_cerrado)
                VALUES (:eid, :mes, :ano, :estado)
                ON DUPLICATE KEY UPDATE esta_cerrado = VALUES(esta_cerrado)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':eid' => $data['empleador_id'],
            ':mes' => $data['mes'],
            ':ano' => $data['ano'],
            ':estado' => $data['nuevo_estado']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Si la acción no es válida
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
