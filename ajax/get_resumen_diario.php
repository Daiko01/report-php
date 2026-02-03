<?php
// ajax/get_resumen_diario.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    // 1. Filtros
    // Por defecto hoy, o lo que venga en GET
    $fecha = $_GET['fecha'] ?? date('Y-m-d');

    // 2. Query
    // Seleccionamos gastos específicos requeridos para la vista
    $sql = "SELECT 
                p.id,
                p.nro_guia,
                p.ingreso, -- Total General
                p.gasto_boletos,
                p.gasto_administracion,
                p.gasto_imposiciones,
                b.numero_maquina,
                t.nombre as conductor_nombre
            FROM produccion_buses p
            JOIN buses b ON p.bus_id = b.id
            JOIN empleadores e ON b.empleador_id = e.id
            LEFT JOIN trabajadores t ON p.conductor_id = t.id
            WHERE p.fecha = :fecha
              AND e.empresa_sistema_id = :emp_sys
            ORDER BY p.nro_guia DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'fecha' => $fecha,
        'emp_sys' => ID_EMPRESA_SISTEMA
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Formateo y Totales
    $data = [];
    $totales = [
        'boletos' => 0,
        'admin' => 0,
        'imposiciones' => 0,
        'ingreso' => 0
    ];

    foreach ($rows as $r) {
        $gBol = (int)$r['gasto_boletos'];
        $gAdm = (int)$r['gasto_administracion'];
        $gImp = (int)$r['gasto_imposiciones'];
        $ing = (int)$r['ingreso'];

        // Acumuladores
        $totales['boletos'] += $gBol;
        $totales['admin'] += $gAdm;
        $totales['imposiciones'] += $gImp;

        // Custom Total requests: Sum of Boletos + Admin + Imposiciones
        $customTotal = $gBol + $gAdm + $gImp;
        $totales['ingreso'] += $customTotal;

        $data[] = [
            'folio' => $r['nro_guia'],
            'bus' => $r['numero_maquina'],
            'conductor' => $r['conductor_nombre'] ?: 'Sin Asignar',
            'boletos' => $gBol,
            'administracion' => $gAdm,
            'imposiciones' => $gImp,
            'total' => $customTotal
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'totales' => $totales
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
