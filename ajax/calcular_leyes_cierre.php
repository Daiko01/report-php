<?php
// ajax/calcular_leyes_cierre.php
// SILENCIAR ERRORES DE PHP QUE ROMPEN EL JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start(); // Iniciar buffer para capturar cualquier output no deseado

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php';

// Limpiar buffer antes de enviar headers
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$bus_id = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : 0;
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;

if (!$bus_id || !$mes || !$anio) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

try {
    // 1. Obtener Ingreso Total del Bus en el Mes
    $stmt = $pdo->prepare("SELECT SUM(ingreso) as total_ingreso FROM produccion_buses WHERE bus_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$bus_id, $mes, $anio]);
    $total_ingreso = (int)$stmt->fetchColumn();

    // --- VALIDACIÓN: PROCESO ASIGNADO (Leyes Sociales) ---
    // Verificar si este bus es el pagador asignado para su empleador en este mes
    $stmtBusOwner = $pdo->prepare("SELECT empleador_id FROM buses WHERE id = ?");
    $stmtBusOwner->execute([$bus_id]);
    $id_empleador_bus = $stmtBusOwner->fetchColumn();

    if ($id_empleador_bus) {
        $stmtConfig = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual 
                                     WHERE empleador_id = ? AND mes = ? AND anio = ?");
        $stmtConfig->execute([$id_empleador_bus, $mes, $anio]);
        $bus_pagador_asignado = $stmtConfig->fetchColumn();

        // Si existe configuración y el bus actual NO es el asignado
        if ($bus_pagador_asignado && $bus_pagador_asignado != $bus_id) {
            // Buscamos info del bus asignado para mensaje más claro
            $stmtInfo = $pdo->prepare("SELECT numero_maquina, patente FROM buses WHERE id = ?");
            $stmtInfo->execute([$bus_pagador_asignado]);
            $infoBus = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            $strBus = $infoBus ? "Bus Nº {$infoBus['numero_maquina']} ({$infoBus['patente']})" : "ID $bus_pagador_asignado";

            throw new Exception("ESTA MÁQUINA NO ESTÁ HABILITADA PARA COBRAR LEYES SOCIALES.\n\nEl bus asignado para este empleador en este mes es: $strBus.");
        }

        if (!$bus_pagador_asignado) {
            throw new Exception("ESTA MÁQUINA NO ESTÁ HABILITADA PARA COBRAR LEYES SOCIALES.\n\nNo se ha configurado ningún bus pagador para este empleador en el mes seleccionado. Vaya a Configuración.");
        }
    }

    // 2. Calcular Leyes Sociales usando la Lógica Centralizada (Soporta múltiples conductores/planillas)
    require_once dirname(__DIR__) . '/app/includes/calculos_cierre.php';

    // Llamamos a la función que ya hace todo el trabajo (y que usa la nueva "Generate Mass" data)
    $resultado_cierre = calcular_cierre_bus($pdo, $bus_id, $mes, $anio);

    // Si el monto es 0, podría ser que no se han generado planillas o no es pagador.
    // La función calcular_cierre_bus maneja internamente si es pagador o no (retorna 0 si no lo es).

    // Extraemos detalles para el JSON
    $total_final = $resultado_cierre['monto_leyes_sociales'];
    $detalle = $resultado_cierre['detalle'] ?? [
        'descuentos_trabajador' => 0,
        'sindicato' => 0,
        'costos_empleador' => 0,
        'asignacion_familiar' => 0
    ];
    $lista_trabajadores = $resultado_cierre['lista_trabajadores'] ?? [];

    // Para mostrar nombres de conductores involucrados (Visual only) - REEMPLAZADO POR TABLA
    $conductores_str = count($lista_trabajadores) . " Trabajadores";

    echo json_encode([
        'success' => true,
        'bus_id' => $bus_id,
        'total_ingreso' => $total_ingreso,
        'sueldo_base_calculado' => 0, // Ya no es relevante un solo base
        'conductor' => $conductores_str,
        'detalle' => $detalle,
        'lista_trabajadores' => $lista_trabajadores, // NEW DATA
        'monto_leyes_sociales' => $total_final
    ]);
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
ob_end_flush();
