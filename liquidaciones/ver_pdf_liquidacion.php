<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/LiquidacionPdfGenerator.php';
use Mpdf\Mpdf;

if (!isset($_GET['id'])) die("Falta ID");

$data = LiquidacionPdfGenerator::generarHtml($_GET['id'], $pdo);

if (!$data) die("Liquidación no encontrada");

$mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
$mpdf->WriteHTML($data['html']);
$mpdf->Output('Liquidacion.pdf', 'I');
?>