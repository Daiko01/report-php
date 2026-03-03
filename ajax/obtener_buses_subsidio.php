<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$empleador_id = isset($_GET['empleador_id']) ? (int)$_GET['empleador_id'] : 0;

if ($mes <= 0 || $mes > 12 || $anio < 2000) {
    echo json_encode(['success' => false, 'message' => 'Parámetros de fecha inválidos.']);
    exit;
}

try {
    // Construir consulta base para buses que tienen liquidaciones en el mes/año o que estén activos
    // Para asegurar que solo salgan las máquinas que trabajaron ese mes:
    $sql = "SELECT DISTINCT b.id, b.numero_maquina, b.patente, e.nombre as empleador_nombre 
            FROM buses b 
            JOIN empleadores e ON b.empleador_id = e.id 
            JOIN produccion_buses pb ON pb.bus_id = b.id AND MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ? ";

    $params = [$mes, $anio];
    if ($empleador_id > 0) {
        $sql .= " WHERE b.empleador_id = ? ";
        $params[] = $empleador_id;
    }
    $sql .= " ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";

    $stmtBuses = $pdo->prepare($sql);
    $stmtBuses->execute($params);
    $buses = $stmtBuses->fetchAll(PDO::FETCH_ASSOC);

    // Obtener subsidios guardados para ese mes/año
    $stmtSub = $pdo->prepare("SELECT bus_id, monto_subsidio, descuento, descuento_gps, descuento_boleta, monto_pagar FROM subsidios_operacionales WHERE mes = ? AND anio = ?");
    $stmtSub->execute([$mes, $anio]);
    $subsidios = [];
    while ($row = $stmtSub->fetch(PDO::FETCH_ASSOC)) {
        $subsidios[$row['bus_id']] = $row;
    }

    // Combinar datos
    $resultado = [];
    foreach ($buses as $bus) {
        $subAct = isset($subsidios[$bus['id']]) ? $subsidios[$bus['id']] : null;
        $resultado[] = [
            'id' => $bus['id'],
            'numero_maquina' => $bus['numero_maquina'],
            'patente' => $bus['patente'],
            'empleador_nombre' => $bus['empleador_nombre'],
            'monto_subsidio' => $subAct ? (float)$subAct['monto_subsidio'] : '',
            'descuento_gps' => $subAct ? (float)$subAct['descuento_gps'] : '',
            'descuento_boleta' => $subAct ? (float)$subAct['descuento_boleta'] : '',
            'monto_pagar' => $subAct ? (float)$subAct['monto_pagar'] : 0
        ];
    }

    echo json_encode(['success' => true, 'data' => $resultado]);
} catch (PDOException $e) {
    error_log("Error obteniendo buses para subsidio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}
