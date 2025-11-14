<?php
// Reporte de Cotizaciones - ver_pdf.php (AFP Nombres + PartTime + Lógica Corregida)
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

// Formato de Fecha
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

    // B. Registros de Planilla (SQL Actualizado con AFP Nombre y Part-Time)
    $sql_p = "SELECT 
                t.rut as trabajador_rut, 
                t.nombre as trabajador_nombre, 
                t.estado_previsional as trabajador_estado_previsional, 
                a.nombre as afp_nombre, -- <-- TRAEMOS EL NOMBRE DE LA AFP
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
                (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as total_descuentos,
                (p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica) as saldo,
                
                -- Subconsulta para Part-Time
                (SELECT es_part_time FROM contratos c 
                 WHERE c.trabajador_id = t.id 
                 AND c.empleador_id = p.empleador_id
                 AND c.fecha_inicio <= LAST_DAY(CONCAT(p.ano, '-', p.mes, '-01'))
                 ORDER BY c.fecha_inicio DESC LIMIT 1) as es_part_time

              FROM planillas_mensuales p
              JOIN trabajadores t ON p.trabajador_id = t.id
              LEFT JOIN afps a ON t.afp_id = a.id -- <-- JOIN PARA NOMBRE AFP
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
// 5. FUNCIONES HELPER
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

// A. Cálculo de totales_tabla
$totales_tabla = ['sueldo_imponible' => 0, 'descuento_afp' => 0, 'descuento_salud' => 0, 'adicional_salud_apv' => 0, 'seguro_cesantia' => 0, 'sindicato' => 0, 'cesantia_licencia_medica' => 0, 'total_descuentos' => 0, 'aportes' => 0, 'asignacion_familiar_calculada' => 0, 'saldo' => 0];
foreach ($registros as $r) {
    foreach ($totales_tabla as $key => $value) {
        if (isset($r[$key])) $totales_tabla[$key] += $r[$key];
    }
}

// B. Cálculo de calculos_pie
$stmt_sis = $pdo->prepare("SELECT tasa_sis_decimal FROM sis_historico WHERE (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes)) ORDER BY ano_inicio DESC, mes_inicio DESC LIMIT 1");
$stmt_sis->execute(['ano' => $ano, 'mes' => $mes]);
$sis_data = $stmt_sis->fetch();
$TASA_SIS_ACTUAL = $sis_data ? (float)$sis_data['tasa_sis_decimal'] : 0.0;

// Constantes
define('TASA_CAP_IND_CONST', 0.001);
define('TASA_EXP_VIDA_CONST', 0.009);

$total_imponible_sis = 0;
foreach ($registros as $r) if ($r['trabajador_estado_previsional'] != 'Pensionado') $total_imponible_sis += $r['sueldo_imponible'];

$tasa_mutual_actual = $empleador['tasa_mutual_decimal'];
$calc_aporte_patronal = floor($totales_tabla['sueldo_imponible'] * $tasa_mutual_actual);

// --- CÁLCULO SEGURO CESANTÍA PATRONAL (Lógica Corregida) ---
$calc_seguro_cesantia_patronal = 0;
foreach ($registros as $r) {
    if ($r['tipo_contrato'] == 'Fijo') {
        // Fijo: 3.0%
        $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * 0.03);
    } else {
        // Indefinido
        if ($r['trabajador_estado_previsional'] == 'Activo') {
            // Activo: 2.4%
            $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * 0.024);
        } elseif (isset($r['cotiza_cesantia_pensionado']) && $r['cotiza_cesantia_pensionado'] == 1) {
            // Pensionado con Toggle ON: 2.4%
            $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * 0.024);
        }
        // Pensionado Toggle OFF: 0%
    }
}
// --- FIN CÁLCULO ---

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
// 7. GENERAR PDF
// ===================================================================
try {
    $css = file_get_contents(__DIR__ . '/style.css');

    // INICIO DEL TEMPLATE
    ob_start();
    // NOTA: Incluimos el contenido aquí directamente o vía include, 
    // pero modificaré la parte de la tabla para mostrar el Nombre de la AFP.
?>

    <!DOCTYPE html>
    <html lang="es">

    <body>
        <main>
            <div class="info-empleador">
                <div class="info-col">
                    <p><span class="label">Empleador:</span> <?= htmlspecialchars($empleador['nombre']) ?></p>
                    <p><span class="label">RUT:</span> <?= format_rut($empleador['rut']) ?></p>
                    <p><span class="label">Sucursal:</span> <?= htmlspecialchars($empleador['sucursal'] ?: 'N/A') ?></p>
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
                        <th style="width: 30px;">Días</th>
                        <th>Sueldo Imp.</th>
                        <th>AFP</th>
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
                <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr>
                            <td><?= format_rut($r['trabajador_rut']) ?></td>
                            <td class="nombre-trabajador">
                                <?php if ($r['tipo_contrato'] == 'Fijo'): ?><span class="fixed-marker">F</span><?php endif; ?>
                                <?php if (isset($r['es_part_time']) && $r['es_part_time'] == 1): ?><span class="fixed-marker" style="color: #007bff;">P</span><?php endif; ?>
                                <?= htmlspecialchars($r['trabajador_nombre']) ?>
                            </td>
                            <td style="text-align: center;"><?= $r['dias_trabajados'] ?></td>
                            <td><?= format_numero($r['sueldo_imponible']) ?></td>

                            <td>
                                <?php
                                if ($r['trabajador_estado_previsional'] == 'Pensionado') {
                                    echo '<div style="font-size: 7px; font-weight: bold; color: #555;">PENSIONADO</div>';
                                } elseif ($r['afp_nombre']) {
                                    echo '<div style="font-size: 7px; font-weight: bold; color: #555;">' . mb_strtoupper($r['afp_nombre']) . '</div>';
                                }
                                echo format_numero($r['descuento_afp']);
                                ?>
                            </td>

                            <td><?= format_numero($r['descuento_salud']) ?></td>
                            <td><?= format_numero($r['adicional_salud_apv']) ?></td>
                            <td><?= format_numero($r['seguro_cesantia']) ?></td>
                            <td><?= format_numero($r['sindicato']) ?></td>
                            <td><?= format_numero($r['cesantia_licencia_medica']) ?></td>
                            <td class="total"><?= format_numero($r['total_descuentos']) ?></td>
                            <td><?= format_numero($r['aportes']) ?></td>
                            <td><?= format_numero($r['asignacion_familiar_calculada']) ?></td>
                            <td class="total"><?= format_numero($r['saldo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
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
                        <td>Sub-Total Descuentos (Tabla)</td>
                        <td class="numero"><?= format_numero($calculos_pie['sub_total_desc_tabla']) ?></td>
                    </tr>
                    <tr>
                        <td>(+) Aporte Patronal (<?= sprintf("%.2f", $calculos_pie['tasa_mutual_aplicada_porc']) ?>%)</td>
                        <td class="numero"><?= format_numero($calculos_pie['aporte_patronal']) ?></td>
                    </tr>
                    <tr>
                        <td>(+) SIS (Tasa Histórica)</td>
                        <td class="numero"><?= format_numero($calculos_pie['sis']) ?></td>
                    </tr>
                    <tr>
                        <td>(+) Seguro Cesantía Patronal</td>
                        <td class="numero"><?= format_numero($calculos_pie['seguro_cesantia_patronal']) ?></td>
                    </tr>
                    <tr>
                        <td>(+) Capitalización Individual (SIS)</td>
                        <td class="numero"><?= format_numero($calculos_pie['capitalizacion_individual']) ?></td>
                    </tr>
                    <tr>
                        <td>(+) Expectativa de Vida (SIS)</td>
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
                        <td>(+) Saldos Positivos</td>
                        <td class="numero"><?= format_numero($calculos_pie['saldos_positivos']) ?></td>
                    </tr>
                    <tr class="total-final">
                        <td>Total Descuentos</td>
                        <td class="numero"><?= format_numero($calculos_pie['total_descuentos_final']) ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total Asignación Familiar</td>
                        <td class="numero"><?= format_numero($calculos_pie['asignacion_familiar']) ?></td>
                    </tr>
                    <tr>
                        <td>Total Sindicato</td>
                        <td class="numero"><?= format_numero($calculos_pie['sindicato']) ?></td>
                    </tr>
                    <tr class="total-final">
                        <td>Total Leyes Sociales (Empleado + Empleador)</td>
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
    $mpdf->SetTitle("Planilla $mes-$ano - " . $empleador['nombre']);
    $mpdf->SetAuthor("Sistema de Reportes Daiko");

    $header_html = '<div class="info-header"><div class="info-izq"><span class="label">PERÍODO:</span><span class="data">' . $mes_nombre . ' / ' . $ano . '</span></div><div class="info-der"><h1 style="font-size: 16pt; margin:0; padding:0;">Planilla de Cotizaciones Previsionales</h1><span class="label">Emisión:</span> ' . date('d/m/Y - H:i') . '</div></div><div style="border-bottom: 2px solid #000; margin-top: 10px;"></div>';
    $mpdf->SetHTMLHeader($header_html);

    $footer_html = '<table width="100%" style="font-size: 8pt; color: #888;"><tr><td width="50%">Reporte generado por Sistema de Reportes Daiko.</td><td width="50%" style="text-align: right;">Página {PAGENO} de {nbpg}</td></tr></table>';
    $mpdf->SetHTMLFooter($footer_html);

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html_body, \Mpdf\HTMLParserMode::HTML_BODY);
    $mpdf->Output('Reporte.pdf', 'I');
    exit;
} catch (\Exception $e) {
    echo "<h1>Error al generar el PDF</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>