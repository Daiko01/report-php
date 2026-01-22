<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/LiquidacionService.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: generar_liquidacion.php');
    exit;
}

$seleccion_empleador = $_POST['empleador_id']; // Puede ser 'todos' o un número
$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];

try {
    $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
} catch (Exception $e) {
    // Continuamos aunque falle (algunos servidores no lo permiten, pero la mayoría sí)
}

// 1. Determinar qué empleadores procesar
$lista_empleadores = [];

if ($seleccion_empleador === 'todos') {
    // Obtener todos los IDs de empleadores
    $lista_empleadores = $pdo->query("SELECT id FROM empleadores")->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Solo el seleccionado
    $lista_empleadores[] = (int)$seleccion_empleador;
}

$liquidacionService = new LiquidacionService($pdo);
// 2A. Inicializar Caché (Optimización)
$liquidacionService->setPeriodo($mes, $ano);

$total_generadas = 0;
$empresas_procesadas = 0;
$errores = [];

// 2. Bucle Maestro: Procesar cada empleador
foreach ($lista_empleadores as $empleador_id) {

    // A. Verificar si tiene planilla para este mes
    $stmt_check = $pdo->prepare("SELECT id FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ? LIMIT 1");
    $stmt_check->execute([$empleador_id, $mes, $ano]);

    if (!$stmt_check->fetch()) {
        if ($seleccion_empleador !== 'todos') {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Error: No existe planilla para el empleador seleccionado en este período."];
            header('Location: generar_liquidacion.php');
            exit;
        }
        continue; // Saltar al siguiente empleador
    }

    // B. Obtener datos (Planilla + Contrato + Trabajador) - OPTIMIZADO
    $sql_data = "SELECT 
                    p.*, 
                    c.sueldo_imponible as sueldo_contrato,
                    c.pacto_colacion as contrato_colacion,
                    c.pacto_movilizacion as contrato_movilizacion,
                    t.afp_id as tr_afp_id,
                    t.sindicato_id as tr_sindicato_id,
                    t.estado_previsional as tr_estado_previsional,
                    t.sistema_previsional as tr_sistema_previsional,
                    t.tasa_inp_decimal as tr_tasa_inp_decimal,
                    t.tiene_cargas as tr_tiene_cargas,
                    t.numero_cargas as tr_numero_cargas,
                    t.tramo_asignacion_manual as tr_tramo_manual
                 FROM planillas_mensuales p
                 JOIN contratos c ON 
                    p.trabajador_id = c.trabajador_id AND 
                    p.empleador_id = c.empleador_id
                 JOIN trabajadores t ON p.trabajador_id = t.id
                 WHERE p.empleador_id = ? AND p.mes = ? AND p.ano = ?
                 AND c.fecha_inicio <= LAST_DAY(CONCAT(p.ano, '-', p.mes, '-01'))
                 AND (c.fecha_termino IS NULL OR c.fecha_termino >= CONCAT(p.ano, '-', p.mes, '-01'))
                 GROUP BY p.id";

    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute([$empleador_id, $mes, $ano]);
    $filas_planilla = $stmt_data->fetchAll();

    if (empty($filas_planilla)) continue;

    // --- CARGAR TRAMOS HISTÓRICOS PARA OVERRIDE ---
    $fecha_reporte = "$ano-" . str_pad($mes, 2, "0", STR_PAD_LEFT) . "-01";
    $sql_fecha_tramo = "SELECT MAX(fecha_inicio) as fecha_vigs FROM cargas_tramos_historicos WHERE fecha_inicio <= :fecha";
    $stmt_ft = $pdo->prepare($sql_fecha_tramo);
    $stmt_ft->execute([':fecha' => $fecha_reporte]);
    $fecha_vigente = $stmt_ft->fetchColumn();

    $tramos_del_periodo = [];
    if ($fecha_vigente) {
        $sql_tramos_final = "SELECT tramo, renta_maxima, monto_por_carga 
                             FROM cargas_tramos_historicos 
                             WHERE fecha_inicio = :fecha";
        $stmt_tf = $pdo->prepare($sql_tramos_final);
        $stmt_tf->execute([':fecha' => $fecha_vigente]);
        $tramos_del_periodo = $stmt_tf->fetchAll(PDO::FETCH_ASSOC);
    }

    $getTramoManual = function ($letra, $tramos) {
        foreach ($tramos as $t) if ($t['tramo'] == $letra) return $t;
        return null;
    };
    // ----------------------------------------------

    // C. Procesar trabajadores de esta empresa
    $pdo->beginTransaction();
    try {
        $sql_save = "INSERT INTO liquidaciones (empleador_id, trabajador_id, mes, ano, sueldo_base, gratificacion_legal, bonos_imponibles, total_imponible, descuento_afp, descuento_salud, seguro_cesantia, adicional_salud_apv, base_tributable, impuesto_unico, colacion, movilizacion, asig_familiar, total_no_imponible, anticipos, sindicato, total_descuentos, total_haberes, sueldo_liquido, fecha_generacion) 
        VALUES (:eid, :tid, :mes, :ano, :base, :grat, :bonos, :tot_imp, :afp, :sal, :ces, :apv, :trib, :imp_u, :col, :mov, :asig, :tot_noimp, :ant, :sind, :tot_desc, :tot_hab, :liq, NOW()) 
        ON DUPLICATE KEY UPDATE sueldo_base=VALUES(sueldo_base), gratificacion_legal=VALUES(gratificacion_legal), bonos_imponibles=VALUES(bonos_imponibles), total_imponible=VALUES(total_imponible), descuento_afp=VALUES(descuento_afp), descuento_salud=VALUES(descuento_salud), seguro_cesantia=VALUES(seguro_cesantia), adicional_salud_apv=VALUES(adicional_salud_apv), base_tributable=VALUES(base_tributable), impuesto_unico=VALUES(impuesto_unico), colacion=VALUES(colacion), movilizacion=VALUES(movilizacion), asig_familiar=VALUES(asig_familiar), total_no_imponible=VALUES(total_no_imponible), anticipos=VALUES(anticipos), sindicato=VALUES(sindicato), total_descuentos=VALUES(total_descuentos), total_haberes=VALUES(total_haberes), sueldo_liquido=VALUES(sueldo_liquido), fecha_generacion=NOW()";

        $stmt_save = $pdo->prepare($sql_save);

        foreach ($filas_planilla as $fila) {
            // Lógica de respaldo sueldo base
            $sueldo_base_calculo = (int)$fila['sueldo_base'];
            if ($sueldo_base_calculo == 0) {
                $dias = (int)$fila['dias_trabajados'];
                $sueldo_contrato = (int)$fila['sueldo_contrato'];
                $sueldo_base_calculo = ($dias == 30) ? $sueldo_contrato : round(($sueldo_contrato / 30) * $dias);
            }

            // Mapeo eager loading para evitar N+1
            $trabajador_obj = [
                'afp_id' => $fila['tr_afp_id'],
                'sindicato_id' => $fila['tr_sindicato_id'],
                'estado_previsional' => $fila['tr_estado_previsional'],
                'sistema_previsional' => $fila['tr_sistema_previsional'],
                'tasa_inp_decimal' => $fila['tr_tasa_inp_decimal'],
                'tiene_cargas' => $fila['tr_tiene_cargas'],
                'numero_cargas' => $fila['tr_numero_cargas']
            ];

            $contrato_data = [
                'trabajador_id' => $fila['trabajador_id'],
                'sueldo_imponible' => $sueldo_base_calculo,
                'tipo_contrato' => $fila['tipo_contrato'],
                'pacto_colacion' => $fila['contrato_colacion'],
                'pacto_movilizacion' => $fila['contrato_movilizacion'],
                'trabajador_obj' => $trabajador_obj // <--- INYECCION DE DEPENDENCIA
            ];

            $variables_mes = [
                'dias_trabajados' => $fila['dias_trabajados'],
                'cotiza_cesantia_pensionado' => $fila['cotiza_cesantia_pensionado'],
                'adicional_salud_apv' => $fila['adicional_salud_apv'],
                'anticipos' => $fila['monto_anticipo'],
                'sindicato' => $fila['sindicato'],
                'bonos_imponibles' => $fila['bonos_imponibles'] // <--- NUEVO CAMPO
            ];

            $resultado = $liquidacionService->calcularLiquidacion($contrato_data, $variables_mes, $mes, $ano);

            // --- LOGICA OVERRIDE ASIG FAMILIAR ---
            $asig_familiar_final = $fila['asignacion_familiar_calculada']; // Valor por defecto de la planilla

            if (!empty($fila['tr_tramo_manual'])) {
                $tramo_manual_data = $getTramoManual($fila['tr_tramo_manual'], $tramos_del_periodo);
                if ($tramo_manual_data) {
                    $monto_unitario = (int)$tramo_manual_data['monto_por_carga'];
                    $num_cargas = (int)$fila['tr_numero_cargas'];
                    $asig_familiar_final = $monto_unitario * $num_cargas;
                }
            }
            $resultado['asig_familiar'] = $asig_familiar_final;

            // Recálculo final
            $resultado['total_no_imponible'] = $resultado['colacion'] + $resultado['movilizacion'] + $resultado['asig_familiar'];
            $resultado['total_haberes'] = $resultado['total_imponible'] + $resultado['total_no_imponible'];
            $resultado['total_descuentos'] = $resultado['descuento_afp'] + $resultado['descuento_salud'] + $resultado['seguro_cesantia'] +
                $resultado['adicional_salud_apv'] + $resultado['impuesto_unico'] + $resultado['sindicato'] +
                $resultado['anticipos'];
            $resultado['sueldo_liquido'] = $resultado['total_haberes'] - $resultado['total_descuentos'];

            $stmt_save->execute([
                ':eid' => $empleador_id,
                ':tid' => $fila['trabajador_id'],
                ':mes' => $mes,
                ':ano' => $ano,
                ':base' => $resultado['sueldo_base'],
                ':grat' => $resultado['gratificacion_legal'],
                ':bonos' => $resultado['bonos_imponibles'], // <--- NUEVO
                ':tot_imp' => $resultado['total_imponible'],
                ':afp' => $resultado['descuento_afp'],
                ':sal' => $resultado['descuento_salud'],
                ':ces' => $resultado['seguro_cesantia'],
                ':apv' => $resultado['adicional_salud_apv'],
                ':trib' => $resultado['base_tributable'],
                ':imp_u' => $resultado['impuesto_unico'],
                ':col' => $resultado['colacion'],
                ':mov' => $resultado['movilizacion'],
                ':asig' => $resultado['asig_familiar'],
                ':tot_noimp' => $resultado['total_no_imponible'],
                ':ant' => $resultado['anticipos'],
                ':sind' => $resultado['sindicato'],
                ':tot_desc' => $resultado['total_descuentos'],
                ':tot_hab' => $resultado['total_haberes'],
                ':liq' => $resultado['sueldo_liquido']
            ]);
            $total_generadas++;
        }

        $pdo->commit();
        $empresas_procesadas++;
    } catch (Exception $e) {
        $pdo->rollBack();
        $errores[] = "Error en empleador ID $empleador_id: " . $e->getMessage();
    }
}

// Mensaje Final
if ($total_generadas > 0) {


    $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Proceso Masivo Completo. Se generaron $total_generadas liquidaciones de $empresas_procesadas empresas."];
} else {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => "No se generaron liquidaciones. Verifica que existan planillas guardadas para el mes seleccionado."];
}

if (!empty($errores)) {
    // Podrías guardar los errores en log, por ahora solo mostramos el primero si hubo fallo total
    // O redirigir a una página de log.
}

header('Location: listar_liquidaciones.php?mes=' . $mes . '&ano=' . $ano);
exit;
