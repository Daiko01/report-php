<?php
// ajax/procesar_generacion_masiva.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos
$mes = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
$ano = isset($_POST['ano']) ? (int)$_POST['ano'] : 0;
$empleadores_ids = isset($_POST['empleadores']) ? $_POST['empleadores'] : [];

if (!$mes || !$ano || empty($empleadores_ids)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

// Inicializar Servicio
$calculoService = new CalculoPlanillaService($pdo);
// Cargar caché de datos globales (afp, sindicatos)
$calculoService->cargarDatosGlobales($mes, $ano);

// Estadísticas
$stats = [
    'procesados' => 0,
    'empleadores_exito' => 0,
    'errores' => [],
    'ignorados' => 0
];

$pdo->beginTransaction();

try {
    // Preparar INSERT (Misma query que guardar_planilla.php)
    // Preparar INSERT (incluyendo snapshots)
    $sql_insert = "INSERT INTO planillas_mensuales (
        mes, ano, empleador_id, trabajador_id, sueldo_imponible, bonos_imponibles,
        tipo_contrato, fecha_inicio, fecha_termino, dias_trabajados, aportes,
        adicional_salud_apv, cesantia_licencia_medica, cotiza_cesantia_pensionado,
        descuento_afp, afp_historico_nombre, descuento_salud, seguro_cesantia, sindicato,
        asignacion_familiar_calculada, tasa_mutual_aplicada, sis_aplicado,
        tipo_asignacion_familiar, tramo_asignacion_familiar,
        sueldo_base_snapshot, es_part_time_snapshot, tipo_jornada_snapshot
    ) VALUES (
        :mes, :ano, :eid, :tid, :sueldo, :bonos,
        :tipo, :f_inicio, :f_termino, :dias, :aportes,
        :adicional, :cesantia_lic, :cotiza_ces,
        :desc_afp, :afp_hist, :desc_salud, :desc_cesantia, :desc_sindicato,
        :desc_asig_fam, :tasa_mutual, :sis_app,
        :tipo_asig, :tramo_asig,
        :s_snapshot, :pt_snapshot, :j_snapshot
    )";
    $stmt_insert = $pdo->prepare($sql_insert);

    // Obtener SIS vigente (Global) - Copiado de guardar_planilla.php
    $stmt_sis = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes)) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");
    $stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
    $tasa_sis_decimal = $stmt_sis->fetchColumn() ?: 0.0;
    $sis_aplicado = $tasa_sis_decimal * 100;

    // Obtener Aportes Externos (Cargados por CSV)
    // Se busca por RUT sin puntos ni guión
    $stmt_aporte = $pdo->prepare("SELECT monto FROM aportes_externos WHERE rut_trabajador = ? AND mes = ? AND ano = ? LIMIT 1");

    foreach ($empleadores_ids as $empleador_id) {
        $empleador_id = (int)$empleador_id;

        // 1. Verificar si ya existe planilla y ELIMINAR para regenerar
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ?");
        $stmt_check->execute([$empleador_id, $mes, $ano]);
        if ($stmt_check->fetchColumn() > 0) {
            // Si existe, la borramos para regenerarla con la nueva lógica
            $stmt_del = $pdo->prepare("DELETE FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ?");
            $stmt_del->execute([$empleador_id, $mes, $ano]);
            // No incrementamos stats['ignorados'], la tratamos como procesada
        }

        // 2. Obtener Tasa Mutual del Empleador
        $stmt_emp = $pdo->prepare("SELECT tasa_mutual_decimal FROM empleadores WHERE id = ?");
        $stmt_emp->execute([$empleador_id]);
        $tasa_mutual_decimal = $stmt_emp->fetchColumn() ?: 0.0;
        $tasa_mutual_aplicada = $tasa_mutual_decimal * 100;

        // 3. Buscar Contratos Activos en el mes
        // Un contrato es activo si:
        // - fecha_fin es NULL O fecha_fin >= primer_dia_mes
        // - fecha_inicio <= ultimo_dia_mes
        // - esta_finiquitado = 0 (Aunque fecha_termino debería controlarlo, la bandera es más segura)
        $primer_dia = sprintf("%04d-%02d-01", $ano, $mes);
        $ultimo_dia = date("Y-m-t", strtotime($primer_dia));

        $sql_contratos = "
            SELECT 
                c.*, t.rut, t.nombre
            FROM contratos c
            JOIN trabajadores t ON c.trabajador_id = t.id
            WHERE c.empleador_id = :eid
              AND c.fecha_inicio <= :ultimo_dia
              AND (c.fecha_termino IS NULL OR c.fecha_termino >= :primer_dia)
              AND (c.fecha_finiquito IS NULL OR c.fecha_finiquito >= :primer_dia)
        ";
        $stmt_c = $pdo->prepare($sql_contratos);
        $stmt_c->execute(['eid' => $empleador_id, 'ultimo_dia' => $ultimo_dia, 'primer_dia' => $primer_dia]);
        $contratos = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

        if (empty($contratos)) {
            // No hay trabajadores para este empleador en este mes
            $stats['ignorados']++; // O contar como éxito sin registros?
            continue;
        }

        // Aplicar lógica histórica de anexos
        foreach ($contratos as &$contrato) {
            $datos_historicos = obtener_datos_contrato_vigente($pdo, (int)$contrato['id'], $ultimo_dia);
            if ($datos_historicos) {
                $contrato['sueldo_imponible'] = $datos_historicos['sueldo_imponible'];
                $contrato['tipo_contrato'] = $datos_historicos['tipo_contrato'];
                $contrato['es_part_time'] = $datos_historicos['es_part_time'] ?? 0;
            }
        }
        unset($contrato);

        foreach ($contratos as $contrato) {
            // 4. Calcular Días Trabajados (Delegado al Servicio)
            $dias_trabajados = $calculoService->calcularDiasTrabajados($contrato['fecha_inicio'], $contrato['fecha_termino'] ?? null, $mes, $ano);

            // Calcular días de descuento por licencias
            $dias_descuento_licencia = $calculoService->calcularDiasLicencia($contrato['trabajador_id'], $contrato['fecha_inicio'], $contrato['fecha_termino'] ?? null, $mes, $ano);

            // Aplicar descuento
            $dias_trabajados = $dias_trabajados - $dias_descuento_licencia;

            // Topes y mínimos
            if ($dias_trabajados > 30) $dias_trabajados = 30;
            if ($dias_trabajados < 0) $dias_trabajados = 0;

            // PRORRATEO SUELDO (Delegado al Servicio)
            $sueldo_contractual_completo = (int)$contrato['sueldo_imponible'];
            $contrato['sueldo_imponible'] = $calculoService->calcularProporcional($sueldo_contractual_completo, $dias_trabajados);

            // Bonos también? Asumimos que sí, son imponibles mensuales. 
            if (isset($contrato['bonos_imponibles']) && $contrato['bonos_imponibles'] > 0) {
                $contrato['bonos_imponibles'] = $calculoService->calcularProporcional($contrato['bonos_imponibles'], $dias_trabajados);
            }

            // 5. Buscar Aporte Externo
            $rut_limpio = preg_replace('/[^0-9kK]/', '', $contrato['rut']);
            $stmt_aporte->execute([$rut_limpio, $mes, $ano]);
            $monto_aporte = (int)($stmt_aporte->fetchColumn() ?: 0);

            // Construir fila para servicio
            // Nota: El servicio busca trabajador en DB, pero necesita ciertos datos en $fila
            $fila_planilla = [
                'trabajador_id' => $contrato['trabajador_id'],
                'sueldo_imponible' => $contrato['sueldo_imponible'],
                'tipo_contrato' => $contrato['tipo_contrato'],
                // 'estado_previsional' se saca de la DB dentro del servicio
                'cotiza_cesantia_pensionado' => 0, // Valor por defecto
            ];

            // LLAMAR SERVICIO
            $calculos = $calculoService->calcularFila($fila_planilla, $mes, $ano);

            // INSERTAR
            $stmt_insert->execute([
                ':mes' => $mes,
                ':ano' => $ano,
                ':eid' => $empleador_id,
                ':tid' => $contrato['trabajador_id'],
                ':sueldo' => (int)$contrato['sueldo_imponible'],
                ':bonos' => (int)($contrato['bonos_imponibles'] ?? 0),
                ':tipo' => $contrato['tipo_contrato'],
                ':f_inicio' => $contrato['fecha_inicio'],
                ':f_termino' => !empty($contrato['fecha_termino']) ? $contrato['fecha_termino'] : null,
                ':dias' => (int)$dias_trabajados,
                ':aportes' => $monto_aporte,
                ':adicional' => 0, // Default
                ':cesantia_lic' => 0, // Default
                ':cotiza_ces' => 0, // Default
                ':desc_afp' => $calculos['descuento_afp'],
                ':afp_hist' => $calculos['afp_historico_nombre'],
                ':desc_salud' => $calculos['descuento_salud'],
                ':desc_cesantia' => $calculos['seguro_cesantia'],
                ':desc_sindicato' => $calculos['sindicato'],
                ':desc_asig_fam' => $calculos['asignacion_familiar_calculada'],
                ':tasa_mutual' => $tasa_mutual_aplicada,
                ':sis_app' => $sis_aplicado,
                ':tipo_asig' => $calculos['tipo_asignacion_familiar'],
                ':tramo_asig' => $calculos['tramo_asignacion_familiar'],
                // Snapshots
                ':s_snapshot' => $sueldo_contractual_completo,
                ':pt_snapshot' => (int)($contrato['es_part_time'] ?? 0),
                ':j_snapshot' => ($contrato['es_part_time'] ?? 0) ? 'Part Time' : 'Full Time'
            ]);

            $stats['procesados']++;
        }
        $stats['empleadores_exito']++;
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => "Proceso Finalizado. Empleadores: {$stats['empleadores_exito']}, Trabajadores generados: {$stats['procesados']}. (Ya existentes ignorados: {$stats['ignorados']})"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error Interno: ' . $e->getMessage()]);
}
