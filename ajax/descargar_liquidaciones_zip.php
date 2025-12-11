<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/LiquidacionPdfGenerator.php';
use Mpdf\Mpdf;

// Limpia nombres de archivo para evitar errores en el ZIP
function limpiar_nombre($string) {
    return preg_replace('/[^A-Za-z0-9 _-]/', '', $string);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['ids'])) {
    http_response_code(400); exit;
}

try {
    $zip = new ZipArchive();
    $zipFileName = tempnam(sys_get_temp_dir(), 'liq_') . '.zip';
    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Error creando ZIP');
    }

    // Mes para el nombre del archivo final
    $nombre_zip_mes = "Liquidaciones"; 

    foreach ($data['ids'] as $id) {
        // 1. Generar datos y HTML
        $res = LiquidacionPdfGenerator::generarHtml($id, $pdo);
        if (!$res) continue;

        // 2. Convertir a PDF binario
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->WriteHTML($res['html']);
        $pdfContent = $mpdf->Output('', 'S');

        // 3. Definir estructura de carpetas
        // Estructura: MES-AÑO / NOMBRE_EMPLEADOR / NOMBRE_TRABAJADOR.pdf
        
        $mes_num = str_pad($res['mes'], 2, '0', STR_PAD_LEFT);
        $anio = $res['ano'];
        $carpeta_fecha = "$mes_num-$anio";
        
        $nombre_empleador = limpiar_nombre($res['empleador']);
        $nombre_trabajador = limpiar_nombre($res['trabajador']);
        
        $ruta_interna = "$carpeta_fecha/$nombre_empleador/$nombre_trabajador.pdf";
        
        $zip->addFromString($ruta_interna, $pdfContent);
        
        $nombre_zip_mes = "Liquidaciones_$carpeta_fecha";
    }

    $zip->close();

    if (ob_get_level() > 0) ob_end_clean();
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nombre_zip_mes . '.zip"');
    header('Content-Length: ' . filesize($zipFileName));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($zipFileName);
    unlink($zipFileName);
    exit;

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>