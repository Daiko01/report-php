<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

use Mpdf\Mpdf;

if (!isset($_SESSION['reporte_excedentes'])) {
    die("No hay datos de excedentes para generar.");
}

$data = $_SESSION['reporte_excedentes'];
$empresa = $data['empresa'];
$lista = $data['data'];
$mes_nombre = date('F', mktime(0, 0, 0, $data['mes'], 1)); // Puedes usar IntlDateFormatter aquí también

try {
    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);

    $html = '
    <div style="text-align:center; margin-bottom:20px;">
        <h2>Reporte de Excedentes (Trabajadores No Registrados)</h2>
        <h3>' . htmlspecialchars($empresa) . '</h3>
        <p>Período: ' . $data['mes'] . '/' . $data['ano'] . '</p>
    </div>
    <table style="width:100%; border-collapse:collapse; border:1px solid #ccc; font-family:Arial; font-size:10pt;">
        <tr style="background:#eee;">
            <th style="border:1px solid #ccc; padding:8px;">RUT</th>
            <th style="border:1px solid #ccc; padding:8px;">Nombre</th>
            <th style="border:1px solid #ccc; padding:8px;">Monto Aporte</th>
            <th style="border:1px solid #ccc; padding:8px; width:150px;">Firma</th>
        </tr>';

    $total = 0;
    foreach ($lista as $item) {
        // Excluir conflictos (cruce de choferes entre empresas del mismo holding)
        if (isset($item['motivo']) && strpos($item['motivo'], 'CONFLICTO') !== false) {
            continue;
        }

        $total += $item['monto'];
        $html .= '
        <tr>
            <td style="border:1px solid #ccc; padding:8px;">' . htmlspecialchars($item['rut']) . '</td>
            <td style="border:1px solid #ccc; padding:8px;">' . htmlspecialchars($item['nombre']) . '</td>
            <td style="border:1px solid #ccc; padding:8px; text-align:right;">$' . number_format($item['monto'], 0, ',', '.') . '</td>
            <td style="border:1px solid #ccc; padding:8px; vertical-align:bottom;">_______</td>
        </tr>';
    }

    $html .= '
        <tr style="background:#eee; font-weight:bold;">
            <td colspan="2" style="border:1px solid #ccc; padding:8px; text-align:right;">Total:</td>
            <td style="border:1px solid #ccc; padding:8px; text-align:right;">$' . number_format($total, 0, ',', '.') . '</td>
            <td></td>
        </tr>
    </table>';

    $mpdf->WriteHTML($html);
    $mpdf->Output('Excedentes.pdf', 'I');

    // Limpiar sesión después de generar (opcional)
    // unset($_SESSION['reporte_excedentes']);

} catch (Exception $e) {
    echo "Error PDF: " . $e->getMessage();
}
