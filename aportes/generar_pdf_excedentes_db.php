<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

$mes = (int)$_GET['mes'];
$ano = (int)$_GET['ano'];
$empresa_filtro = $_GET['empresa'] ?? '';

// --- LIVE QUERY (Same as ver_excedentes.php) ---
$sql = "SELECT 
            p.bus_id, 
            p.conductor_id, 
            SUM(p.gasto_imposiciones) as monto,
            b.numero_maquina as nro_maquina,
            t.rut as rut_conductor,
            t.nombre as nombre_conductor
        FROM produccion_buses p
        JOIN buses b ON p.bus_id = b.id
        JOIN trabajadores t ON p.conductor_id = t.id
        WHERE MONTH(p.fecha) = :mes 
          AND YEAR(p.fecha) = :ano
          AND t.es_excedente = 1
          AND p.gasto_imposiciones > 0
        GROUP BY p.bus_id, p.conductor_id
        ORDER BY b.numero_maquina, t.nombre";

$params = [':mes' => $mes, ':ano' => $ano];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$excedentes = $stmt->fetchAll();

if (empty($excedentes)) die("No hay datos para generar el reporte de excedentes (En Vivo).");

// Generar PDF
try {
    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
    $mes_nombre = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

    $html = '<h2 style="text-align:center;">Reporte de Excedentes de Aportes</h2>';
    $html .= '<p style="text-align:center;">Período: ' . $mes_nombre[$mes] . ' / ' . $ano . '</p>';
    if ($empresa_filtro) $html .= '<h3 style="text-align:center;">Empresa: ' . htmlspecialchars($empresa_filtro) . '</h3>';

    $html .= '<table style="width:100%; border-collapse:collapse; font-size:10pt;">
                <thead>
                    <tr style="background-color:#eee;">
                        <th style="border:1px solid #ccc; padding:5px;">N° Bus</th>
                        <th style="border:1px solid #ccc; padding:5px;">RUT</th>
                        <th style="border:1px solid #ccc; padding:5px;">Nombre</th>
                        <th style="border:1px solid #ccc; padding:5px;">Monto Acumulado</th>
                        <th style="border:1px solid #ccc; padding:5px;">Firma</th>
                    </tr>
                </thead><tbody>';

    $grandTotal = 0;

    foreach ($excedentes as $ex) {
        $grandTotal += (int)$ex['monto'];
        $html .= '<tr>
                    <td style="border:1px solid #ccc; padding:5px; text-align:center;">' . htmlspecialchars($ex['nro_maquina']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($ex['rut_conductor']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($ex['nombre_conductor']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . number_format($ex['monto'], 0, ',', '.') . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">________</td>
                  </tr>';
    }

    // Grand Total Row
    $html .= '<tr style="background-color:#eee; font-weight:bold;">
                <td colspan="3" style="border:1px solid #ccc; padding:5px; text-align:right;">TOTAL GENERAL</td>
                <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . number_format($grandTotal, 0, ',', '.') . '</td>
                <td style="border:1px solid #ccc; padding:5px;"></td>
              </tr>';

    $html .= '</tbody></table>';

    $mpdf->WriteHTML($html);
    $mpdf->Output('Excedentes_Vivo.pdf', 'I');
} catch (Exception $e) {
    echo "Error PDF: " . $e->getMessage();
}
