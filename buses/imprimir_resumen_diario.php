<?php
// buses/imprimir_resumen_diario.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Filtros
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$usuario_generador = $_SESSION['nombre'] ?? $_SESSION['user_name'] ?? $_SESSION['usuario'] ?? 'Usuario Sistema';

// Query
$sql = "SELECT 
            p.nro_guia,
            p.ingreso,
            p.gasto_boletos,
            p.gasto_administracion,
            p.gasto_imposiciones,
            b.numero_maquina,
            t.nombre as conductor_nombre
        FROM produccion_buses p
        JOIN buses b ON p.bus_id = b.id
        JOIN empleadores e ON b.empleador_id = e.id
        LEFT JOIN trabajadores t ON p.conductor_id = t.id
        WHERE p.fecha = :fecha
          AND e.empresa_sistema_id = :emp_sys
        ORDER BY p.nro_guia ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'fecha' => $fecha,
    'emp_sys' => ID_EMPRESA_SISTEMA
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales Init
$tTot = 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Diario - <?= $fecha ?></title>
    <style>
        /* Base Reset */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
        }

        /* Screen Preview */
        @media screen {
            body {
                background-color: #525659;
                display: flex;
                justify-content: center;
                padding: 40px 0;
                min-height: 100vh;
            }

            .ticket-container {
                width: 80mm;
                background-color: #fff;
                padding: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            }
        }

        /* Print Optimization */
        @media print {
            body {
                background: none;
                display: block;
            }

            .ticket-container {
                width: 100%;
                margin: 0;
                padding: 0;
                border: none !important;
                box-shadow: none !important;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }

        /* Typography */
        .ticket-container {
            font-size: 8.5pt;
            line-height: 1.2;
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .fw-bold {
            font-weight: bold;
        }

        /* Header */
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .brand {
            font-size: 10pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        .doc-title {
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            margin: 3px 0;
            border: 1px solid #000;
            display: inline-block;
            padding: 2px 5px;
        }

        .meta-info {
            font-size: 7.5pt;
            display: flex;
            justify-content: space-between;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .data-table th {
            font-size: 7.5pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding: 2px 0;
            color: #000;
        }

        .data-table td {
            font-size: 8pt;
            padding: 2px 0;
            border-bottom: 1px dashed #ccc;
            vertical-align: middle;
        }

        /* Column Config - 6 Columns */
        .col-folio {
            width: 15%;
            text-align: left;
        }

        .col-bus {
            width: 15%;
            text-align: center;
        }

        .col-val {
            width: 17%;
            text-align: right;
        }

        /* Bol, Adm, Imp */
        .col-tot {
            width: 19%;
            text-align: right;
            font-weight: 700;
            background-color: #f0f0f0;
        }

        /* Summary Box */
        .summary-box {
            border: 2px solid #000;
            padding: 5px;
            margin-top: 10px;
            text-align: center;
        }

        .summary-val {
            font-size: 12pt;
            font-weight: 800;
        }

        .footer {
            margin-top: 10px;
            font-size: 6pt;
            text-align: center;
            color: #555;
            border-top: 1px dotted #999;
            padding-top: 2px;
        }
    </style>
</head>

<body>

    <div class="ticket-container">

        <div class="header">
            <div class="brand"><?= NOMBRE_SISTEMA ?></div>
            <div class="doc-title">Resumen Diario</div>
            <div class="meta-info">
                <span><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($fecha)) ?></span>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-folio">Folio</th>
                    <th class="col-bus">Bus</th>
                    <th class="col-val">Bol</th>
                    <th class="col-val">Adm</th>
                    <th class="col-val">Imp</th>
                    <th class="col-tot">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $gBol = (int)$r['gasto_boletos'];
                    $gAdm = (int)$r['gasto_administracion'];
                    $gImp = (int)($r['gasto_imposiciones'] ?? 0);

                    // Visual Sum: Bol + Adm + Imp
                    $total = $gBol + $gAdm + $gImp;

                    $tTot += $total;
                ?>
                    <tr>
                        <td class="col-folio"><?= $r['nro_guia'] ?></td>
                        <td class="col-bus"><?= $r['numero_maquina'] ?></td>
                        <td class="col-val"><?= number_format($gBol, 0, ',', '.') ?></td>
                        <td class="col-val"><?= number_format($gAdm, 0, ',', '.') ?></td>
                        <td class="col-val"><?= number_format($gImp, 0, ',', '.') ?></td>
                        <td class="col-tot"><?= number_format($total, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTALES GENERALES -->
        <div class="summary-box">
            <div style="font-size: 9pt; text-transform: uppercase;">Total General</div>
            <div class="summary-val">$ <?= number_format($tTot, 0, ',', '.') ?></div>
            <div style="font-size: 8pt; margin-top: 2px;">
                Guías Procesadas: <strong><?= count($rows) ?></strong>
            </div>
        </div>

        <div class="footer">
            Generado el: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 300);
        }
    </script>

</body>

</html>