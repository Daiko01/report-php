<?php
// reportes/ver_reporte_especial.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

// Validaciones: Ya no es estrictamente necesario validar 'empresa' por seguridad, 
// pero lo mantenemos para compatibilidad de lógica.
if (!isset($_GET['mes']) || !isset($_GET['ano']) || !isset($_GET['tipo_reporte'])) {
    die("Faltan parámetros fundamentales.");
}

$mes = (int)$_GET['mes'];
$ano = (int)$_GET['ano'];
$tipo_reporte = $_GET['tipo_reporte'];

// Usamos las constantes globales definidas en bootstrap.php
$nombre_sistema_actual = NOMBRE_SISTEMA;
$id_sistema_actual = ID_EMPRESA_SISTEMA;

// Helpers
date_default_timezone_set('America/Santiago');
$formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'LLLL');
$fecha_obj = DateTime::createFromFormat('!Y-n-d', "$ano-$mes-01");
$mes_nombre = mb_strtoupper($formatter->format($fecha_obj));

function format_money($num)
{
    return number_format($num, 0, ',', '.');
}

// Configuración mPDF
$mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 30]);
$mpdf->SetTitle("Reporte $tipo_reporte - $nombre_sistema_actual");

// ==========================================
// REPORTE A: SINDICATOS
// ==========================================
if ($tipo_reporte == 'sindicatos') {

    // 1. Obtener Sindicatos filtrando por ID_EMPRESA_SISTEMA
    $sql_sind = "SELECT DISTINCT s.id, s.nombre 
                 FROM planillas_mensuales p
                 JOIN trabajadores t ON p.trabajador_id = t.id
                 JOIN sindicatos s ON t.sindicato_id = s.id
                 JOIN empleadores e ON p.empleador_id = e.id
                 WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ? AND s.id IS NOT NULL
                 ORDER BY s.nombre";
    $stmt = $pdo->prepare($sql_sind);
    $stmt->execute([$mes, $ano, $id_sistema_actual]);
    $sindicatos = $stmt->fetchAll();

    if (empty($sindicatos)) die("No hay datos de sindicatos para " . $nombre_sistema_actual . " en este período.");

    foreach ($sindicatos as $index => $sindicato) {
        if ($index > 0) $mpdf->AddPage();

        // Obtener detalles filtrando por ID_EMPRESA_SISTEMA
        $sql_det = "SELECT t.rut, t.nombre as trabajador, p.sindicato as monto, e.nombre as empleador
                    FROM planillas_mensuales p
                    JOIN trabajadores t ON p.trabajador_id = t.id
                    JOIN empleadores e ON p.empleador_id = e.id
                    WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ? AND t.sindicato_id = ?
                    ORDER BY t.nombre";
        $stmt_d = $pdo->prepare($sql_det);
        $stmt_d->execute([$mes, $ano, $id_sistema_actual, $sindicato['id']]);
        $filas = $stmt_d->fetchAll();

        $total_monto = 0;

        $html = '
        <div style="text-align:center; margin-bottom:20px;">
            <h2>Reporte de Sindicatos</h2>
            <p style="font-size:14px; color:#555;">' . htmlspecialchars($nombre_sistema_actual) . '</p>
            <p><strong>Período:</strong> ' . $mes_nombre . ' / ' . $ano . '</p>
            <h3>' . htmlspecialchars($sindicato['nombre']) . '</h3>
        </div>
        <table style="width:100%; border-collapse:collapse; border:1px solid #ccc;">
            <tr style="background:#eee;">
                <th style="border:1px solid #ccc; padding:5px;">RUT</th>
                <th style="border:1px solid #ccc; padding:5px;">Nombre Trabajador</th>
                <th style="border:1px solid #ccc; padding:5px;">Empleador</th>
                <th style="border:1px solid #ccc; padding:5px;">Valor Cuota</th>
            </tr>';

        foreach ($filas as $f) {
            $total_monto += $f['monto'];
            $html .= '
            <tr>
                <td style="border:1px solid #ccc; padding:5px;">' . $f['rut'] . '</td>
                <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($f['trabajador']) . '</td>
                <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($f['empleador']) . '</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . format_money($f['monto']) . '</td>
            </tr>';
        }

        $html .= '
            <tr style="background:#eee; font-weight:bold;">
                <td colspan="3" style="border:1px solid #ccc; padding:5px; text-align:right;">Total (' . count($filas) . ' trabajadores):</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . format_money($total_monto) . '</td>
            </tr>
        </table>';

        $mpdf->WriteHTML($html);
    }
}

