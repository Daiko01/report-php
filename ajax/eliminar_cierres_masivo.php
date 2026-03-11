<?php
// ajax/eliminar_cierres_masivo.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere rol de administrador.']);
    exit;
}

$mes = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : 0;
$ids_json = $_POST['buses'] ?? '[]';
$buses_ids = json_decode($ids_json, true);

if ($mes <= 0 || $anio <= 0 || !is_array($buses_ids) || empty($buses_ids)) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes o inválidos para proceder con la eliminación.']);
    exit;
}

// Limpiar IDs
$buses_ids = array_map('intval', $buses_ids);
$buses_ids = array_filter($buses_ids, function ($id) {
    return $id > 0;
});

if (empty($buses_ids)) {
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron buses válidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $inPart = implode(',', array_fill(0, count($buses_ids), '?'));

    // 1. ELIMINAR CIERRES MAQUINAS
    $sqlCierres = "DELETE FROM cierres_maquinas WHERE mes = ? AND anio = ? AND bus_id IN ($inPart)";
    $paramsCierres = array_merge([$mes, $anio], $buses_ids);
    $stmt1 = $pdo->prepare($sqlCierres);
    $stmt1->execute($paramsCierres);
    $cierresBorrados = $stmt1->rowCount();

    // 2. ELIMINAR GUIAS DE PRODUCCION
    $sqlGuias = "DELETE FROM produccion_buses WHERE MONTH(fecha) = ? AND YEAR(fecha) = ? AND bus_id IN ($inPart)";
    $paramsGuias = array_merge([$mes, $anio], $buses_ids);
    $stmt2 = $pdo->prepare($sqlGuias);
    $stmt2->execute($paramsGuias);
    $guiasBorradas = $stmt2->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Operación exitosa. Se eliminaron $cierresBorrados registro(s) de cierre y un total de $guiasBorradas guía(s) asociadas."
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
