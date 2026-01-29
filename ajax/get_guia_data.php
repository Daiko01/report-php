<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$guia_id = (int)$_GET['id'];

try {
    // 1. Fetch Header
    $stmt = $pdo->prepare("
        SELECT p.*, b.numero_maquina, b.patente, e.nombre as empleador, IFNULL(t.nombre, 'Sin Conductor') as conductor, t.rut as conductor_rut 
        FROM produccion_buses p
        JOIN buses b ON p.bus_id = b.id
        JOIN empleadores e ON b.empleador_id = e.id
        LEFT JOIN trabajadores t ON p.conductor_id = t.id
        WHERE p.id = ?
    ");
    $stmt->execute([$guia_id]);
    $guia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guia) throw new Exception("Guía no encontrada");

    // 2. Fetch Details
    $stmtDet = $pdo->prepare("SELECT * FROM produccion_detalle_boletos WHERE guia_id = ?");
    $stmtDet->execute([$guia_id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $pecera = [];
    foreach ($detalles as $d) {
        $pecera[] = [
            'tarifa' => $d['tarifa'],
            'inicio' => $d['folio_inicio'],
            'fin' => $d['folio_fin'],
            'monto' => $d['monto_total']
        ];
    }

    // 3. Format Response
    $total_gastos = $guia['gasto_administracion'] + $guia['gasto_petroleo'] + $guia['gasto_boletos'] + $guia['gasto_aseo'] + $guia['gasto_viatico'] + $guia['gasto_varios'];
    $liquido = $guia['ingreso'] - $total_gastos;

    $data = [
        'id' => $guia['id'],
        'fecha' => date('d/m/Y', strtotime($guia['fecha'])),
        'hora' => '', // We don't store time
        'nro_guia' => $guia['nro_guia'],
        'bus' => $guia['numero_maquina'],
        'patente' => $guia['patente'],
        'empleador' => $guia['empleador'],
        'conductor' => $guia['conductor'] . ($guia['conductor_rut'] ? ' (' . $guia['conductor_rut'] . ')' : ''),
        'ingreso' => number_format($guia['ingreso'], 0, ',', '.'),
        'prop_conductor' => number_format($guia['pago_conductor'], 0, ',', '.'),
        'liquido' => number_format($liquido, 0, ',', '.'),
        'empresa_fantasia' => NOMBRE_SISTEMA,
        'direccion_empresa' => 'Oficina Central',
        'pecera' => $pecera,
        'gastos' => [
            ['label' => 'Administración', 'value' => number_format($guia['gasto_administracion'], 0, ',', '.')],
            ['label' => 'Petróleo', 'value' => number_format($guia['gasto_petroleo'], 0, ',', '.')],
            ['label' => 'Boletos', 'value' => number_format($guia['gasto_boletos'], 0, ',', '.')],
            ['label' => 'Aseo', 'value' => number_format($guia['gasto_aseo'], 0, ',', '.')],
            ['label' => 'Viático', 'value' => number_format($guia['gasto_viatico'], 0, ',', '.')],
            ['label' => 'Varios', 'value' => number_format($guia['gasto_varios'], 0, ',', '.')]
        ]
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
