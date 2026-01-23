<?php
// buses/exportar_buses.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1. Obtener datos (Misma lógica de seguridad que gestionar_buses.php)
$sql = "SELECT b.numero_maquina, b.patente, 
               COALESCE(e.nombre, 'Sin Asignar') as dueno, 
               u.numero as unidad, 
               COALESCE(t.nombre, '---') as terminal
        FROM buses b
        JOIN terminales t ON b.terminal_id = t.id
        JOIN unidades u ON t.unidad_id = u.id
        LEFT JOIN empleadores e ON b.empleador_id = e.id
        WHERE u.empresa_asociada_id = :id_sistema
        ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_sistema' => ID_EMPRESA_SISTEMA]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Crear Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Directorio Flota');

// 3. Encabezados
$headers = ['Nº Máquina', 'Patente', 'Dueño (Empleador)', 'Unidad', 'Terminal'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// 4. Estilos Encabezado (Azul Oscuro, Blanco, Negrita)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2E59D9'], // #2e59d9
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];

$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(25);

// 5. Llenar datos
$row = 2;
foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $item['numero_maquina']);
    $sheet->setCellValue('B' . $row, $item['patente']);
    $sheet->setCellValue('C' . $row, $item['dueno']);
    $sheet->setCellValue('D' . $row, $item['unidad']);
    $sheet->setCellValue('E' . $row, $item['terminal']);

    // Alineación centrada para columnas cortas
    $sheet->getStyle('A' . $row . ':B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row++;
}

// 6. Auto-size columnas
foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// 7. Salida
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Flota_Buses.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
