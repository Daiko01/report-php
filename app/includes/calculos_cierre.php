<?php
// app/includes/calculos_cierre.php

/**
 * Calcula los datos de cierre para una máquina en un mes/año específico.
 * Retorna un array con los valores calculados.
 * 
 * @param PDO $pdo Conexión a base de datos
 * @param int $bus_id ID del bus
 * @param int $mes Mes (1-12)
 * @param int $anio Año
 * @param array|null $parametros_mensuales Opcional: si ya se tiene la fila de parametros_mensuales para no consultar de nuevo
 * @return array
 */
function calcular_cierre_bus($pdo, $bus_id, $mes, $anio, $parametros_mensuales = null)
{
    // 1. Obtener Parámetros Globales si no vienen
    if ($parametros_mensuales === null) {
        $stmtP = $pdo->prepare("SELECT * FROM parametros_mensuales WHERE mes = ? AND anio = ?");
        $stmtP->execute([$mes, $anio]);
        $parametros_mensuales = $stmtP->fetch(PDO::FETCH_ASSOC);
    }

    $pm = $parametros_mensuales; // alias corto

    // Valores por defecto globales
    $defaults = [
        'monto_administracion_global' => $pm ? (int)$pm['monto_administracion_global'] : 350000,
        'derechos_loza' => $pm ? (int)$pm['derechos_loza_global'] : 0,
        'gps' => $pm ? (int)$pm['gps_global'] : 0,
        'boleta_garantia' => $pm ? (int)$pm['boleta_garantia_global'] : 0,
        'boleta_garantia_dos' => $pm ? (int)$pm['boleta_garantia_dos_global'] : 0,
        'seguro_cartolas' => 0
    ];

    // Regla Septiembre
    if ($mes == 9 && $pm) {
        $defaults['seguro_cartolas'] = (int)$pm['seguro_cartolas_global'];
    }

    // 2. Lógica de Leyes Sociales
    $leyes_sociales_calculo = 0;

    $stmtBusCfg = $pdo->prepare("SELECT empleador_id FROM buses WHERE id = ?");
    $stmtBusCfg->execute([$bus_id]);
    $busInfo = $stmtBusCfg->fetch(PDO::FETCH_ASSOC);

    if ($busInfo) {
        // Verificar si este bus es el pagador en la configuración mensual
        $stmtConfigLeyes = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual WHERE mes = ? AND anio = ? AND empleador_id = ?");
        $stmtConfigLeyes->execute([$mes, $anio, $busInfo['empleador_id']]);
        $resConfig = $stmtConfigLeyes->fetch();

        $es_pagador = false;

        // Lógica de detección inteligente (la misma del PDF)
        if ($resConfig) {
            if ($resConfig['bus_pagador_id'] == $bus_id) $es_pagador = true;
        } else {
            // Fallback: Si tiene 1 solo bus
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE empleador_id = ?");
            $stmtCount->execute([$busInfo['empleador_id']]);
            if ($stmtCount->fetchColumn() == 1) $es_pagador = true;
        }

        if ($es_pagador) {
            $eid = $busInfo['empleador_id'];
            // Fetch Planillas
            $stmtPlan = $pdo->prepare("
                SELECT sueldo_imponible, descuento_afp, descuento_salud, adicional_salud_apv, seguro_cesantia, sindicato, cesantia_licencia_medica,
                    aportes, asignacion_familiar_calculada, sis_aplicado, tasa_mutual_aplicada, trabajador_id,
                    (SELECT estado_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as estado_prev,
                    (SELECT sistema_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as sistema_prev,
                    tipo_contrato, cotiza_cesantia_pensionado
                FROM planillas_mensuales 
                WHERE empleador_id = ? AND mes = ? AND ano = ?
            ");
            $stmtPlan->execute([$eid, $mes, $anio]);
            $rowsPlan = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

            if ($rowsPlan) {
                // Cálculo detallado de leyes
                $TASA_CAP_IND_CONST = 0.001;
                $TASA_EXP_VIDA_CONST = 0.009;

                $first = $rowsPlan[0];
                $tasa_sis = isset($first['sis_aplicado']) ? ((float)$first['sis_aplicado'] / 100) : 0.0;
                $tasa_mutual = isset($first['tasa_mutual_aplicada']) ? ((float)$first['tasa_mutual_aplicada'] / 100) : 0.0;

                $total_imponible_all = 0;
                $total_imponible_sis = 0;
                $sum_desc_worker = 0;
                $sum_aportes = 0;
                $saldos_positivos = 0;
                $calc_seguro_cesantia_patronal = 0;

                foreach ($rowsPlan as $r) {
                    $total_imponible_all += $r['sueldo_imponible'];
                    if ($r['sistema_prev'] == 'AFP' && $r['estado_prev'] != 'Pensionado') {
                        $total_imponible_sis += $r['sueldo_imponible'];
                    }

                    $row_desc = ($r['descuento_afp'] + $r['descuento_salud'] + $r['adicional_salud_apv'] + $r['seguro_cesantia'] + $r['sindicato'] + $r['cesantia_licencia_medica']);
                    $sum_desc_worker += $row_desc;
                    $sum_aportes += $r['aportes'];

                    // Saldo Positivo
                    $row_saldo = ($r['aportes'] + $r['asignacion_familiar_calculada']) - $row_desc;
                    if ($row_saldo > 0) {
                        $saldos_positivos += $row_saldo;
                    }

                    $debe_pagar_sc = false;
                    if ($r['estado_prev'] == 'Activo') $debe_pagar_sc = true;
                    elseif (isset($r['cotiza_cesantia_pensionado']) && $r['cotiza_cesantia_pensionado'] == 1) $debe_pagar_sc = true;

                    if ($debe_pagar_sc) {
                        $rate_sc = ($r['tipo_contrato'] == 'Fijo') ? 0.03 : 0.024;
                        $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * $rate_sc);
                    }
                }
                $calc_aporte_mutual = floor($total_imponible_all * $tasa_mutual);
                $calc_sis = floor($total_imponible_sis * $tasa_sis);
                $calc_cap_ind = floor($total_imponible_sis * $TASA_CAP_IND_CONST);
                $calc_exp_vida = floor($total_imponible_sis * $TASA_EXP_VIDA_CONST);

                // Cálculo Final (Exact match ver_pdf.php "total_descuentos_final")
                $sub_total_desc_tabla = $sum_desc_worker;
                $sub_total_desc_final = $sub_total_desc_tabla + $calc_aporte_mutual + $calc_sis + $calc_seguro_cesantia_patronal + $calc_cap_ind + $calc_exp_vida;

                $leyes_sociales_calculo = $sub_total_desc_final - $sum_aportes + $saldos_positivos;
            }
        }
    }

    return [
        'defaults' => $defaults,
        'monto_leyes_sociales' => $leyes_sociales_calculo,
        'admin_global' => $defaults['monto_administracion_global'] // convenience
    ];
}
