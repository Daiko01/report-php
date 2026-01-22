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
            // 4. Calcular Días Trabajados (Lógica 30 días comercial)
            $dias_trabajados = 30;

            $inicio_contrato = strtotime($contrato['fecha_inicio']);
            $fin_contrato = $contrato['fecha_termino'] ? strtotime($contrato['fecha_termino']) : null;
            $inicio_mes_ts = strtotime($primer_dia);
            $fin_mes_ts = strtotime($ultimo_dia);

            // Caso 1: Contrato empieza este mes
            if ($inicio_contrato >= $inicio_mes_ts) {
                // Dias = 30 - (dia_inicio) + 1
                $dia_inicio = (int)date('j', $inicio_contrato);

                // Excepción febrero: Si empieza el 28/29 de Feb, el cálculo 30 - dia + 1 puede dar problemas.
                // La norma general comercial es: Si labora mes completo = 30.
                // Si ingresa después del 1, se pagan los días efectivos.
                // Simplificación: usaremos la diferencia de días + 1, ajustado a 30 si es mes completo.
                // Pero comercialmente: 30 - dia + 1 es el estándar.
                // Si entra el 31, se paga (30-31+1) = 0? No, se paga 1 día (o 0 si 31 no cuenta).
                // Ajuste simple: Calcular días reales, y si es todo el mes -> 30.

                // Usemos date_diff para ser exactos en días calendario y luego topar a 30?
                // Mejor, lógica simple solicitada: "asumiendo 30 días trabajados por defecto".
                // Solo ajustamos si entra o sale.

                $dias_trabajados = 30 - $dia_inicio + 1;
                // Si entra el 31, da 0?
                if ($dias_trabajados < 0) $dias_trabajados = 0; // Por seguridad
            }

            // Caso 2: Contrato termina este mes
            if ($fin_contrato && $fin_contrato <= $fin_mes_ts) {
                $dia_fin = (int)date('j', $fin_contrato);
                if ($inicio_contrato >= $inicio_mes_ts) {
                    $datediff = $fin_contrato - $inicio_contrato;
                    $dias_trabajados = round($datediff / (60 * 60 * 24)) + 1;
                } else {
                    $dias_trabajados = $dia_fin;
                    // Si termina el 31, se considera mes completo (30).
                    if ($dia_fin == 31) $dias_trabajados = 30;
                }
            }

            // --- LOGICA LICENCIAS MÉDICAS ---
            // Calcular días de licencia que caen dentro del periodo activo del contrato en este mes.
            $stmt_lic_check = $pdo->prepare("SELECT fecha_inicio, fecha_fin FROM trabajador_licencias WHERE trabajador_id = ? AND fecha_inicio <= ? AND fecha_fin >= ?");
            // Buscamos licencias que intercepten con el mes (la intersección fina la hacemos en PHP)
            $stmt_lic_check->execute([$contrato['trabajador_id'], $ultimo_dia, $primer_dia]);
            $licencias_encontradas = $stmt_lic_check->fetchAll(PDO::FETCH_ASSOC);

            $dias_descuento_licencia = 0;
            // Definir el rango efectivo del contrato en este mes para descontar licencias válido
            $inicio_efectivo = ($inicio_contrato > $inicio_mes_ts) ? $inicio_contrato : $inicio_mes_ts;
            $fin_efectivo = ($fin_contrato && $fin_contrato < $fin_mes_ts) ? $fin_contrato : $fin_mes_ts;

            foreach ($licencias_encontradas as $lic) {
                $l_ini = strtotime($lic['fecha_inicio']);
                $l_fin = strtotime($lic['fecha_fin']);

                // Intersección: Licencia vs (Contrato & Mes)
                $overlap_start = max($l_ini, $inicio_efectivo);
                $overlap_end = min($l_fin, $fin_efectivo);

                if ($overlap_start <= $overlap_end) {
                    // Contar días uno a uno para aplicar lógica 30 días
                    // Lógica: No contar el día 31.

                    $current = $overlap_start;
                    while ($current <= $overlap_end) {
                        $dia_numero = (int)date('j', $current);

                        // Si es día 31, LO IGNORAMOS para el descuento
                        if ($dia_numero == 31) {
                            $current = strtotime('+1 day', $current);
                            continue;
                        }

                        $dias_descuento_licencia++;
                        $current = strtotime('+1 day', $current);
                    }
                }
            }

            // AJUSTE FEBRERO MES COMPLETO
            // Si es Febrero y la licencia cubrió todo el mes calendario (28 o 29), el descuento debe ser 30.
            if ($mes == 2) {
                $dias_mes_feb = (int)date('t', strtotime("$ano-02-01"));
                // Si el descuento calculado (sin ajustes raros) es igual a los días del mes, 
                // significa que estuvo enfermo todo el mes.
                if ($dias_descuento_licencia == $dias_mes_feb) {
                    $dias_descuento_licencia = 30;
                }
            }

            // Aplicar descuento
            $dias_trabajados = $dias_trabajados - $dias_descuento_licencia;

            // Topes y mínimos
            if ($dias_trabajados > 30) $dias_trabajados = 30;
            if ($dias_trabajados < 0) $dias_trabajados = 0;

            // PRORRATEO SUELDO (Si días < 30 y no es porque entró o salió, o sí? 
            // Si tiene licencia, se paga proporcional.
            // Si dias_trabajados < 30, prorrateamos el sueldo base.
            // Guardamos original para snapshot (SIS).
            $sueldo_contractual_completo = (int)$contrato['sueldo_imponible'];

            if ($dias_trabajados < 30) {
                // Cálculo proporcional
                // Si dias es 0, sueldo es 0.
                if ($dias_trabajados == 0) {
                    $contrato['sueldo_imponible'] = 0;
                    $contrato['bonos_imponibles'] = 0;
                } else {
                    $contrato['sueldo_imponible'] = round(($sueldo_contractual_completo / 30) * $dias_trabajados);
                    // Bonos también? Asumimos que sí, son imponibles mensuales. 
                    // Si son tratos (no fijos) esto podría ser error, pero es generación masiva estándar.
                    if (isset($contrato['bonos_imponibles']) && $contrato['bonos_imponibles'] > 0) {
                        $contrato['bonos_imponibles'] = round(($contrato['bonos_imponibles'] / 30) * $dias_trabajados);
                    }
                }
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
