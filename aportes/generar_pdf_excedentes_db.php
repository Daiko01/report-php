<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

$mes = (int)$_GET['mes'];
$ano = (int)$_GET['ano'];
$empresa_filtro = $_GET['empresa'] ?? '';

// Consulta
$sql = "SELECT * FROM excedentes_aportes WHERE mes = ? AND ano = ?";
$params = [$mes, $ano];
if ($empresa_filtro) {
    $sql .= " AND empresa_detectada = ?";
    $params[] = $empresa_filtro;
}
// Filtrar Conflictos en reporte histórico
$sql .= " AND (motivo NOT LIKE 'CONFLICTO%' OR motivo IS NULL)";
$sql .= " ORDER BY empresa_detectada, nombre_conductor";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$excedentes = $stmt->fetchAll();

if (empty($excedentes)) die("No hay datos para generar.");

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
                        <th style="border:1px solid #ccc; padding:5px;">Empresa</th>
                        <th style="border:1px solid #ccc; padding:5px;">Máquina</th>
                        <th style="border:1px solid #ccc; padding:5px;">RUT</th>
                        <th style="border:1px solid #ccc; padding:5px;">Nombre</th>
                        <th style="border:1px solid #ccc; padding:5px;">Monto</th>
                        <th style="border:1px solid #ccc; padding:5px;">Firma</th>
                    </tr>
                </thead><tbody>';

    foreach ($excedentes as $ex) {
        $html .= '<tr>
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($ex['empresa_detectada']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($ex['nro_maquina']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($ex['rut_conductor']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($ex['nombre_conductor']) . '</td>
                    <td style="border:1px solid #ccc; padding:5px; text-align:right;">$' . number_format($ex['monto'], 0, ',', '.') . '</td>
                    <td style="border:1px solid #ccc; padding:5px;">________</td>
                  </tr>';
    }
    $html .= '</tbody></table>';

    $mpdf->WriteHTML($html);
    $mpdf->Output('Excedentes.pdf', 'I');
} catch (Exception $e) {
    echo "Error PDF: " . $e->getMessage();
}
