<?php
// ajax/descargar_zip.php (INP INCLUIDO EN CESANTÍA)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['reportes']) || empty($data['reportes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron reportes.']);
    exit;
}
$reportes = $data['reportes'];
$organizacion = isset($data['organizacion']) ? $data['organizacion'] : 'mes'; // 'mes' o 'empleador'

try {
    $zip = new ZipArchive();
    $zipFileName = tempnam(sys_get_temp_dir(), 'reportes_') . '.zip';
    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('No se pudo crear el archivo ZIP.');
    }

    $css = file_get_contents(BASE_PATH . '/reportes/style.css');
    // $mes_zip y $ano_zip se usaban para formatear titulo global si fuera necesario, aqui usamos loop
    $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'LLLL');

    $stmt_sis = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes)) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");

    foreach ($reportes as $r) {
        $empleador_id = $r['empleador_id'];
        $mes = $r['mes'];
        $ano = $r['ano'];

        $fecha = DateTime::createFromFormat('!Y-n-d', "$ano-$mes-01");
        $mes_nombre = mb_strtoupper($formatter->format($fecha));

        $sql_e = "SELECT e.id, e.rut, e.nombre, c.nombre as caja_compensacion_nombre, m.nombre as mutual_seguridad_nombre, e.tasa_mutual_decimal FROM empleadores e LEFT JOIN cajas_compensacion c ON e.caja_compensacion_id = c.id LEFT JOIN mutuales_seguridad m ON e.mutual_seguridad_id = m.id WHERE e.id = ?";
        $stmt_e = $pdo->prepare($sql_e);
        $stmt_e->execute([$empleador_id]);
        $empleador = $stmt_e->fetch();

        // SQL Query actualizada (Step 152 fixes)
        $sql_p = "SELECT 
                t.rut as trabajador_rut, 
                t.nombre as trabajador_nombre, 
                t.estado_previsional as trabajador_estado_previsional, 
                t.sistema_previsional,
                t.tramo_asignacion_manual,
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
                (SELECT es_part_time FROM contratos c 
                 WHERE c.trabajador_id = t.id 
                 AND c.empleador_id = p.empleador_id
                 AND c.fecha_inicio <= LAST_DAY(CONCAT(p.ano, '-', p.mes, '-01'))
                 ORDER BY c.fecha_inicio DESC LIMIT 1) as es_part_time
              FROM planillas_mensuales p
              JOIN trabajadores t ON p.trabajador_id = t.id
              WHERE p.empleador_id = ? AND p.mes = ? AND p.ano = ?
              ORDER BY t.nombre ASC";
        $stmt_p = $pdo->prepare($sql_p);
        $stmt_p->execute([$empleador_id, $mes, $ano]);
        $registros = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

        if (empty($registros)) continue;

        // --- LÓGICA TRAMOS MANUAL ---
        $fecha_reporte = "$ano-" . str_pad($mes, 2, "0", STR_PAD_LEFT) . "-01";
        $sql_fecha_tramo = "SELECT MAX(fecha_inicio) as fecha_vigs FROM cargas_tramos_historicos WHERE fecha_inicio <= :fecha";
        $stmt_ft = $pdo->prepare($sql_fecha_tramo);
        $stmt_ft->execute([':fecha' => $fecha_reporte]);
        $fecha_vigente = $stmt_ft->fetchColumn();

        $tramos_del_periodo = [];
        if ($fecha_vigente) {
            $sql_tramos_final = "SELECT tramo, renta_maxima, monto_por_carga FROM cargas_tramos_historicos WHERE fecha_inicio = :fecha";
            $stmt_tf = $pdo->prepare($sql_tramos_final);
            $stmt_tf->execute([':fecha' => $fecha_vigente]);
            $tramos_del_periodo = $stmt_tf->fetchAll(PDO::FETCH_ASSOC);
        }

        $getTramoAutomatico = function ($sueldo, $tramos) {
            usort($tramos, function ($a, $b) {
                return $a['renta_maxima'] <=> $b['renta_maxima'];
            });
            foreach ($tramos as $t) {
                if ($sueldo <= $t['renta_maxima']) return $t;
            }
            return end($tramos);
        };
        $getTramoManual = function ($letra, $tramos) {
            foreach ($tramos as $t) if ($t['tramo'] == $letra) return $t;
            return null;
        };

        foreach ($registros as &$r) {
            $tramo_data = null;
            $origen_tramo = 'auto';
            $tramo_auto = $getTramoAutomatico($r['sueldo_imponible'], $tramos_del_periodo);
            if (!empty($r['tramo_asignacion_manual'])) {
                $tramo_manual = $getTramoManual($r['tramo_asignacion_manual'], $tramos_del_periodo);
                if ($tramo_manual) {
                    $tramo_data = $tramo_manual;
                    $origen_tramo = 'manual';
                } else {
                    $tramo_data = $tramo_auto;
                }
            } else {
                $tramo_data = $tramo_auto;
            }
            $r['tramo_letra'] = $tramo_data['tramo'] ?? '';
            if ($origen_tramo == 'manual') {
                $monto_unitario = (int)$tramo_data['monto_por_carga'];
                $num_cargas = (int)$r['numero_cargas'];
                $r['asignacion_familiar_calculada'] = $monto_unitario * $num_cargas;
                $r['saldo'] = ($r['aportes'] + $r['asignacion_familiar_calculada']) - $r['total_descuentos'];
            }
            if ($r['asignacion_familiar_calculada'] == 0) {
                $r['tramo_letra'] = '';
            }
        }
        unset($r);

        $totales_tabla = ['sueldo_imponible' => 0, 'descuento_afp' => 0, 'descuento_salud' => 0, 'adicional_salud_apv' => 0, 'seguro_cesantia' => 0, 'sindicato' => 0, 'cesantia_licencia_medica' => 0, 'total_descuentos' => 0, 'aportes' => 0, 'asignacion_familiar_calculada' => 0, 'saldo' => 0];
        foreach ($registros as $reg) foreach ($totales_tabla as $key => $value) if (isset($reg[$key])) $totales_tabla[$key] += $reg[$key];

        $stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
        $sis_data = $stmt_sis->fetch();
        $TASA_SIS_ACTUAL = $sis_data ? (float)$sis_data['tasa_sis_decimal'] : 0.0;
        if (!defined('TASA_CAP_IND_CONST')) define('TASA_CAP_IND_CONST', 0.001);
        if (!defined('TASA_EXP_VIDA_CONST')) define('TASA_EXP_VIDA_CONST', 0.009);

        // Imponible SIS
        $total_imponible_sis = 0;
        foreach ($registros as $reg) {
            if ($reg['sistema_previsional'] == 'AFP' && $reg['trabajador_estado_previsional'] != 'Pensionado') {
                $total_imponible_sis += $reg['sueldo_imponible'];
            }
        }

        $tasa_mutual_actual = $empleador['tasa_mutual_decimal'];
        $calc_aporte_patronal = calcular_mutual($totales_tabla['sueldo_imponible'], $tasa_mutual_actual);

        $calc_seguro_cesantia_patronal = 0;
        foreach ($registros as $r) {
            $calc_seguro_cesantia_patronal += calcular_cesantia_patronal(
                $r['sueldo_imponible'],
                $r['tipo_contrato'],
                $r['trabajador_estado_previsional'],
                $r['cotiza_cesantia_pensionado'] ?? 0
            );
        }

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

        // Instanciar Template PDF
        ob_start();
        require dirname(__DIR__) . '/reportes/reporte_template.php';
        $html_body = ob_get_clean();

        // 5mm de márgenes L/R/B y 20mm Top
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 5, 'margin_right' => 5, 'margin_top' => 20, 'margin_bottom' => 5]);

        // Header HTML
        $header_html = '
        <div class="info-header">
            <table class="header-table">
                <tr>
                    <td class="header-logo">TRANSREPORT<br><span style="font-size:7px; font-weight:normal;">Software de Gestión</span></td>
                    <td class="header-title">
                        <h1>Planilla de Cotizaciones</h1>
                        <span class="sub-title">PREVISIONALES | MENSUAL</span>
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
        $footer_html = '<table width="100%" style="font-size: 8pt; color: #888;"><tr><td width="50%"></td><td width="50%" style="text-align: right;">Página {PAGENO} de {nbpg}</td></tr></table>';
        $mpdf->SetHTMLFooter($footer_html);

        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html_body, \Mpdf\HTMLParserMode::HTML_BODY);
        $pdfString = $mpdf->Output('', 'S');

        // Determinar la ruta del archivo según la organización
        // REFACTORIZADO: Usar función centralizada
        $mes_nombre_corto = get_mes_nombre($mes);

        // Limpiar nombre del empleador para archivo: eliminar caracteres especiales pero permitir espacios
        $nombre_archivo_limpio = preg_replace('/[^a-zA-Z0-9 ]/', '', $empleador['nombre']);
        $nombre_archivo_limpio = trim($nombre_archivo_limpio);

        $rut_limpio = preg_replace('/[^0-9kK]/', '', $empleador['rut']);
        $nombre_empleador_carpeta = trim($empleador['nombre']);

        if ($organizacion === 'empleador') {
            $carpeta = "{$nombre_empleador_carpeta}/{$mes_nombre_corto}_{$ano}";
            $nombre_archivo = "{$nombre_archivo_limpio}_{$mes_nombre_corto}_{$ano}.pdf";
            $pdfFileName = "{$carpeta}/{$nombre_archivo}";
        } else {
            $carpeta = "{$mes_nombre_corto}_{$ano}";
            $nombre_archivo = "{$nombre_archivo_limpio}.pdf";
            $pdfFileName = "{$carpeta}/{$nombre_archivo}";
        }

        $zip->addFromString($pdfFileName, $pdfString);
    }

    $zip->close();

    if ($organizacion === 'empleador') {
        $zipBasename = "Reportes_PorEmpleador_" . date('Y-m-d') . ".zip";
    } else {
        $zipBasename = "Reportes_PorMes_" . date('Y-m-d') . ".zip";
    }
    if (ob_get_length()) ob_clean();
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
