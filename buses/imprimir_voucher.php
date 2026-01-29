<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
// removed vendor autoload requirement for mPDF as it's no longer needed for output

if (!isset($_GET['id'])) {
    die("ID no especificado");
}

$guia_id = (int)$_GET['id'];

try {
    // 1. Datos
    $stmt = $pdo->prepare("
        SELECT p.*, b.numero_maquina, b.patente, e.nombre as empleador, IFNULL(t.nombre, 'Sin Conductor') as conductor, t.rut as conductor_rut 
        FROM produccion_buses p
        JOIN buses b ON p.bus_id = b.id
        JOIN empleadores e ON b.empleador_id = e.id
        LEFT JOIN trabajadores t ON p.conductor_id = t.id
        WHERE p.id = ?
    ");
    $stmt->execute([$guia_id]);
    $guia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guia) die("Guía no encontrada");

    $stmtDet = $pdo->prepare("SELECT * FROM produccion_detalle_boletos WHERE guia_id = ? ORDER BY tarifa ASC");
    $stmtDet->execute([$guia_id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    // Cálculos
    $total_a_pagar = $guia['gasto_administracion'] + $guia['gasto_imposiciones'] + $guia['gasto_boletos'];
    $fecha = date('d/m/Y', strtotime($guia['fecha']));
    $hora_actual = date('H:i');
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher #<?php echo $guia['nro_guia']; ?></title>
    <style>
        /* Reset & Base */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Roboto", sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
        }

        /* Screen Preview Style (Centered 80mm strip) */
        @media screen {
            body {
                background-color: #f0f0f0;
                display: flex;
                justify-content: center;
                padding-top: 20px;
            }

            .ticket-body {
                width: 80mm;
                background-color: #fff;
                padding: 5px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
        }

        /* Print Style */
        @media print {
            body {
                background: none;
            }

            .ticket-body {
                width: 100%;
                /* Printer driver handles 80mm paper matches */
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }

        /* Typography & Layout */
        .ticket-body {
            font-size: 11pt;
            line-height: 1.2;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .small {
            font-size: 9pt;
        }

        .mt-2 {
            margin-top: 2mm;
        }

        .w-50 {
            width: 50%;
            float: left;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Components */
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 2mm;
        }

        .title-simple {
            font-weight: bold;
            font-size: 11pt;
            text-align: center;
            padding: 2mm;
            text-transform: uppercase;
            margin-bottom: 3mm;
            border-bottom: 1px dashed #000;
            border-top: 1px dashed #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            vertical-align: top;
        }

        /* Info Grid */
        .info-table td {
            padding: 1mm 0;
            font-size: 10pt;
        }

        .separator {
            border-top: 2px solid #000;
            margin: 3mm 0;
        }

        /* Finance Table */
        .finance-table td {
            padding: 1.5mm 0;
        }

        .finance-item-value {
            text-align: right;
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 3mm 0;
            font-size: 15pt;
            font-weight: bold;
        }

        /* Folios */
        .folio-container {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 1mm 0;
            margin-top: 3mm;
        }

        .folio-title {
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }

        .folio-table th {
            padding: 2px 0;
            text-align: left;
            border-bottom: 1px solid #000;
            font-size: 9pt;
        }

        .folio-table td {
            padding: 2px 0;
            border-bottom: 1px dotted #ccc;
            text-align: left;
            font-size: 9pt;
        }

        .folio-table th.series-col {
            text-align: center;
        }

        .folio-table td.series-col {
            text-align: center;
            font-family: "Courier New", monospace;
            font-weight: bold;
            font-size: 10pt;
        }

        .footer {
            text-align: center;
            margin-top: 5mm;
            font-size: 8pt;
        }

        .cut-space {
            height: 15mm;
        }
    </style>
</head>

<body>

    <div class="ticket-body">
        <div class="company-name"><?php echo NOMBRE_SISTEMA; ?></div>

        <div class="title-simple">Comprobante de Pago</div>

        <table class="info-table">
            <tr>
                <td class="bold">N° GUÍA: #<?php echo $guia['nro_guia']; ?></td>
                <td class="text-right">FECHA: <?php echo $fecha; ?></td>
            </tr>
            <tr>
                <td class="bold">BUS: <?php echo $guia['numero_maquina']; ?></td>
                <td class="text-right">Patente: <?php echo strtoupper($guia['patente']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="mt-2">
                    <span class="small">CONDUCTOR:</span><br>
                    <span class="bold"><?php echo strtoupper(mb_strimwidth($guia['conductor'], 0, 35, '...')); ?></span> <br>
                    <span class="bold">RUT: <?php echo strtoupper($guia['conductor_rut']); ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="mt-2">
                    <span class="small">PROPIETARIO:</span><br>
                    <span class="bold"><?php echo strtoupper(mb_strimwidth($guia['empleador'], 0, 35, '...')); ?></span>
                </td>
            </tr>
        </table>

        <div class="separator"></div>

        <table class="finance-table">
            <tr>
                <td>Control Operativo Diario</td>
                <td class="finance-item-value">$ <?php echo number_format($guia['gasto_administracion'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td>Imposiciones Conductor</td>
                <td class="finance-item-value">$ <?php echo number_format($guia['gasto_imposiciones'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td>Compra De Boletos</td>
                <td class="finance-item-value">$ <?php echo number_format($guia['gasto_boletos'], 0, ',', '.'); ?></td>
            </tr>
            <tr class="total-row">
                <td>TOTAL PAGAR</td>
                <td class="finance-item-value">$ <?php echo number_format($total_a_pagar, 0, ',', '.'); ?></td>
            </tr>
        </table>

        <div class="folio-container">
            <div class="folio-title">Detalle Tarifario y Serie de Boletos</div>
            <table class="folio-table">
                <thead>
                    <tr>
                        <th width="40%">Tarifa</th>
                        <th width="60%" class="series-col">Últ. Serie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $d): ?>
                        <?php if ($d['folio_fin'] > 0): ?>
                            <tr>
                                <td class="bold">$ <?php echo $d['tarifa']; ?></td>
                                <td class="series-col"><?php echo $d['folio_fin']; ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            Sistema TransReport <br>
            <?php echo $hora_actual; ?>
        </div>

        <div class="cut-space">.</div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            // Optional: auto-close after print dialog logic requires complex monitoring.
            // Usually simpler to let user close or close on focus regain.
            window.onafterprint = function() {
                // window.close(); 
            };
        }
    </script>

</body>

</html>