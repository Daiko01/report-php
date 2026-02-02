<?php
// ajax/buscar_guias.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Silenciar errores para evitar corromper JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // 1. Capturar Filtros
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    $empleador_id = !empty($_GET['empleador_id']) ? (int)$_GET['empleador_id'] : null;
    $bus_id = !empty($_GET['bus_id']) ? (int)$_GET['bus_id'] : null;
    $conductor_id = !empty($_GET['conductor_id']) ? (int)$_GET['conductor_id'] : null;

    // 2. Construir Query Dinámica
    $sql = "SELECT 
                p.id, 
                p.fecha, 
                p.nro_guia, 
                p.ingreso,
                p.estado,
                p.bus_id,
                b.numero_maquina, 
                b.patente,
                e.nombre as empleador_nombre,
                t.nombre as conductor_nombre,
                (p.ingreso - (
                    COALESCE(p.gasto_administracion, 0) + 
                    COALESCE(p.gasto_petroleo, 0) + 
                    COALESCE(p.gasto_boletos, 0) + 
                    COALESCE(p.gasto_aseo, 0) + 
                    COALESCE(p.gasto_viatico, 0) + 
                    COALESCE(p.gasto_varios, 0) +
                    COALESCE(p.gasto_cta_extra, 0) +
                    COALESCE(p.gasto_imposiciones, 0)
                )) as liquido
            FROM produccion_buses p
            JOIN buses b ON p.bus_id = b.id
            JOIN empleadores e ON b.empleador_id = e.id
            LEFT JOIN trabajadores t ON p.conductor_id = t.id
            WHERE p.fecha BETWEEN :fi AND :ff";

    $params = [
        'fi' => $fecha_inicio,
        'ff' => $fecha_fin
    ];

    if ($empleador_id) {
        $sql .= " AND b.empleador_id = :emp_id";
        $params['emp_id'] = $empleador_id;
    }

    if ($bus_id) {
        $sql .= " AND p.bus_id = :bus_id";
        $params['bus_id'] = $bus_id;
    }

    if ($conductor_id) {
        $sql .= " AND p.conductor_id = :cond_id";
        $params['cond_id'] = $conductor_id;
    }

    $sql .= " ORDER BY p.fecha DESC, p.nro_guia DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Post-Procesamiento (Estado Cerrado del Mes)
    // Para saber si se puede editar/reabrir, necesitamos saber si el MES fiscal de la máquina está cerrado.
    // Esto es más eficiente hacerlo en PHP si son pocos registros, o JOIN si son muchos.
    // Usaremos un cache simple de cierres para no consultar por cada fila si se repite bus/mes.

    $cierres_cache = []; // Key: bus_id-mes-anio -> Value: bool (isClosed)

    $data = [];
    foreach ($guias as $g) {
        $mes = date('n', strtotime($g['fecha']));
        $ano = date('Y', strtotime($g['fecha']));
        $cacheKey = "{$g['bus_id']}-{$mes}-{$ano}";

        if (!isset($cierres_cache[$cacheKey])) {
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ? AND estado = 'Cerrado'");
            $stmtC->execute([$g['bus_id'], $mes, $ano]);
            $cierres_cache[$cacheKey] = ($stmtC->fetchColumn() > 0);
        }

        $mes_cerrado = $cierres_cache[$cacheKey];
        $editable = !$mes_cerrado && ($g['estado'] !== 'Cerrada');

        // Roles
        $role = $_SESSION['user_role'] ?? '';
        $can_reopen = ($role === 'admin' || $role === 'contador');

        $data[] = [
            'id' => $g['id'],
            'fecha' => date('d/m/Y', strtotime($g['fecha'])),
            'folio' => $g['nro_guia'],
            'bus' => "Nº " . $g['numero_maquina'],
            'patente' => $g['patente'],
            'empleador' => $g['empleador_nombre'],
            'conductor' => $g['conductor_nombre'] ?: 'Sin Asignar',
            'ingreso' => number_format($g['ingreso'], 0, ',', '.'),
            'liquido' => number_format($g['liquido'], 0, ',', '.'),
            'estado' => $g['estado'],
            'mes_cerrado' => $mes_cerrado,
            'can_edit' => $editable,
            'can_reopen' => $can_reopen
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
