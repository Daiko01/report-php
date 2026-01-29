<?php
// buses/generar_pdf_liquidacion.php
// 1. CONFIGURACIÓN INICIAL ROBUSTA
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
date_default_timezone_set('America/Santiago');

if (ob_get_length()) ob_end_clean();
ob_start();

function logPdfError($msg)
{
    $logFile = __DIR__ . '/error_log_pdf.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg" . PHP_EOL, FILE_APPEND);
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        if (ob_get_length()) ob_end_clean();
        $msg = "FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}";
        logPdfError($msg);
        echo "<h1>Error Fatal en Generación</h1><p>Revise el log de errores.</p>";
    }
});

try {
    // 2. INCLUDES
    require_once dirname(__DIR__) . '/app/core/bootstrap.php';
    require_once dirname(__DIR__) . '/app/includes/session_check.php';
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    if (ob_get_length()) ob_end_clean();
    ob_start();

    // 3. CAPTURA DE VARIABLES
    $mes = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : date('n');
    $anio = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : date('Y');
    $filtro_tipo = $_REQUEST['filtro_tipo'] ?? 'todos';
    $bus_id = isset($_REQUEST['bus_id']) ? (int)$_REQUEST['bus_id'] : 0;
    $empleador_id = isset($_REQUEST['empleador_id']) ? (int)$_REQUEST['empleador_id'] : 0;

    // 4. PREPARACIÓN DE MPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 5,
        'margin_right' => 5,
        'margin_top' => 5,
        'margin_bottom' => 5
    ]);
    $mpdf->SetTitle("Liquidacion_Buses_{$mes}_{$anio}");
    $mpdf->SetAuthor('Sistema Gestión');

    // 5. CONSULTA PRINCIPAL DE BUSES
    $sqlBuses = "SELECT DISTINCT pb.bus_id, b.numero_maquina, b.patente, b.empleador_id,
                        e.nombre as empleador_nombre, e.rut as empleador_rut
                 FROM produccion_buses pb
                 JOIN buses b ON pb.bus_id = b.id
                 JOIN empleadores e ON b.empleador_id = e.id
                 WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ? ";

    $params = [$mes, $anio];

    if ($filtro_tipo === 'bus' && $bus_id) {
        $sqlBuses .= " AND b.id = ?";
        $params[] = $bus_id;
    } elseif ($filtro_tipo === 'empleador' && $empleador_id) {
        $sqlBuses .= " AND b.empleador_id = ? ";
        $params[] = $empleador_id;
    }

    $sqlBuses .= " ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";

    $stmtBuses = $pdo->prepare($sqlBuses);
    $stmtBuses->execute($params);
    $busesList = $stmtBuses->fetchAll();

    if (!$busesList) {
        ob_clean(); // Limpiar buffer de MPDF
        echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sin Resultados</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: "info",
            title: "Sin Información",
            text: "No se encontraron registros para el periodo seleccionado.",
            confirmButtonText: "Entendido"
        }).then((result) => {
            if (result.isConfirmed || result.isDismissed) {
                window.close();
            }
        });
    </script>
    </body>
    </html>';
        exit;
    }

    // Obtener Admin Global Solamente (El bus pagador ahora es dinámico por empleador)
    $stmtP = $pdo->prepare("SELECT monto_administracion_global FROM parametros_mensuales WHERE mes = ? AND anio = ?");
    $stmtP->execute([$mes, $anio]);
    $rowP = $stmtP->fetch();

    // LOGICA HISTORICA MONTO GLOBAL: Si no hay monto este mes, buscar el último
    if (!$rowP) {
        $stmtHistG = $pdo->prepare("SELECT monto_administracion_global FROM parametros_mensuales WHERE (anio < ?) OR (anio = ? AND mes < ?) ORDER BY anio DESC, mes DESC LIMIT 1");
        $stmtHistG->execute([$anio, $anio, $mes]);
        $hist_global = $stmtHistG->fetch();
        $adminGlobal = $hist_global ? (int)$hist_global['monto_administracion_global'] : 35000;
    } else {
        $adminGlobal = (int)$rowP['monto_administracion_global'];
    }

    // Cache para no recalcular el "Bus Pagador" del mismo empleador 50 veces
    $cacheBusPagador = [];

    // 7. GENERACIÓN DE PÁGINAS (Bucle Principal)
    foreach ($busesList as $bus) {
        $mpdf->AddPage();

        // ---------------------------------------------------------
        // LÓGICA INTELIGENTE: DETERMINACIÓN DEL BUS PAGADOR
        // ---------------------------------------------------------
        $empID = $bus['empleador_id'];
        $busPagadorId = 0;

        if (isset($cacheBusPagador[$empID])) {
            $busPagadorId = $cacheBusPagador[$empID];
        } else {
            // 1. Buscar configuración explícita para este mes
            $stmtConf = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual WHERE mes = ? AND anio = ? AND empleador_id = ?");
            $stmtConf->execute([$mes, $anio, $empID]);
            $resConf = $stmtConf->fetch();

            if ($resConf) {
                $busPagadorId = $resConf['bus_pagador_id'];
            } else {
                // 2. Si no existe, revisar si tiene UN SOLO BUS (Autoselección)
                $stmtCount = $pdo->prepare("SELECT id FROM buses WHERE empleador_id = ?");
                $stmtCount->execute([$empID]);
                $allBusesEmp = $stmtCount->fetchAll(PDO::FETCH_COLUMN);

                if (count($allBusesEmp) === 1) {
                    $busPagadorId = $allBusesEmp[0];
                } else {
                    // 3. Si tiene varios, buscar HISTORIAL (Cascada)
                    $stmtHist = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual 
                                               WHERE empleador_id = ? 
                                               AND ((anio < ?) OR (anio = ? AND mes < ?)) 
                                               ORDER BY anio DESC, mes DESC LIMIT 1");
                    $stmtHist->execute([$empID, $anio, $anio, $mes]);
                    $resHist = $stmtHist->fetch();
                    if ($resHist) {
                        $busPagadorId = $resHist['bus_pagador_id'];
                    }
                }
            }
            // Guardar en cache para la siguiente vuelta del bucle
            $cacheBusPagador[$empID] = $busPagadorId;
        }
        // ---------------------------------------------------------

        // Datos Producción
        $stmtProd = $pdo->prepare("
            SELECT p.*, t.nombre as nombre_conductor_real 
            FROM produccion_buses p
            LEFT JOIN trabajadores t ON p.conductor_id = t.id
            WHERE p.bus_id = ? AND MONTH(p.fecha) = ? AND YEAR(p.fecha) = ? 
            ORDER BY p.fecha ASC
        ");
        $stmtProd->execute([$bus['bus_id'], $mes, $anio]);
        $produccion = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        // Datos Cierre Manual
        $stmtCierre = $pdo->prepare("SELECT * FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ?");
        $stmtCierre->execute([$bus['bus_id'], $mes, $anio]);
        $cierre = $stmtCierre->fetch(PDO::FETCH_ASSOC) ?: [];

        // HEADER
        $htmlHeader = '
        <h1 style="text-align: center; font-family: sans-serif; font-size: 14px; margin-bottom: 2px; margin-top:0; text-transform: uppercase;">RESUMEN MENSUAL DE PRODUCCIÓN</h1>
        <table border="0" cellpadding="1" width="100%" style="font-family:sans-serif; font-size: 10px;">
            <tr>
                <td width="60%">
                    <b>EMPLEADOR:</b> ' . htmlspecialchars($bus['empleador_nombre'] ?? '') . ' - <b>RUT:</b> ' . htmlspecialchars($bus['empleador_rut'] ?? '') . '<br>
                    <b>PERIODO:</b> ' . $mes . ' / ' . $anio . '
                </td>
                <td width="40%" align="right">
                    <h2 style="margin:0; font-size:14px;">BUS N° ' . ($bus['numero_maquina'] ?? 'S/N') . ' <small style="font-size:10px; font-weight:normal;">(Patente: ' . ($bus['patente'] ?? '---') . ')</small></h2>
                </td>
            </tr>
        </table><hr style="margin: 2px 0;">';
        $mpdf->WriteHTML($htmlHeader);

        // TABLA DETALLE
        $htmlTable = '
        <style>
            table.det { border-collapse: collapse; width: 100%; font-family: sans-serif; font-size: 10px; }
            .det th { background: #f2f2f2; border: 1px solid #999; padding: 2px; font-weight:bold; }
            .det td { border: 1px solid #ccc; padding: 2px; }
            .tot { background: #e0e0e0; font-weight: bold; }
        </style>
        <table class="det">
            <thead>
                <tr>
                    <th width="3%">Dia</th>
                    <th width="6%">Guía</th>
                    <th>Ingreso</th>
                    <th>Petróleo</th>
                    <th>Boletos</th>
                    <th>Admin</th>
                    <th>Aseo</th>
                    <th>Viático</th>
                    <th>% Cond</th>
                    <th>Aportes</th>
                    <th>Varios</th>
                    <th width="15%">Conductor</th>
                </tr>
            </thead>
            <tbody>';

        $sum = ['ing' => 0, 'pet' => 0, 'bol' => 0, 'adm' => 0, 'ase' => 0, 'via' => 0, 'pag' => 0, 'apo' => 0, 'var' => 0, 'ext' => 0];

        foreach ($produccion as $row) {
            $ing = $row['ingreso'] ?? 0;
            $pet = $row['gasto_petroleo'] ?? 0;
            $bol = $row['gasto_boletos'] ?? 0;
            $adm = $row['gasto_administracion'] ?? 0;
            $ase = $row['gasto_aseo'] ?? 0;
            $via = $row['gasto_viatico'] ?? 0;
            $pag = $row['pago_conductor'] ?? 0;
            // MAPPING: Aportes (Legacy/CSV) > Imposiciones (New)
            $apo = $row['aporte_previsional'] ?: ($row['gasto_imposiciones'] ?? 0);
            $var = $row['gasto_varios'] ?? 0;
            $ext = $row['gasto_cta_extra'] ?? 0;
            // PREFERIR NOMBRE DE DATABASE (ID), SINO CSV, SINO VACIO
            $nom = $row['nombre_conductor_real'] ?? ($row['conductor_nombre_csv'] ?? '---');

            $sum['ing'] += $ing;
            $sum['pet'] += $pet;
            $sum['bol'] += $bol;
            $sum['adm'] += $adm;
            $sum['ase'] += $ase;
            $sum['via'] += $via;
            $sum['pag'] += $pag;
            $sum['apo'] += $apo;
            $sum['var'] += $var;
            $sum['ext'] += $ext;

            $dia = date('d', strtotime($row['fecha']));

            $htmlTable .= '<tr>
                <td align="center">' . $dia . '</td>
                <td align="center">' . ($row['nro_guia'] ?? '') . '</td>
                <td align="right">' . number_format($ing, 0, ',', '.') . '</td>
                <td align="right">' . number_format($pet, 0, ',', '.') . '</td>
                <td align="right">' . number_format($bol, 0, ',', '.') . '</td>
                <td align="right">' . number_format($adm, 0, ',', '.') . '</td>
                <td align="right">' . number_format($ase, 0, ',', '.') . '</td>
                <td align="right">' . number_format($via, 0, ',', '.') . '</td>
                <td align="right">' . number_format($pag, 0, ',', '.') . '</td>
                <td align="right">' . number_format($apo, 0, ',', '.') . '</td>
                <td align="right">' . number_format($var, 0, ',', '.') . '</td>
                <td>' . $nom . '</td>
            </tr>';
        }

        $htmlTable .= '<tr class="tot">
            <td colspan="2" align="right">TOTALES:</td>
            <td align="right">' . number_format($sum['ing'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['pet'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['bol'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['adm'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['ase'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['via'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['pag'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['apo'], 0, ',', '.') . '</td>
            <td align="right">' . number_format($sum['var'], 0, ',', '.') . '</td>
            <td></td>
        </tr></tbody></table><br>';

        $mpdf->WriteHTML($htmlTable);

        // Variables Cierre
        $v = [
            'sub' => (int)($cierre['subsidio_operacional'] ?? 0),
            'min' => (int)($cierre['devolucion_minutos'] ?? 0),
            'ot1' => (int)($cierre['otros_ingresos_1'] ?? 0),
            'ant' => (int)($cierre['anticipo'] ?? 0),
            'asg' => (int)($cierre['asignacion_familiar'] ?? 0),
            'pmi' => (int)($cierre['pago_minutos'] ?? 0),
            'san' => (int)($cierre['saldo_anterior'] ?? 0),
            'amu' => (int)($cierre['ayuda_mutua'] ?? 0),
            'gru' => (int)($cierre['servicio_grua'] ?? 0),
            'pol' => (int)($cierre['poliza_seguro'] ?? 0),
            'ley' => (int)($cierre['monto_leyes_sociales'] ?? 0),
            'adm_app' => isset($cierre['monto_administracion_aplicado']) ? (int)$cierre['monto_administracion_aplicado'] : $adminGlobal,
            // Nuevos Cargos Fijos
            'der_loz' => (int)($cierre['derechos_loza'] ?? 0),
            'seg_car' => (int)($cierre['seguro_cartolas'] ?? 0),
            'gps' => (int)($cierre['gps'] ?? 0),
            'bol_gar' => (int)($cierre['boleta_garantia'] ?? 0),
            'bol_ga2' => (int)($cierre['boleta_garantia_dos'] ?? 0)
        ];

        // Vueltas
        $cvd = (int)($cierre['cant_vueltas_directo'] ?? 0);
        $vvd = (int)($cierre['valor_vueltas_directo'] ?? 0);
        $cvl = (int)($cierre['cant_vueltas_local'] ?? 0);
        $vvl = (int)($cierre['valor_vueltas_local'] ?? 0);
        $total_vueltas = ($cvd * $vvd) + ($cvl * $vvl);

        // --------------------------------------------------------------------------
        // CÁLCULO LAZY SAVE DE LEYES SOCIALES (USANDO LA NUEVA LÓGICA DE DETECCIÓN)
        // --------------------------------------------------------------------------
        $monto_leyes_final = $v['ley'];

        // Verificamos si la máquina actual ($bus['bus_id']) coincide con la detectada ($busPagadorId)
        if ($busPagadorId > 0 && $bus['bus_id'] == $busPagadorId) {

            // Si es la elegida, y no tiene monto guardado, calculamos
            if ($monto_leyes_final == 0) {

                $empleadorIdCalc = $bus['empleador_id'] ?? 0;

                // Consulta Planillas RRHH
                $stmtPlan = $pdo->prepare("
                    SELECT 
                        sueldo_imponible, 
                        descuento_afp, descuento_salud, adicional_salud_apv, seguro_cesantia, sindicato, cesantia_licencia_medica,
                        aportes, asignacion_familiar_calculada,
                        sis_aplicado, tasa_mutual_aplicada,
                        trabajador_id,
                        (SELECT estado_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as estado_prev,
                        (SELECT sistema_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as sistema_prev,
                        tipo_contrato, cotiza_cesantia_pensionado
                    FROM planillas_mensuales 
                    WHERE empleador_id = ? AND mes = ? AND ano = ?
                ");
                $stmtPlan->execute([$empleadorIdCalc, $mes, $anio]);
                $rowsPlan = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

                $leyes_sociales_calculo = 0;

                if ($rowsPlan) {
                    // Constants
                    $TASA_CAP_IND_CONST = 0.001;
                    $TASA_EXP_VIDA_CONST = 0.009;

                    $first = $rowsPlan[0];
                    $tasa_sis = isset($first['sis_aplicado']) ? ((float)$first['sis_aplicado'] / 100) : 0.0;
                    $tasa_mutual = isset($first['tasa_mutual_aplicada']) ? ((float)$first['tasa_mutual_aplicada'] / 100) : 0.0;

                    $total_imponible_all = 0;
                    $total_imponible_sis = 0;
                    $sum_desc_worker = 0;
                    $sum_sindicato = 0;
                    $sum_asig_fam = 0;
                    $calc_seguro_cesantia_patronal = 0;

                    foreach ($rowsPlan as $r) {
                        $total_imponible_all += $r['sueldo_imponible'];

                        if ($r['sistema_prev'] == 'AFP' && $r['estado_prev'] != 'Pensionado') {
                            $total_imponible_sis += $r['sueldo_imponible'];
                        }

                        $row_desc = $r['descuento_afp'] + $r['descuento_salud'] + $r['adicional_salud_apv'] +
                            $r['seguro_cesantia'] + $r['sindicato'] + $r['cesantia_licencia_medica'];
                        $sum_desc_worker += $row_desc;
                        $sum_sindicato += $r['sindicato'];
                        $sum_asig_fam += $r['asignacion_familiar_calculada'];

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

                    $leyes_sociales_empleado = $sum_desc_worker - $sum_sindicato;
                    $leyes_sociales_empleador = $calc_sis + $calc_seguro_cesantia_patronal + $calc_cap_ind + $calc_exp_vida;

                    $leyes_sociales_calculo = $leyes_sociales_empleado + $leyes_sociales_empleador + $calc_aporte_mutual - $sum_asig_fam;
                }

                $monto_leyes_final = $leyes_sociales_calculo;

                if ($monto_leyes_final > 0) {
                    $stmtSave = $pdo->prepare("INSERT INTO cierres_maquinas (bus_id, mes, anio, monto_leyes_sociales) 
                                                VALUES (?, ?, ?, ?) 
                                                ON DUPLICATE KEY UPDATE monto_leyes_sociales = ?");
                    $stmtSave->execute([$bus['bus_id'], $mes, $anio, $monto_leyes_final, $monto_leyes_final]);
                }
            }
        }

        // Asignar el valor final al array
        $v['ley'] = $monto_leyes_final;

        // Devolución Boletos
        $dev_bol = 0;
        if ($sum['bol'] > 0) {
            $dev_bol = $sum['bol'] - (($sum['bol'] / 5000) * 1095);
        }

        $haberes = $sum['adm'] + $v['sub'] + $v['min'] + $dev_bol + $v['ot1'];
        $total_nuevos_cargos = $v['der_loz'] + $v['seg_car'] + $v['gps'] + $v['bol_gar'] + $v['bol_ga2'];
        $descuentos = $v['adm_app'] + $v['ley'] + $total_vueltas + $v['ant'] + $v['pmi'] + $v['san'] + $v['amu'] + $v['gru'] + $v['pol'] + $v['asg'] + $total_nuevos_cargos;

        $saldo_final = $haberes - $descuentos;

        $countGuias = count($produccion);
        $htmlResumen = '
        <table width="100%" cellpadding="0" cellspacing="2" style="font-family:sans-serif;">
        <tr>
            <td width="26%" valign="top">
                <table border="1" style="border-collapse:collapse; width:100%; font-size:9px;">
                    <tr bgcolor="#eee"><th colspan="2">INGRESOS / HABERES</th></tr>
                    <tr><td>Guías Cortadas (N° ' . $countGuias . ')</td><td align="right">' . number_format($sum['adm'], 0, ',', '.') . '</td></tr>
                    <tr><td>Subsidio Operacional</td><td align="right">' . number_format($v['sub'], 0, ',', '.') . '</td></tr>
                    <tr><td>Devolución Minutos</td><td align="right">' . number_format($v['min'], 0, ',', '.') . '</td></tr>
                    <tr><td>Devolución Boletos</td><td align="right">' . number_format($dev_bol, 0, ',', '.') . '</td></tr>
                    <tr><td>Otros Ingresos</td><td align="right">' . number_format($v['ot1'], 0, ',', '.') . '</td></tr>
                    <tr bgcolor="#ddd"><td><b>TOTAL HABERES</b></td><td align="right"><b>' . number_format($haberes, 0, ',', '.') . '</b></td></tr>
                </table>
            </td>
            
            <td width="26%" valign="top">
                <table border="1" style="border-collapse:collapse; width:100%; font-size:9px;">
                    <tr bgcolor="#eee"><th colspan="2">DESC. / CARGOS VARIOS</th></tr>
                    <tr><td>Administración</td><td align="right">' . number_format($v['adm_app'], 0, ',', '.') . '</td></tr>
                    <tr><td>Leyes Sociales</td><td align="right">' . number_format($v['ley'], 0, ',', '.') . '</td></tr>
                    <tr><td>Control Vueltas</td><td align="right">' . number_format($total_vueltas, 0, ',', '.') . '</td></tr>
                    <tr><td>Anticipos</td><td align="right">' . number_format($v['ant'], 0, ',', '.') . '</td></tr>
                    <tr><td>Asignación Familiar</td><td align="right">' . number_format($v['asg'], 0, ',', '.') . '</td></tr>
                    <tr><td>Ayuda Mutua / Saldo Ant</td><td align="right">' . number_format($v['amu'] + $v['san'], 0, ',', '.') . '</td></tr>
                    <tr><td>Varios (Seguro/Grúa/Etc)</td><td align="right">' . number_format($v['pol'] + $v['gru'] + $v['pmi'], 0, ',', '.') . '</td></tr>
                </table>
            </td>

            <td width="24%" valign="top">
                <table border="1" style="border-collapse:collapse; width:100%; font-size:9px;">
                    <tr bgcolor="#eee"><th colspan="2">CARGOS FIJOS / OTROS</th></tr>
                    <tr><td>Derechos de Loza</td><td align="right">' . number_format($v['der_loz'], 0, ',', '.') . '</td></tr>
                    ' . (($mes == 9)
            ? '<tr><td>Seguro y Cartolas</td><td align="right">' . number_format($v['seg_car'], 0, ',', '.') . '</td></tr>'
            : '<tr><td>Seguro y Cartolas</td><td align="right">---</td></tr>') . '
                    <tr><td>GPS</td><td align="right">' . number_format($v['gps'], 0, ',', '.') . '</td></tr>
                    <tr><td>Boleta Garantía</td><td align="right">' . number_format($v['bol_gar'], 0, ',', '.') . '</td></tr>
                    <tr><td>Asesorías Varias</td><td align="right">' . number_format($v['bol_ga2'], 0, ',', '.') . '</td></tr>
                    <tr bgcolor="#ddd"><td><b>SUBTOTAL CARGOS</b></td><td align="right"><b>' . number_format($total_nuevos_cargos, 0, ',', '.') . '</b></td></tr>
                </table>
            </td>

            <td width="24%" valign="top">
                <table width="100%" style="font-family:sans-serif; border: 1px solid #999; font-size:9px;">
                   <tr bgcolor="#eee"><td><b>RESUMEN TOTAL</b></td></tr>
                   <tr><td align="right">Total Haberes: $ ' . number_format($haberes, 0, ',', '.') . '</td></tr>
                   <tr><td align="right">Total Desc. + Cargos: $ ' . number_format($descuentos, 0, ',', '.') . '</td></tr>
                   <tr><td><br></td></tr> 
                   <tr bgcolor="#a3bce9ff" style="color:#fff;">
                       <td align="center" style="padding: 10px;">
                          <span style="font-size:10px;">SALDO FINAL A PAGAR</span><br>
                          <b style="font-size:14px;">$ ' . number_format($saldo_final, 0, ',', '.') . '</b>
                       </td>
                   </tr>
                </table>
            </td>
        </tr>
        </table>';

        $mpdf->WriteHTML($htmlResumen);
    }

    ob_end_clean();
    $mpdf->Output("Liquidacion_{$mes}_{$anio}.pdf", 'I');
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    die("Error PDF: " . $e->getMessage());
}
