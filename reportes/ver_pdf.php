<?php
// Reporte de Cotizaciones - ver_pdf.php (Días Trab. Corregido)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

date_default_timezone_set('America/Santiago');
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain', 'Spanish');

if (!isset($_GET['id']) || !isset($_GET['mes']) || !isset($_GET['ano'])) {
    die("Error: Faltan parámetros para generar el reporte.");
}
$empleador_id = (int)$_GET['id'];
$mes = (int)$_GET['mes'];
$ano = (int)$_GET['ano'];

// CORRECCIÓN STRFTIME (IntlDateFormatter)
$fecha = DateTime::createFromFormat('!Y-n-d', "$ano-$mes-01");
$formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'LLLL');
$mes_nombre = mb_strtoupper($formatter->format($fecha));

// ===================================================================
// 4. CARGA DE DATOS REALES (DE LA BD)
// ===================================================================

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
    $empleador = $stmt_e->fetch();
    $empleador['sucursal'] = 'N/A';

    // B. Registros de Planilla (CORRECCIÓN BUG 4)
    $sql_p = "SELECT 
                t.rut as trabajador_rut, 
                t.nombre as trabajador_nombre, 
                t.estado_previsional as trabajador_estado_previsional, 
                p.dias_trabajados, -- <-- CAMBIO: Columna añadida
                p.tipo_contrato, p.sueldo_imponible, p.descuento_afp,
                p.descuento_salud, p.adicional_salud_apv, p.seguro_cesantia,
                p.sindicato, p.cesantia_licencia_medica, p.aportes,
                p.asignacion_familiar_calculada,
                (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as total_descuentos,
                (p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as saldo
              FROM planillas_mensuales p
              JOIN trabajadores t ON p.trabajador_id = t.id
              WHERE p.empleador_id = ? AND p.mes = ? AND p.ano = ?
              ORDER BY t.nombre ASC";
    $stmt_p = $pdo->prepare($sql_p);
    $stmt_p->execute([$empleador_id, $mes, $ano]);
    $registros = $stmt_p->fetchAll();

    if (empty($registros)) {
        die("No se encontraron datos de planilla para este período.");
    }
} catch (Exception $e) {
    die("Error al cargar datos de la BD: " . "<pre>" . $e->getMessage() . "</pre>");
}

// ===================================================================
// 5. FUNCIONES HELPER DE FORMATO
// ===================================================================
function format_numero($num)
{
    if ($num === null || $num === 0) return "0";
    return number_format(intval($num), 0, ',', '.');
}
function format_rut($rut_str)
{
    if (empty($rut_str)) return "";
    $rut_str = str_replace('.', '', $rut_str);
    $partes = explode('-', $rut_str);
    if (count($partes) != 2) return $rut_str;
    $cuerpo = $partes[0];
    $verificador = $partes[1];
    $cuerpo_formateado = number_format(intval($cuerpo), 0, ',', '.');
    return $cuerpo_formateado . '-' . $verificador;
}

// ===================================================================
// 6. LÓGICA DE CÁLCULO
// ===================================================================

// A. Cálculo de totales_tabla (CORRECCIÓN BUG 4)
$totales_tabla = ['sueldo_imponible' => 0, 'descuento_afp' => 0, 'descuento_salud' => 0, 'adicional_salud_apv' => 0, 'seguro_cesantia' => 0, 'sindicato' => 0, 'cesantia_licencia_medica' => 0, 'total_descuentos' => 0, 'aportes' => 0, 'asignacion_familiar_calculada' => 0, 'saldo' => 0, 'dias_trabajados' => 0]; // <-- Añadido dias_trabajados
foreach ($registros as $r) {
    foreach ($totales_tabla as $key => $value) {
        if (isset($r[$key])) $totales_tabla[$key] += $r[$key];
    }
}

// B. Cálculo de calculos_pie (Lógica SIS + Cap + Exp)
$stmt_sis = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes)) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");
$stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
$sis_data = $stmt_sis->fetch();
$TASA_SIS_ACTUAL = $sis_data ? (float)$sis_data['tasa_sis_decimal'] : 0.0;
define('TASA_CAP_IND_CONST', 0.001);
define('TASA_EXP_VIDA_CONST', 0.009);
$total_imponible_sis = 0;
foreach ($registros as $r) if ($r['trabajador_estado_previsional'] != 'Pensionado') $total_imponible_sis += $r['sueldo_imponible'];
$total_imponible_fijo = 0;
$total_imponible_indefinido = 0;
foreach ($registros as $r) {
    if ($r['trabajador_estado_previsional'] == 'Pensionado') continue;
    if ($r['tipo_contrato'] == 'Fijo') $total_imponible_fijo += $r['sueldo_imponible'];
    else $total_imponible_indefinido += $r['sueldo_imponible'];
}
$tasa_mutual_actual = $empleador['tasa_mutual_decimal'];
$calc_aporte_patronal = floor($totales_tabla['sueldo_imponible'] * $tasa_mutual_actual);
$calc_cesantia_fijo = floor($total_imponible_fijo * 0.03);
$calc_cesantia_indefinido = floor($total_imponible_indefinido * 0.024);
$calc_seguro_cesantia_patronal = $calc_cesantia_fijo + $calc_cesantia_indefinido;
$calc_sis = floor($total_imponible_sis * $TASA_SIS_ACTUAL);
$calc_capitalizacion_individual = floor($total_imponible_sis * TASA_CAP_IND_CONST);
$calc_expectativa_vida = floor($total_imponible_sis * TASA_EXP_VIDA_CONST);
$sub_total_desc_tabla = $totales_tabla['total_descuentos'];
$sub_total_desc_final = $sub_total_desc_tabla + $calc_aporte_patronal + $calc_sis + $calc_seguro_cesantia_patronal + $calc_capitalizacion_individual + $calc_expectativa_vida;
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

// ===================================================================
// 7. CARGA DE ASSETS Y RENDERIZADO DE mPDF
// ===================================================================
try {
    $css = file_get_contents(__DIR__ . '/style.css');
    ob_start();
    include __DIR__ . '/reporte_template.php';
    $html_body = ob_get_clean();

    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 35, 'margin_bottom' => 20]);
    $mpdf->SetTitle("Planilla $mes-$ano - " . $empleador['nombre']);
    $mpdf->SetAuthor("Sistema de Reportes Daiko");

    // Header
    $header_html = '
    <div class="info-header">
        <div class="info-izq">
            <span class="label">PERÍODO:</span>
            <span class="data">' . $mes_nombre . ' / ' . $ano . '</span>
        </div>
        <div class="info-der">
            <h1 style="font-size: 16pt; margin:0; padding:0;">Planilla de Cotizaciones Previsionales</h1>
            <span class="label">Emisión:</span> ' . date('d/m/Y - H:i') . '
        </div>
    </div>
    <div style="border-bottom: 2px solid #000; margin-top: 10px;"></div>';
    $mpdf->SetHTMLHeader($header_html);
    // Footer
    $footer_html = '
    <table width="100%" style="font-size: 8pt; color: #888;">
        <tr>
            <td width="50%">Reporte generado por Sistema de Reportes Daiko.</td>
            <td width="50%" style="text-align: right;">Página {PAGENO} de {nbpg}</td>
        </tr>
    </table>';
    $mpdf->SetHTMLFooter($footer_html);

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html_body, \Mpdf\HTMLParserMode::HTML_BODY);
    $mpdf->Output('Reporte.pdf', 'I');
    exit;
} catch (\Exception $e) {
    echo "<h1>Error al generar el PDF</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
