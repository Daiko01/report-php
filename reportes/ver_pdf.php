<?php
// Reporte de Cotizaciones - ver_pdf.php (INP INCLUIDO EN CESANTÍA)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

date_default_timezone_set('America/Santiago');
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain', 'Spanish');

if (!isset($_GET['id']) || !isset($_GET['mes']) || !isset($_GET['ano'])) {
    die("Error: Faltan parámetros.");
}
$empleador_id = (int)$_GET['id'];
$mes = (int)$_GET['mes'];
$ano = (int)$_GET['ano'];

$fecha = DateTime::createFromFormat('!Y-n-d', "$ano-$mes-01");
$formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'LLLL');
$mes_nombre = mb_strtoupper($formatter->format($fecha));

try {
    // A. Empleador
    $sql_e = "SELECT e.id, e.rut, e.nombre, c.nombre as caja_compensacion_nombre, 
                     m.nombre as mutual_seguridad_nombre, e.tasa_mutual_decimal
              FROM empleadores e
              LEFT JOIN cajas_compensacion c ON e.caja_compensacion_id = c.id
              LEFT JOIN mutuales_seguridad m ON e.mutual_seguridad_id = m.id
              WHERE e.id = ?";
    $stmt_e = $pdo->prepare($sql_e);
    $stmt_e->execute([$empleador_id]);
    $empleador = $stmt_e->fetch(PDO::FETCH_ASSOC);


    // B. Registros
    $sql_p = "SELECT 
                t.rut as trabajador_rut, 
                t.nombre as trabajador_nombre, 
                t.estado_previsional as trabajador_estado_previsional, 
                t.sistema_previsional,
                t.tramo_asignacion_manual,
                t.numero_cargas,
                t.numero_cargas,
                p.afp_historico_nombre,
                p.dias_trabajados,
                p.tipo_contrato, 
                p.sueldo_imponible, 
                p.descuento_afp,
                p.descuento_salud, 
                p.adicional_salud_apv, 
                p.seguro_cesantia,
                p.sindicato, 
                p.cesantia_licencia_medica, 
                p.aportes,
                p.asignacion_familiar_calculada,
                p.cotiza_cesantia_pensionado,
                p.tasa_mutual_aplicada,
                p.sis_aplicado,
                p.tipo_asignacion_familiar,
                p.tramo_asignacion_familiar,
                (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as total_descuentos,
                (p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as saldo,
                p.es_part_time_snapshot as es_part_time,
                p.sueldo_base_snapshot
              FROM planillas_mensuales p
              JOIN trabajadores t ON p.trabajador_id = t.id
              WHERE p.empleador_id = ? AND p.mes = ? AND p.ano = ?
              ORDER BY t.nombre ASC";

    $stmt_p = $pdo->prepare($sql_p);
    $stmt_p->execute([$empleador_id, $mes, $ano]);
    $registros = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

    if (empty($registros)) die("No se encontraron datos.");
} catch (Exception $e) {
    die("Error BD: " . $e->getMessage());
}



// --- CÁLCULOS ---
// Reiniciar totales que dependen de la iteración (se recalcularán)
$totales_tabla = ['sueldo_imponible' => 0, 'descuento_afp' => 0, 'descuento_salud' => 0, 'adicional_salud_apv' => 0, 'seguro_cesantia' => 0, 'sindicato' => 0, 'cesantia_licencia_medica' => 0, 'total_descuentos' => 0, 'aportes' => 0, 'asignacion_familiar_calculada' => 0, 'saldo' => 0];

// PROCESAMIENTO DE REGISTROS (Lógica de Tramos)
foreach ($registros as &$r) {
    // USAR SNAPSHOT DE TRAMO
    // Si la columna existe (migración) y tiene dato, usarla.
    if (!empty($r['tramo_asignacion_familiar'])) {
        $r['tramo_letra'] = $r['tramo_asignacion_familiar'];
    } else {
        // FALLBACK (Solo si es antiguo y migración falló, o futuro sin dato)
        // Por seguridad, dejamos vacío o calculamos. 
        // Si la migración corrió, debería estar. Si está vacía, asumimos sin tramo.
        $r['tramo_letra'] = '';
    }

    // --- CORRECCIÓN FINAL: Si monto es 0, ocultar letra tramo ---
    if ($r['asignacion_familiar_calculada'] == 0) {
        $r['tramo_letra'] = '';
    }

    // 4. Recalcular SALDO 
    $r['saldo'] = ($r['aportes'] + $r['asignacion_familiar_calculada']) - $r['total_descuentos'];

    // 5. Acumular Totales
    foreach ($totales_tabla as $key => $val) {
        if (isset($r[$key])) $totales_tabla[$key] += $r[$key];
    }
}
unset($r); // romper referencia

// --- DATOS GLOBALES SNAPSHOT ---
// Tomamos del primer registro (son iguales para el empleador/mes)
$first_reg = $registros[0];
// Si es 0.00, fallback a lo actual ?? No, snapshot es truth. 
// Dividimos por 100 porque lo guardamos como porcentaje (ej 3.40) y aqui variable espera 0.034
$TASA_SIS_ACTUAL = isset($first_reg['sis_aplicado']) ? ((float)$first_reg['sis_aplicado'] / 100) : 0.0;
$tasa_mutual_actual = isset($first_reg['tasa_mutual_aplicada']) ? ((float)$first_reg['tasa_mutual_aplicada'] / 100) : 0.0;


define('TASA_CAP_IND_CONST', 0.001);
define('TASA_EXP_VIDA_CONST', 0.009);

// 1. Calcular Base Imponible SIS (Solo AFP y Activos)
$total_imponible_sis = 0;
foreach ($registros as $r) {
    if ($r['sistema_previsional'] == 'AFP' && $r['trabajador_estado_previsional'] != 'Pensionado') {
        $total_imponible_sis += $r['sueldo_imponible'];
    }
}

// $tasa_mutual_actual YA FUE SETEADA ARRIBA DESDE SNAPSHOT
// $tasa_mutual_actual YA FUE SETEADA ARRIBA DESDE SNAPSHOT
$calc_aporte_patronal = calcular_mutual($totales_tabla['sueldo_imponible'], $tasa_mutual_actual);

// 2. Calcular Seguro Cesantía Patronal (CORREGIDO: Verifica Activo o Pensionado con AFC)
$calc_seguro_cesantia_patronal = 0;
foreach ($registros as $r) {
    $c = calcular_cesantia_patronal($r['sueldo_imponible'], $r['tipo_contrato'], $r['trabajador_estado_previsional'], $r['cotiza_cesantia_pensionado'] ?? 0);
    $calc_seguro_cesantia_patronal += $c;
}    // Si es pensionado estándar (sin AFC), no paga cesantía patronal (0%)


// --- LOGICA SIS PARA LICENCIAS (INYECCIÓN DE COSTO) ---
// --- LOGICA SIS PARA LICENCIAS (INYECCIÓN DE COSTO) ---
$sis_extra_licencias = 0;
$cap_ind_extra_licencias = 0;
$exp_vida_extra_licencias = 0;

// 1. Obtener Tasa SIS Histórica
$stmt_sis_rate = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE ano_inicio < ? OR (ano_inicio = ? AND mes_inicio <= ?) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");
$stmt_sis_rate->execute([$ano, $ano, $mes]);
$rate_row = $stmt_sis_rate->fetch(PDO::FETCH_ASSOC);
$TASA_SIS_HISTORICA = $rate_row ? (float)$rate_row['tasa_sis_decimal'] : 0.0149; // Fallback 1.49%

// 2. Buscar trabajadores con licencia en este periodo
// (Optimizacion: Traer solo licencias que intersecten con el mes)
$fecha_inicio_mes = "$ano-$mes-01";
$fecha_fin_mes = date("Y-m-t", strtotime($fecha_inicio_mes));

$stmt_lic = $pdo->prepare("
    SELECT trabajador_id, base_imponible_manual 
    FROM trabajador_licencias 
    WHERE (fecha_inicio <= ? AND fecha_fin >= ?) 
");
// Intersección: inicio_lic <= fin_mes AND fin_lic >= inicio_mes
$stmt_lic->execute([$fecha_fin_mes, $fecha_inicio_mes]);
$licencias_map = $stmt_lic->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

// 3. Preparar datos del mes anterior (para fallback de base imponible)
$mes_ant = $mes - 1;
$ano_ant = $ano;
if ($mes_ant == 0) {
    $mes_ant = 12;
    $ano_ant = $ano - 1;
}
$stmt_prev = $pdo->prepare("SELECT trabajador_id, sueldo_imponible FROM planillas_mensuales WHERE mes = ? AND ano = ? AND empleador_id = ?");
$stmt_prev->execute([$mes_ant, $ano_ant, $empleador_id]);
$prev_income_map = $stmt_prev->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. Calcular SIS Extra
foreach ($registros as $r) {
    // Solo si tiene licencia vigente en el periodo
    if (isset($licencias_map[$r['trabajador_rut']])) {
        // ERROR: licencias_map usa trabajador_id, pero registros no tiene ID limpio, 
        // registros viene de planillas_mensuales JOIN trabajadores.
        // Debemos buscar el ID del trabajador.
        // En registros (line 39) no seleccionamos t.id explícitamente, pero p.trabajador_id debe estar en el join implícito o lo agregamos.
        // Revisando SQL linea 39: SELECT t.rut... 
        // No tenemos el ID directo en $r.
        // Solucion: Usar RUT en licencias map o agregar t.id a la query principal. 
        // MODIFICACION: Agregaré t.id a la query principal más abajo, pero como no puedo editar todo el archivo, 
        // usaré una query auxiliar aqui o iteraré comparando RUT si fuera necesario.
        // MEJOR OPCION: Asumir que licencias_map usa ID y tratar de mapear con lo que tengo.
        // NO tengo ID en $r. 
        // VOY A MODIFICAR ESTE BLOQUE PARA QUE HAGA UNA BÚSQUEDA DEL ID O USE EL RUT.
    }
}
// RE-STRATEGY: I need t.id in $registros.
// I will fetch t.id in the main query first. 
// Wait, I am replacing a block deep down. I cannot easily change the main query without a massive replace.
// Alternative: Fetch licenses mapped by RUT.
// Table `trabajador_licencias` has `trabajador_id`. 
// I can join `trabajadores` in the license query below.

$stmt_lic_rut = $pdo->prepare("
    SELECT t.rut, l.base_imponible_manual 
    FROM trabajador_licencias l
    JOIN trabajadores t ON l.trabajador_id = t.id
    WHERE (l.fecha_inicio <= ? AND l.fecha_fin >= ?) 
");
$stmt_lic_rut->execute([$fecha_fin_mes, $fecha_inicio_mes]);
$licencias_rut_map = $stmt_lic_rut->fetchAll(PDO::FETCH_KEY_PAIR);
// Map: RUT => base_imponible_manual (or null)

// Map Previous Income by RUT too (since planillas has trabajador_id, we need rut)
// Actually planillas has trabajador_id. Current query $registros has rut.
// Let's map prev income by RUT.
$stmt_prev_rut = $pdo->prepare("
    SELECT t.rut, p.sueldo_imponible 
    FROM planillas_mensuales p
    JOIN trabajadores t ON p.trabajador_id = t.id
    WHERE p.mes = ? AND p.ano = ? AND p.empleador_id = ?
");
$stmt_prev_rut->execute([$mes_ant, $ano_ant, $empleador_id]);
$prev_income_rut_map = $stmt_prev_rut->fetchAll(PDO::FETCH_KEY_PAIR);


foreach ($registros as $r) {
    $usu_rut = $r['trabajador_rut'];

    if (array_key_exists($usu_rut, $licencias_rut_map)) {
        // TIENE LICENCIA
        $base_calculo = 0;
        $base_manual = $licencias_rut_map[$usu_rut]; // puede ser null o valor

        // PRIORIDAD 1: Manual
        if ($base_manual !== null && $base_manual > 0) {
            $base_calculo = $base_manual;
        }
        // PRIORIDAD 2: Mes Anterior
        elseif (isset($prev_income_rut_map[$usu_rut]) && $prev_income_rut_map[$usu_rut] > 0) {
            $base_calculo = $prev_income_rut_map[$usu_rut];
        }
        // PRIORIDAD 3: Sueldo Base Contrato (Snapshot actual)
        else {
            $base_calculo = isset($r['sueldo_base_snapshot']) ? $r['sueldo_base_snapshot'] : 0;
        }

        // CALCULO
        $costo_sis_licencia = floor($base_calculo * $TASA_SIS_HISTORICA);
        $sis_extra_licencias += $costo_sis_licencia;

        // CALCULO EXTRAS
        $cap_ind_extra_licencias += floor($base_calculo * TASA_CAP_IND_CONST);
        $exp_vida_extra_licencias += floor($base_calculo * TASA_EXP_VIDA_CONST);
    }
}

$calc_sis = floor($total_imponible_sis * $TASA_SIS_ACTUAL) + $sis_extra_licencias;
$calc_capitalizacion_individual = floor($total_imponible_sis * TASA_CAP_IND_CONST) + $cap_ind_extra_licencias;
$calc_expectativa_vida = floor($total_imponible_sis * TASA_EXP_VIDA_CONST) + $exp_vida_extra_licencias;

$sub_total_desc_tabla = $totales_tabla['total_descuentos'];
$sub_total_desc_final = $sub_total_desc_tabla + $calc_aporte_patronal + $calc_sis +
    $calc_seguro_cesantia_patronal + $calc_capitalizacion_individual + $calc_expectativa_vida;

$aportes_conductor = $totales_tabla['aportes'];
$saldos_positivos = 0;
foreach ($registros as $r) if ($r['saldo'] > 0) $saldos_positivos += $r['saldo'];
$total_descuentos_final = $sub_total_desc_final - $aportes_conductor + $saldos_positivos;
$total_asignacion = $totales_tabla['asignacion_familiar_calculada'];
$leyes_sociales_empleado = $totales_tabla['total_descuentos'] - $totales_tabla['sindicato'];
$leyes_sociales_empleador = $calc_sis + $calc_seguro_cesantia_patronal + $calc_capitalizacion_individual + $calc_expectativa_vida;
$total_leyes_sociales = $leyes_sociales_empleado + $leyes_sociales_empleador + $calc_aporte_patronal - $total_asignacion;

$calculos_pie = [
    'sub_total_desc_tabla' => $sub_total_desc_tabla,
    'aporte_patronal' => $calc_aporte_patronal,
    'sis' => $calc_sis,
    'seguro_cesantia_patronal' => $calc_seguro_cesantia_patronal,
    'capitalizacion_individual' => $calc_capitalizacion_individual,
    'expectativa_vida' => $calc_expectativa_vida,
    'sub_total_desc_pie' => $sub_total_desc_final,
    'aportes_conductor' => $aportes_conductor,
    'saldos_positivos' => $saldos_positivos,
    'total_descuentos_final' => $total_descuentos_final,
    'asignacion_familiar' => $total_asignacion,
    'sindicato' => $totales_tabla['sindicato'],
    'total_leyes_sociales' => $total_leyes_sociales,
    'tasa_mutual_aplicada_porc' => $tasa_mutual_actual * 100
];

// --- GENERAR PDF ---
try {
    $css = file_get_contents(__DIR__ . '/style.css');

    // Capturar HTML desde el template
    ob_start();
    require __DIR__ . '/reporte_template.php';
    $html_body = ob_get_clean();

    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 5, 'margin_right' => 5, 'margin_top' => 20, 'margin_bottom' => 5]);
    // --- Header Estético Moderno ---
    $header_html = '
    <div class="info-header">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    TRANSREPORT<br><span style="color:#718096; font-weight:normal; font-size: 8pt;">Software de Gestión</span>
                </td>
                <td class="header-title">
                    <h1>Planilla de Cotizaciones Previsionales</h1>
                </td>
                <td class="header-meta">
                    <div class="meta-box">
                        <span class="periodo">' . $mes_nombre . ' ' . $ano . '</span>
                        <span class="emision">EMISIÓN: ' . date('d/m/Y H:i') . '</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>';
    $mpdf->SetHTMLHeader($header_html);
    $footer_html = '<table width="100%" style="font-size: 8pt; color: #888;"><tr><td width="50%">Reporte generado por Sistema de Reportes Transreport.</td><td width="50%" style="text-align: right;">Página {PAGENO} de {nbpg}</td></tr></table>';
    $mpdf->SetHTMLFooter($footer_html);

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html_body, \Mpdf\HTMLParserMode::HTML_BODY);

    // Configurar Título del Documento (Pestaña del Navegador)
    $titulo_pdf = "Planilla de Cotizaciones - " . $empleador['nombre'] . " - " . $mes_nombre . " " . $ano;
    $mpdf->SetTitle($titulo_pdf);

    // Nombre de archivo descriptivo para descarga (Sanitizado)
    // Se permiten espacios en el título, pero para archivo usualmente se prefieren guiones bajos, 
    // aunque los navegadores modernos manejan bien los espacios. 
    // Usaremos guiones bajos para máxima compatibilidad como solicitado implícitamente en el "guardar como".
    $nombre_file = preg_replace('/[^a-zA-Z0-9]/', '_', $empleador['nombre']);
    $nombre_descarga = "Planilla_Cotizaciones_" . $nombre_file . "_" . $mes_nombre . "_" . $ano . ".pdf";

    $mpdf->Output($nombre_descarga, 'I');
    exit;
} catch (\Exception $e) {
    echo "<h1>Error al generar el PDF</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
