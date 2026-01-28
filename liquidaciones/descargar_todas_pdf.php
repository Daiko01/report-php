<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/LiquidacionPdfGenerator.php';

use Mpdf\Mpdf;

// 1. Obtener Filtros
$mes = $_GET['mes'] ?? date('n');
$ano = $_GET['ano'] ?? date('Y');
$empleador_id = $_GET['empleador'] ?? '';

// 2. Consultar IDs
$sql = "SELECT l.id 
        FROM liquidaciones l
        JOIN empleadores e ON l.empleador_id = e.id
        JOIN trabajadores t ON l.trabajador_id = t.id
        WHERE l.mes = :mes AND l.ano = :ano";
$params = [':mes' => $mes, ':ano' => $ano];

if ($empleador_id) {
    $sql .= " AND l.empleador_id = :eid";
    $params[':eid'] = $empleador_id;
}
$sql .= " ORDER BY e.nombre, t.nombre";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN); // Obtiene solo array de IDs
} catch (Exception $e) {
    die("Error al consultar datos: " . $e->getMessage());
}

if (empty($rows)) {
    die("No se encontraron liquidaciones con los filtros seleccionados.");
}

// 3. Generar PDF consolidado
try {
    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
    $count = 0;
    $total = count($rows);

    foreach ($rows as $id) {
        $data = LiquidacionPdfGenerator::generarHtml($id, $pdo);
        if ($data) {
            $mpdf->WriteHTML($data['html']);

            // Agregar nueva página si no es el último
            $count++;
            if ($count < $total) {
                $mpdf->AddPage();
            }
        }
    }

    // Nombre del archivo
    $nombre_archivo = "Liquidaciones_{$mes}_{$ano}";
    if ($empleador_id) {
        $nombre_archivo .= "_Emp{$empleador_id}";
    }
    $nombre_archivo .= ".pdf";

    $mpdf->Output($nombre_archivo, 'I'); // I = Inline (ver en navegador)

} catch (\Mpdf\MpdfException $e) {
    echo "Error al generar PDF: " . $e->getMessage();
}
