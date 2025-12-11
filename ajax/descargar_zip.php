<?php
// ajax/descargar_zip.php (INP INCLUIDO EN CESANTÍA)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

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
    $mes_zip = $reportes[0]['mes'];
    $ano_zip = $reportes[0]['ano'];
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

        $sql_p = "SELECT t.rut as trabajador_rut, t.nombre as trabajador_nombre, t.estado_previsional as trabajador_estado_previsional, t.sistema_previsional, t.tramo_asignacion_manual, t.numero_cargas, a.nombre as afp_nombre, p.dias_trabajados, p.tipo_contrato, p.sueldo_imponible, p.descuento_afp, p.descuento_salud, p.adicional_salud_apv, p.seguro_cesantia, p.sindicato, p.cesantia_licencia_medica, p.aportes, p.asignacion_familiar_calculada, p.cotiza_cesantia_pensionado, (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as total_descuentos, (p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as saldo, 
                  (SELECT es_part_time FROM contratos c WHERE c.trabajador_id = t.id AND c.empleador_id = p.empleador_id AND c.fecha_inicio <= LAST_DAY(CONCAT(p.ano, '-', p.mes, '-01')) ORDER BY c.fecha_inicio DESC LIMIT 1) as es_part_time
                  FROM planillas_mensuales p JOIN trabajadores t ON p.trabajador_id = t.id LEFT JOIN afps a ON t.afp_id = a.id WHERE p.empleador_id = ? AND p.mes = ? AND p.ano = ? ORDER BY t.nombre ASC";
        $stmt_p = $pdo->prepare($sql_p);
        $stmt_p->execute([$empleador_id, $mes, $ano]);
        $registros = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

        if (empty($registros)) continue;

        // --- LÓGICA TRAMOS MANUAL (Copiada de ver_pdf.php) ---
        $fecha_reporte = "$ano-" . str_pad($mes, 2, "0", STR_PAD_LEFT) . "-01";
        
        // Buscar fecha vigente más reciente
        $sql_fecha_tramo = "SELECT MAX(fecha_inicio) as fecha_vigs FROM cargas_tramos_historicos WHERE fecha_inicio <= :fecha";
        $stmt_ft = $pdo->prepare($sql_fecha_tramo);
        $stmt_ft->execute([':fecha' => $fecha_reporte]);
        $fecha_vigente = $stmt_ft->fetchColumn();
        
        $tramos_del_periodo = [];
        if($fecha_vigente) {
             $sql_tramos_final = "SELECT tramo, renta_maxima, monto_por_carga 
                                 FROM cargas_tramos_historicos 
                                 WHERE fecha_inicio = :fecha";
            $stmt_tf = $pdo->prepare($sql_tramos_final);
            $stmt_tf->execute([':fecha' => $fecha_vigente]);
            $tramos_del_periodo = $stmt_tf->fetchAll(PDO::FETCH_ASSOC);
        }

        // Definir funciones helper dentro del loop (o fuera si se prefiere, pero PHP permite re-definir si no son globales o usar closure)
        // Para evitar error "Cannot redeclare", verificamos si existe o usamos closure.
        // Dado que estamos dentro de un loop (foreach reportes), definirlas aqui daría error.
        // Las moveremos afuera del loop o las renombramos. Lo mejor es moverlas al inicio del archivo o usar closures.
        // Usaré closures asignadas a variables para evitar conflictos.
        
        $getTramoAutomatico = function($sueldo, $tramos) {
            usort($tramos, function($a, $b) { return $a['renta_maxima'] <=> $b['renta_maxima']; });
            foreach ($tramos as $t) {
                if ($sueldo <= $t['renta_maxima']) return $t;
            }
            return end($tramos);
        };

        $getTramoManual = function($letra, $tramos) {
            foreach ($tramos as $t) if ($t['tramo'] == $letra) return $t;
            return null;
        };

        // Procesar Registros con nueva lógica
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
                // Recalcular saldo
                $r['saldo'] = ($r['aportes'] + $r['asignacion_familiar_calculada']) - $r['total_descuentos'];
            }
            
            // --- CORRECCIÓN FINAL: Si monto es 0, ocultar letra tramo ---
            if ($r['asignacion_familiar_calculada'] == 0) {
                $r['tramo_letra'] = '';
            }
        }
        unset($r); // romper referencia

        $totales_tabla = ['sueldo_imponible' => 0, 'descuento_afp' => 0, 'descuento_salud' => 0, 'adicional_salud_apv' => 0, 'seguro_cesantia' => 0, 'sindicato' => 0, 'cesantia_licencia_medica' => 0, 'total_descuentos' => 0, 'aportes' => 0, 'asignacion_familiar_calculada' => 0, 'saldo' => 0];
        foreach ($registros as $reg) foreach ($totales_tabla as $key => $value) if (isset($reg[$key])) $totales_tabla[$key] += $reg[$key];

        $stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
        $sis_data = $stmt_sis->fetch();
        $TASA_SIS_ACTUAL = $sis_data ? (float)$sis_data['tasa_sis_decimal'] : 0.0;
        if (!defined('TASA_CAP_IND_CONST')) define('TASA_CAP_IND_CONST', 0.001);
        if (!defined('TASA_EXP_VIDA_CONST')) define('TASA_EXP_VIDA_CONST', 0.009);

        $total_imponible_sis = 0;
        foreach ($registros as $reg) {
            if ($reg['sistema_previsional'] == 'AFP' && $reg['trabajador_estado_previsional'] != 'Pensionado') {
                $total_imponible_sis += $reg['sueldo_imponible'];
            }
        }

        $tasa_mutual_actual = $empleador['tasa_mutual_decimal'];
        $calc_aporte_patronal = floor($totales_tabla['sueldo_imponible'] * $tasa_mutual_actual);

        // CÁLCULO CORREGIDO: Verifica Activo o Pensionado con AFC
        $calc_seguro_cesantia_patronal = 0;
        foreach ($registros as $r) {
            // Determinar si debe pagar cesantía patronal
            $debe_pagar_patronal = false;

            if ($r['trabajador_estado_previsional'] == 'Activo') {
                $debe_pagar_patronal = true;
            } elseif (isset($r['cotiza_cesantia_pensionado']) && $r['cotiza_cesantia_pensionado'] == 1) {
                // Pensionado con AFC (caso especial)
                $debe_pagar_patronal = true;
            }

            // Solo calcular si debe pagar (excluye pensionados estándar)
            if ($debe_pagar_patronal) {
                if ($r['tipo_contrato'] == 'Fijo') {
                    // Plazo Fijo: 3.0% Patronal
                    $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * 0.03);
                } else {
                    // Indefinido: 2.4% Patronal
                    $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * 0.024);
                }
            }
            // Si es pensionado estándar (sin AFC), no paga cesantía patronal (0%)
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

        ob_start();