// ==========================================
// REPORTE B: EXCEDENTES (SALDO POSITIVO)
// ==========================================
elseif ($tipo_reporte == 'excedentes') {

    // Filtrado estricto por ID_EMPRESA_SISTEMA
    $sql = "SELECT e.nombre as empleador, t.nombre as conductor, 
            ((p.aportes + p.asignacion_familiar_calculada) - (p.descuento_afp + p.descuento_salud + p.adicional_salud_apv + p.seguro_cesantia + p.sindicato + p.cesantia_licencia_medica)) as saldo
            FROM planillas_mensuales p
            JOIN trabajadores t ON p.trabajador_id = t.id
            JOIN empleadores e ON p.empleador_id = e.id
            WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ?
            HAVING saldo > 0
            ORDER BY e.nombre, t.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $ano, $id_sistema_actual]);
    $filas = $stmt->fetchAll();

    if (empty($filas)) die("No hay excedentes para " . $nombre_sistema_actual . " en este período.");

    $html = '
    <div style="text-align:center; margin-bottom:20px;">
        <h2>Reporte de Excedentes de Conductor</h2>
        <h3 style="color:#28a745;">' . htmlspecialchars($nombre_sistema_actual) . '</h3>
        <p><strong>Período:</strong> ' . $mes_nombre . ' / ' . $ano . '</p>
    </div>
    <table style="width:100%; border-collapse:collapse; border:1px solid #ccc;">
        <tr style="background:#eee;">
            <th style="border:1px solid #ccc; padding:5px;">Nombre Empleador</th>
            <th style="border:1px solid #ccc; padding:5px;">Nombre Conductor</th>
            <th style="border:1px solid #ccc; padding:5px;">Monto Excedente</th>
            <th style="border:1px solid #ccc; padding:5px; width:150px;">Firma</th>
        </tr>';

    foreach ($filas as $f) {
        $html .= '
        <tr>
            <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($f['empleador']) . '</td>
            <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($f['conductor']) . '</td>
            <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . format_money($f['saldo']) . '</td>
            <td style="border:1px solid #ccc; padding:5px; vertical-align:bottom;"></td>
        </tr>';
    }
    $html .= '</table>';

    $mpdf->WriteHTML($html);
}

// ==========================================
// REPORTE C: ASIGNACIÓN FAMILIAR
// ==========================================
elseif ($tipo_reporte == 'asignacion') {

    // Filtrado estricto por ID_EMPRESA_SISTEMA
    $sql = "SELECT e.nombre as empleador, t.nombre as conductor, 
                    p.asignacion_familiar_calculada as monto,
                    p.sueldo_imponible, t.numero_cargas, t.tramo_asignacion_manual
            FROM planillas_mensuales p
            JOIN trabajadores t ON p.trabajador_id = t.id
            JOIN empleadores e ON p.empleador_id = e.id
            WHERE p.mes = ? AND p.ano = ? AND e.empresa_sistema_id = ?
            ORDER BY e.nombre, t.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $ano, $id_sistema_actual]);
    $filas_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas_raw)) die("No hay asignaciones familiares para " . $nombre_sistema_actual);

    // [Mantenemos tu lógica de tramos e iteración intacta...]
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

    $filas_finales = [];
    $total_asig = 0;

    foreach ($filas_raw as $f) {
        $tramo_data = null;
        $origen_tramo = 'auto';
        $tramo_auto = $getTramoAutomatico($f['sueldo_imponible'], $tramos_del_periodo);

        if (!empty($f['tramo_asignacion_manual'])) {
            $tramo_manual = $getTramoManual($f['tramo_asignacion_manual'], $tramos_del_periodo);
            if ($tramo_manual) {
                $tramo_data = $tramo_manual;
                $origen_tramo = 'manual';
            } else {
                $tramo_data = $tramo_auto;
            }
        } else {
            $tramo_data = $tramo_auto;
        }

        $f['tramo_letra'] = $tramo_data['tramo'] ?? '';
        if ($origen_tramo == 'manual') {
            $f['monto'] = (int)$tramo_data['monto_por_carga'] * (int)$f['numero_cargas'];
        }

        if ($f['monto'] > 0) {
            $filas_finales[] = $f;
            $total_asig += $f['monto'];
        }
    }

    $html = '
    <div style="text-align:center; margin-bottom:20px;">
        <h2>Reporte de Asignación Familiar</h2>
        <h3>' . htmlspecialchars($nombre_sistema_actual) . '</h3>
        <p><strong>Período:</strong> ' . $mes_nombre . ' / ' . $ano . '</p>
    </div>
    <table style="width:100%; border-collapse:collapse; border:1px solid #ccc;">
        <tr style="background:#eee;">
            <th style="border:1px solid #ccc; padding:5px;">Nombre Empleador</th>
            <th style="border:1px solid #ccc; padding:5px;">Nombre Conductor</th>
            <th style="border:1px solid #ccc; padding:5px;">Monto Asignación</th>
            <th style="border:1px solid #ccc; padding:5px; width:150px;">Firma Conductor</th>
        </tr>';

    foreach ($filas_finales as $f) {
        $tramo_html = !empty($f['tramo_letra']) ? '<br><span style="font-size:10px; color:#555;">(Tramo ' . $f['tramo_letra'] . ')</span>' : '';
        $html .= '
        <tr>
            <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($f['empleador']) . '</td>
            <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($f['conductor']) . '</td>
            <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . format_money($f['monto']) . $tramo_html . '</td>
            <td style="border:1px solid #ccc; padding:5px; vertical-align:bottom;"></td>
        </tr>';
    }

    $html .= '
        <tr style="background:#eee; font-weight:bold;">
            <td colspan="2" style="border:1px solid #ccc; padding:5px; text-align:right;">Total Asignaciones:</td>
            <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . format_money($total_asig) . '</td>
            <td style="border:1px solid #ccc;"></td>
        </tr>
    </table>';

    $mpdf->WriteHTML($html);
}

$mpdf->Output('Reporte_Especial_' . str_replace(' ', '_', $nombre_sistema_actual) . '.pdf', 'I');
