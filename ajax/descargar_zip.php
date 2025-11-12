<?php
// ajax/descargar_zip.php (Días Trab. Corregido)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

// Funciones Helper
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

// Recibir JSON
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['reportes']) || empty($data['reportes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron reportes.']);
    exit;
}
$reportes = $data['reportes'];

try {
    $zip = new ZipArchive();
    $zipFileName = tempnam(sys_get_temp_dir(), 'reportes_') . '.zip';
    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('No se pudo crear el archivo ZIP.');
    }

    $css = file_get_contents(BASE_PATH . '/reportes/style.css');
    $template_path = BASE_PATH . '/reportes/reporte_template.php';

    $mes_zip = $reportes[0]['mes'];
    $ano_zip = $reportes[0]['ano'];

    // CORRECCIÓN STRFTIME (IntlDateFormatter)
    $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'LLLL');

    $stmt_sis = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes)) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");

    foreach ($reportes as $r) {
        $empleador_id = $r['empleador_id'];
        $mes = $r['mes'];
        $ano = $r['ano'];

        $fecha = DateTime::createFromFormat('!Y-n-d', "$ano-$mes-01");
        $mes_nombre = mb_strtoupper($formatter->format($fecha));

        // --- INICIO DE LÓGICA DE CÁLCULO ---
        // A. Cargar datos
        $sql_e = "SELECT e.id, e.rut, e.nombre, c.nombre as caja_compensacion_nombre, m.nombre as mutual_seguridad_nombre, e.tasa_mutual_decimal FROM empleadores e LEFT JOIN cajas_compensacion c ON e.caja_compensacion_id = c.id LEFT JOIN mutuales_seguridad m ON e.mutual_seguridad_id = m.id WHERE e.id = ?";
        $stmt_e = $pdo->prepare($sql_e);
        $stmt_e->execute([$empleador_id]);
        $empleador = $stmt_e->fetch();
        $empleador['sucursal'] = 'N/A';

        // CORRECCIÓN BUG 4: Añadir p.dias_trabajados
        $sql_p = "SELECT t.rut as trabajador_rut, t.nombre as trabajador_nombre, t.estado_previsional as trabajador_estado_previsional, p.dias_trabajados, p.*, (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as total_descuentos, (p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as saldo FROM planillas_mensuales p JOIN trabajadores t ON p.trabajador_id = t.id WHERE p.empleador_id = ? AND p.mes = ? AND p.ano = ? ORDER BY t.nombre ASC";
        $stmt_p = $pdo->prepare($sql_p);
        $stmt_p->execute([$empleador_id, $mes, $ano]);
        $registros = $stmt_p->fetchAll();

        if (empty($registros)) continue;

        // B. Calcular totales_tabla (CORRECCIÓN BUG 4: Añadir dias_trabajados)
        $totales_tabla = ['sueldo_imponible' => 0, 'descuento_afp' => 0, 'descuento_salud' => 0, 'adicional_salud_apv' => 0, 'seguro_cesantia' => 0, 'sindicato' => 0, 'cesantia_licencia_medica' => 0, 'total_descuentos' => 0, 'aportes' => 0, 'asignacion_familiar_calculada' => 0, 'saldo' => 0, 'dias_trabajados' => 0];
        foreach ($registros as $reg) foreach ($totales_tabla as $key => $value) if (isset($reg[$key])) $totales_tabla[$key] += $reg[$key];

        // C. Calcular calculos_pie (Lógica SIS + Cap + Exp)
        $stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
        $sis_data = $stmt_sis->fetch();
        $TASA_SIS_ACTUAL = $sis_data ? (float)$sis_data['tasa_sis_decimal'] : 0.0;
        if (!defined('TASA_CAP_IND_CONST')) define('TASA_CAP_IND_CONST', 0.001);
        if (!defined('TASA_EXP_VIDA_CONST')) define('TASA_EXP_VIDA_CONST', 0.009);
        $total_imponible_sis = 0;
        foreach ($registros as $reg) if ($reg['trabajador_estado_previsional'] != 'Pensionado') $total_imponible_sis += $reg['sueldo_imponible'];
        $total_imponible_fijo = 0;
        $total_imponible_indefinido = 0;
        foreach ($registros as $reg) {
            if ($reg['trabajador_estado_previsional'] == 'Pensionado') continue;
            if ($reg['tipo_contrato'] == 'Fijo') $total_imponible_fijo += $reg['sueldo_imponible'];
            else $total_imponible_indefinido += $reg['sueldo_imponible'];
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
        foreach ($registros as $reg) if ($reg['saldo'] > 0) $saldos_positivos += $reg['saldo'];
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
        // --- FIN DE LÓGICA DE CÁLCULO ---

        // D. Renderizar HTML
        ob_start();
        include $template_path;
        $html_body = ob_get_clean();

        // E. Generar PDF y añadir al ZIP
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 35, 'margin_bottom' => 20]);
        $header_html = '<div class="info-header"><div class="info-izq"><span class="label">PERÍODO:</span><span class="data">' . $mes_nombre . ' / ' . $ano . '</span></div><div class="info-der"><h1 style="font-size: 16pt; margin:0; padding:0;">Planilla de Cotizaciones Previsionales</h1><span class="label">Emisión:</span> ' . date('d/m/Y - H:i') . '</div></div><div style="border-bottom: 2px solid #000; margin-top: 10px;"></div>';
        $mpdf->SetHTMLHeader($header_html);
        $footer_html = '<table width="100%" style="font-size: 8pt; color: #888;"><tr><td width="50%">Reporte generado por Sistema de Reportes Daiko.</td><td width="50%" style="text-align: right;">Página {PAGENO} de {nbpg}</td></tr></table>';
        $mpdf->SetHTMLFooter($footer_html);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html_body, \Mpdf\HTMLParserMode::HTML_BODY);
        $pdfString = $mpdf->Output('', 'S');

        $pdfFileName = "Planilla_E{$empleador_id}_{$mes}-{$ano}.pdf";
        $zip->addFromString($pdfFileName, $pdfString);
    }

    $zip->close();

    // 7. Forzar la descarga del ZIP
    $zipBasename = "ReportesMasivos_{$mes_zip}-{$ano_zip}.zip";
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipBasename . '"');
    header('Content-Length: ' . filesize($zipFileName));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($zipFileName);
    unlink($zipFileName);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al generar el ZIP: ' . $e->getMessage()]);
    exit;
}