?>
        <!DOCTYPE html>
        <html lang="es">

        <body>
            <main>
                <div class="info-empleador">
                    <div class="info-col">
                        <p><span class="label">Empleador:</span> <?= htmlspecialchars($empleador['nombre']) ?></p>
                        <p><span class="label">RUT:</span> <?= format_rut($empleador['rut']) ?></p>
                        <p><span class="label">F: Plazo Fijo</p>
                        <p><span class="label">p: Partime</p>
                    </div>
                    <div class="info-col">
                        <p><span class="label">C. Compensación:</span> <?= htmlspecialchars($empleador['caja_compensacion_nombre'] ?: 'N/A') ?></p>
                        <p><span class="label">Mutual:</span> <?= htmlspecialchars($empleador['mutual_seguridad_nombre'] ?: 'ISL') ?></p>
                        <p><span class="label">Tasa Mutual:</span> <?= sprintf("%.2f", $calculos_pie['tasa_mutual_aplicada_porc']) ?>%</p>
                    </div>
                </div>
                <table class="tabla-principal">
                    <thead>
                        <tr>
                            <th>RUT</th>
                            <th>Nombre</th>
                            <th style="width:30px;">Días</th>
                            <th>Sueldo Imp.</th>
                            <th>AFP/INP</th>
                            <th>Salud</th>
                            <th>APV</th>
                            <th>Seg. Cesantía</th>
                            <th>Sindicato</th>
                            <th>Ces. Lic. Méd.</th>
                            <th>Total Desc.</th>
                            <th>Aportes</th>
                            <th>Asig. Familiar</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($registros as $r): ?><tr>
                                <td><?= format_rut($r['trabajador_rut']) ?></td>
                                <td class="nombre-trabajador"><?php if ($r['tipo_contrato'] == 'Fijo'): ?><span class="fixed-marker">F</span><?php endif; ?><?php if (isset($r['es_part_time']) && $r['es_part_time'] == 1): ?><span class="fixed-marker" style="color: #007bff;">P</span><?php endif; ?><?= htmlspecialchars($r['trabajador_nombre']) ?></td>
                                <td style="text-align:center;"><?= $r['dias_trabajados'] ?></td>
                                <td><?= format_numero($r['sueldo_imponible']) ?></td>
                                <td><?php if ($r['trabajador_estado_previsional'] == 'Pensionado') echo '<div style="font-size:7px;font-weight:bold;color:#555;">PENSIONADO</div>';
                                    elseif ($r['sistema_previsional'] == 'INP') echo '<div style="font-size:7px;font-weight:bold;color:#555;">INP</div>';
                                    elseif (!empty($r['afp_nombre'])) echo '<div style="font-size:7px;font-weight:bold;color:#555;">' . mb_strtoupper($r['afp_nombre']) . '</div>';
                                    echo format_numero($r['descuento_afp']); ?></td>
                                <td><?= format_numero($r['descuento_salud']) ?></td>
                                <td><?= format_numero($r['adicional_salud_apv']) ?></td>
                                <td><?= format_numero($r['seguro_cesantia']) ?></td>
                                <td><?= format_numero($r['sindicato']) ?></td>
                                <td><?= format_numero($r['cesantia_licencia_medica']) ?></td>
                                <td class="total"><?= format_numero($r['total_descuentos']) ?></td>
                                <td><?= format_numero($r['aportes']) ?></td>
                                <td>
                                    <?= format_numero($r['asignacion_familiar_calculada']) ?>
                                    <?php if(!empty($r['tramo_letra'])): ?>
                                        <br><span style="font-size:8px; color:#555;">(Tramo <?= $r['tramo_letra'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="total"><?= format_numero($r['saldo']) ?></td>
                            </tr><?php endforeach; ?></tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">Totales</th>
                            <th>-</th>
                            <th><?= format_numero($totales_tabla['sueldo_imponible']) ?></th>
                            <th><?= format_numero($totales_tabla['descuento_afp']) ?></th>
                            <th><?= format_numero($totales_tabla['descuento_salud']) ?></th>
                            <th><?= format_numero($totales_tabla['adicional_salud_apv']) ?></th>
                            <th><?= format_numero($totales_tabla['seguro_cesantia']) ?></th>
                            <th><?= format_numero($totales_tabla['sindicato']) ?></th>
                            <th><?= format_numero($totales_tabla['cesantia_licencia_medica']) ?></th>
                            <th class="total"><?= format_numero($totales_tabla['total_descuentos']) ?></th>
                            <th><?= format_numero($totales_tabla['aportes']) ?></th>
                            <th><?= format_numero($totales_tabla['asignacion_familiar_calculada']) ?></th>
                            <th class="total"><?= format_numero($totales_tabla['saldo']) ?></th>
                        </tr>
                    </tfoot>
                </table>
                <table class="tabla-resumen">
                    <thead>
                        <tr>
                            <th colspan="2">Resumen de Totales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sub Total Descuentos</td>
                            <td class="numero"><?= format_numero($calculos_pie['sub_total_desc_tabla']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Aporte Patronal (<?= sprintf("%.2f", $calculos_pie['tasa_mutual_aplicada_porc']) ?>%)</td>
                            <td class="numero"><?= format_numero($calculos_pie['aporte_patronal']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Seguro Invalidez y Sob (SIS)</td>
                            <td class="numero"><?= format_numero($calculos_pie['sis']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Seguro Cesantía </td>
                            <td class="numero"><?= format_numero($calculos_pie['seguro_cesantia_patronal']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) 0,1 Capitalización Individual (SIS)</td>
                            <td class="numero"><?= format_numero($calculos_pie['capitalizacion_individual']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) 0,9 Expectativa de Vida (SIS)</td>
                            <td class="numero"><?= format_numero($calculos_pie['expectativa_vida']) ?></td>
                        </tr>
                        <tr class="total">
                            <td>Sub-Total Descuentos</td>
                            <td class="numero"><?= format_numero($calculos_pie['sub_total_desc_pie']) ?></td>
                        </tr>
                        <tr>
                            <td>(-) Aportes Conductor</td>
                            <td class="numero"><?= format_numero($calculos_pie['aportes_conductor']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Saldos Positivo(s) Conductor(es)</td>
                            <td class="numero"><?= format_numero($calculos_pie['saldos_positivos']) ?></td>
                        </tr>
                        <tr class="total-final">
                            <td>Total Descuentos</td>
                            <td class="numero"><?= format_numero($calculos_pie['total_descuentos_final']) ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Asignación Familiar</td>
                            <td class="numero"><?= format_numero($calculos_pie['asignacion_familiar']) ?></td>
                        </tr>
                        <tr>
                            <td>Total Sindicato</td>
                            <td class="numero"><?= format_numero($calculos_pie['sindicato']) ?></td>
                        </tr>
                        <tr class="total-final">
                            <td>Total Leyes Sociales</td>
                            <td class="numero"><?= format_numero($calculos_pie['total_leyes_sociales']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </main>
        </body>

        </html>
<?php
        $html_body = ob_get_clean();

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 35, 'margin_bottom' => 20]);
        $header_html = '<div class="info-header"><div class="info-izq"><span class="label">PERÍODO:</span><span class="data">' . $mes_nombre . ' / ' . $ano . '</span></div><div class="info-der"><h1 style="font-size: 16pt; margin:0; padding:0;">Planilla de Cotizaciones Previsionales</h1><span class="label">Emisión:</span> ' . date('d/m/Y - H:i') . '</div></div><div style="border-bottom: 2px solid #000; margin-top: 10px;"></div>';
        $mpdf->SetHTMLHeader($header_html);
        $footer_html = '<table width="100%" style="font-size: 8pt; color: #888;"><tr><td width="50%">Reporte generado por Sistema de Reportes Daiko.</td><td width="50%" style="text-align: right;">Página {PAGENO} de {nbpg}</td></tr></table>';
        $mpdf->SetHTMLFooter($footer_html);

        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html_body, \Mpdf\HTMLParserMode::HTML_BODY);
        $pdfString = $mpdf->Output('', 'S');

        // Determinar la ruta del archivo según la organización
        $meses_nombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $mes_nombre_corto = $meses_nombres[$mes];
        
        // Limpiar nombre del empleador para archivo: eliminar espacios y caracteres especiales
        $nombre_archivo_limpio = preg_replace('/[^a-zA-Z0-9]/', '', $empleador['nombre']); // Solo letras y números
        
        // Limpiar RUT para usar como identificador único (sin puntos ni guiones)
        $rut_limpio = preg_replace('/[^0-9kK]/', '', $empleador['rut']); // Solo números y K
        
        // Nombre del empleador para carpeta (mantener espacios para legibilidad)
        $nombre_empleador_carpeta = trim($empleador['nombre']);
        
        if ($organizacion === 'empleador') {
            // Organizar por empleador: Empleador_Nombre/Mes_Año/archivo.pdf
            // Nombre del archivo: nombreEmpresa_Mes_año.pdf
            $carpeta = "{$nombre_empleador_carpeta}/{$mes_nombre_corto}_{$ano}";
            $nombre_archivo = "{$nombre_archivo_limpio}_{$mes_nombre_corto}_{$ano}.pdf";
            $pdfFileName = "{$carpeta}/{$nombre_archivo}";
        } else {
            // Organizar por mes/año: Mes_Año/archivo.pdf (sin subcarpetas por empleador)
            // Nombre del archivo: nombreEmpresa.pdf (solo nombre, sin espacios, sin guiones, sin _)
            $carpeta = "{$mes_nombre_corto}_{$ano}";
            $nombre_archivo = "{$nombre_archivo_limpio}.pdf";
            $pdfFileName = "{$carpeta}/{$nombre_archivo}";
        }
        
        $zip->addFromString($pdfFileName, $pdfString);
    }

    $zip->close();

    // Nombre del archivo ZIP según organización
    if ($organizacion === 'empleador') {
        $zipBasename = "Reportes_PorEmpleador_" . date('Y-m-d') . ".zip";
    } else {
        $zipBasename = "Reportes_PorMes_" . date('Y-m-d') . ".zip";
    }
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
?>