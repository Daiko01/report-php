<?php
// buses/exportar_excel_resumen.php
// Script para generar Excel (XLSX) con el resumen de liquidación de buses
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
date_default_timezone_set('America/Santiago');

// Prevención de salida prematura
if (ob_get_length()) ob_end_clean();
ob_start();

require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Captura de Variables
$mes = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : date('n');
$anio = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : date('Y');
$filtro_tipo = $_REQUEST['filtro_tipo'] ?? 'todos';
$bus_id = isset($_REQUEST['bus_id']) ? (int)$_REQUEST['bus_id'] : 0;
$empleador_id = isset($_REQUEST['empleador_id']) ? (int)$_REQUEST['empleador_id'] : 0;

// CONSULTA PRINCIPAL DE BUSES
$sqlBuses = "SELECT DISTINCT pb.bus_id, b.numero_maquina, b.patente, b.empleador_id,
                    e.nombre as empleador_nombre, e.rut as empleador_rut
             FROM produccion_buses pb
             JOIN buses b ON pb.bus_id = b.id
             JOIN empleadores e ON b.empleador_id = e.id
             WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ? ";

$params = [$mes, $anio];

if ($filtro_tipo === 'bus' && $bus_id) {
    $sqlBuses .= " AND b.id = ?";
    $params[] = $bus_id;
} elseif ($filtro_tipo === 'empleador' && $empleador_id) {
    $sqlBuses .= " AND b.empleador_id = ? ";
    $params[] = $empleador_id;
}

$sqlBuses .= " ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";

$stmtBuses = $pdo->prepare($sqlBuses);
$stmtBuses->execute($params);
$busesList = $stmtBuses->fetchAll();

// --------------------------------------------------------------------------
// INICIA PHPSPREADSHEET
// --------------------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Resumen $mes-$anio");

// Encabezados
$headers = [
    'N° Maquina',
    'Empleador',
    'RUT Empleador',
    'Derechos Loza',
    'Seguro/Cartolas',
    'GPS',
    'Boleta Gtía 1',
    'Boleta Gtía 2', // Nuevos
    'Total Ingresos',
    'Total Egresos',
    'Resultado (Saldo Final)'
];
$colChar = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($colChar . '1', $header);
    $colChar++;
}

// Estilo Encabezado (Gris Claro, Texto Negro, Negrita)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '000000'], // Texto Negro
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E0E0E0'], // Gris Claro
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
$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(25);

if (!$busesList) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sin Resultados</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: "info",
            title: "Sin Información",
            text: "No se encontraron registros para el periodo seleccionado.",
            confirmButtonText: "Entendido"
        }).then((result) => {
            if (result.isConfirmed || result.isDismissed) {
                window.close();
            }
        });
    </script>
    </body>
    </html>';
    exit;
} else {

    // Global Admin Logic
    $stmtP = $pdo->prepare("SELECT monto_administracion_global FROM parametros_mensuales WHERE mes = ? AND anio = ?");
    $stmtP->execute([$mes, $anio]);
    $rowP = $stmtP->fetch();

    if (!$rowP) {
        $stmtHistG = $pdo->prepare("SELECT monto_administracion_global FROM parametros_mensuales WHERE (anio < ?) OR (anio = ? AND mes < ?) ORDER BY anio DESC, mes DESC LIMIT 1");
        $stmtHistG->execute([$anio, $anio, $mes]);
        $hist_global = $stmtHistG->fetch();
        $adminGlobal = $hist_global ? (int)$hist_global['monto_administracion_global'] : 35000;
    } else {
        $adminGlobal = (int)$rowP['monto_administracion_global'];
    }

    $cacheBusPagador = [];
    $rowIndex = 2; // Iniciamos en la fila 2

    foreach ($busesList as $bus) {
        // ---------------------------------------------------------
        // LÓGICA INTELIGENTE: DETERMINACIÓN DEL BUS PAGADOR
        // ---------------------------------------------------------
        $empID = $bus['empleador_id'];
        $busPagadorId = 0;

        if (isset($cacheBusPagador[$empID])) {
            $busPagadorId = $cacheBusPagador[$empID];
        } else {
            $stmtConf = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual WHERE mes = ? AND anio = ? AND empleador_id = ?");
            $stmtConf->execute([$mes, $anio, $empID]);
            $resConf = $stmtConf->fetch();

            if ($resConf) {
                $busPagadorId = $resConf['bus_pagador_id'];
            } else {
                $stmtCount = $pdo->prepare("SELECT id FROM buses WHERE empleador_id = ?");
                $stmtCount->execute([$empID]);
                $allBusesEmp = $stmtCount->fetchAll(PDO::FETCH_COLUMN);

                if (count($allBusesEmp) === 1) {
                    $busPagadorId = $allBusesEmp[0];
                } else {
                    $stmtHist = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual 
                                            WHERE empleador_id = ? 
                                            AND ((anio < ?) OR (anio = ? AND mes < ?)) 
                                            ORDER BY anio DESC, mes DESC LIMIT 1");
                    $stmtHist->execute([$empID, $anio, $anio, $mes]);
                    $resHist = $stmtHist->fetch();
                    if ($resHist) {
                        $busPagadorId = $resHist['bus_pagador_id'];
                    }
                }
            }
            $cacheBusPagador[$empID] = $busPagadorId;
        }

        // Datos Producción
        $stmtProd = $pdo->prepare("SELECT SUM(ingreso) as ing, SUM(gasto_petroleo) as pet, SUM(gasto_boletos) as bol, 
                                        SUM(gasto_administracion) as adm, SUM(gasto_aseo) as ase, SUM(gasto_viatico) as via,
                                        SUM(pago_conductor) as pag, SUM(aporte_previsional) as apo, SUM(gasto_varios) as var,
                                        SUM(gasto_cta_extra) as ext 
                                FROM produccion_buses WHERE bus_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
        $stmtProd->execute([$bus['bus_id'], $mes, $anio]);
        $sum = $stmtProd->fetch(PDO::FETCH_ASSOC);

        // Casteo a int para evitar nulls
        $sum_ing = (int)($sum['ing'] ?? 0);
        $sum_pet = (int)($sum['pet'] ?? 0);
        $sum_bol = (int)($sum['bol'] ?? 0);
        $sum_adm = (int)($sum['adm'] ?? 0);
        $sum_ase = (int)($sum['ase'] ?? 0);
        $sum_via = (int)($sum['via'] ?? 0);
        $sum_pag = (int)($sum['pag'] ?? 0);
        $sum_apo = (int)($sum['apo'] ?? 0);
        $sum_var = (int)($sum['var'] ?? 0);

        // Datos Cierre Manual
        $stmtCierre = $pdo->prepare("SELECT * FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ?");
        $stmtCierre->execute([$bus['bus_id'], $mes, $anio]);
        $cierre = $stmtCierre->fetch(PDO::FETCH_ASSOC) ?: [];

        // Variables Cierre
        $v = [
            'sub' => (int)($cierre['subsidio_operacional'] ?? 0),
            'min' => (int)($cierre['devolucion_minutos'] ?? 0),
            'ot1' => (int)($cierre['otros_ingresos_1'] ?? 0),
            'ant' => (int)($cierre['anticipo'] ?? 0),
            'asg' => (int)($cierre['asignacion_familiar'] ?? 0),
            'pmi' => (int)($cierre['pago_minutos'] ?? 0),
            'san' => (int)($cierre['saldo_anterior'] ?? 0),
            'amu' => (int)($cierre['ayuda_mutua'] ?? 0),
            'gru' => (int)($cierre['servicio_grua'] ?? 0),
            'pol' => (int)($cierre['poliza_seguro'] ?? 0),
            'ley' => (int)($cierre['monto_leyes_sociales'] ?? 0),
            'adm_app' => isset($cierre['monto_administracion_aplicado']) ? (int)$cierre['monto_administracion_aplicado'] : $adminGlobal,
            // Nuevos Fields
            'der_loz' => (int)($cierre['derechos_loza'] ?? 0),
            'seg_car' => (int)($cierre['seguro_cartolas'] ?? 0),
            'gps' => (int)($cierre['gps'] ?? 0),
            'bol_gar' => (int)($cierre['boleta_garantia'] ?? 0),
            'bol_ga2' => (int)($cierre['boleta_garantia_dos'] ?? 0),
        ];

        // Vueltas
        $cvd = (int)($cierre['cant_vueltas_directo'] ?? 0);
        $vvd = (int)($cierre['valor_vueltas_directo'] ?? 0);
        $cvl = (int)($cierre['cant_vueltas_local'] ?? 0);
        $vvl = (int)($cierre['valor_vueltas_local'] ?? 0);
        $total_vueltas = ($cvd * $vvd) + ($cvl * $vvl);

        // CÁLCULO LAZY SAVE LEYES SOCIALES
        $monto_leyes_final = $v['ley'];

        if ($busPagadorId > 0 && $bus['bus_id'] == $busPagadorId) {
            if ($monto_leyes_final == 0) {
                $empleadorIdCalc = $bus['empleador_id'] ?? 0;
                // Calculo leyes sociales (simplificado para no duplicar demasiado código en este bloque de reemplazo)
                $stmtPlan = $pdo->prepare("SELECT 
                            sueldo_imponible, descuento_afp, descuento_salud, adicional_salud_apv, seguro_cesantia, sindicato, cesantia_licencia_medica,
                            aportes, asignacion_familiar_calculada, sis_aplicado, tasa_mutual_aplicada,
                            trabajador_id,
                            (SELECT estado_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as estado_prev,
                            (SELECT sistema_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as sistema_prev,
                            tipo_contrato, cotiza_cesantia_pensionado
                        FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ?");
                $stmtPlan->execute([$empleadorIdCalc, $mes, $anio]);
                $rowsPlan = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

                if ($rowsPlan) {
                    $TASA_CAP_IND_CONST = 0.001;
                    $TASA_EXP_VIDA_CONST = 0.009;
                    $total_imponible_all = 0;
                    $total_imponible_sis = 0;
                    $sum_desc_worker = 0;
                    $sum_sindicato = 0;
                    $sum_asig_fam = 0;
                    $calc_seguro_cesantia_patronal = 0;

                    $tasa_sis = isset($rowsPlan[0]['sis_aplicado']) ? ((float)$rowsPlan[0]['sis_aplicado'] / 100) : 0.0;
                    $tasa_mutual = isset($rowsPlan[0]['tasa_mutual_aplicada']) ? ((float)$rowsPlan[0]['tasa_mutual_aplicada'] / 100) : 0.0;

                    foreach ($rowsPlan as $r) {
                        $total_imponible_all += $r['sueldo_imponible'];
                        if ($r['sistema_prev'] == 'AFP' && $r['estado_prev'] != 'Pensionado') $total_imponible_sis += $r['sueldo_imponible'];
                        $row_desc = $r['descuento_afp'] + $r['descuento_salud'] + $r['adicional_salud_apv'] +
                            $r['seguro_cesantia'] + $r['sindicato'] + $r['cesantia_licencia_medica'];
                        $sum_desc_worker += $row_desc;
                        $sum_sindicato += $r['sindicato'];
                        $sum_asig_fam += $r['asignacion_familiar_calculada'];

                        $debe_pagar_sc = false;
                        if ($r['estado_prev'] == 'Activo') $debe_pagar_sc = true;
                        elseif (isset($r['cotiza_cesantia_pensionado']) && $r['cotiza_cesantia_pensionado'] == 1) $debe_pagar_sc = true;

                        if ($debe_pagar_sc) {
                            $rate_sc = ($r['tipo_contrato'] == 'Fijo') ? 0.03 : 0.024;
                            $calc_seguro_cesantia_patronal += floor($r['sueldo_imponible'] * $rate_sc);
                        }
                    }
                    $calc_aporte_mutual = floor($total_imponible_all * $tasa_mutual);
                    $calc_sis = floor($total_imponible_sis * $tasa_sis);
                    $calc_cap_ind = floor($total_imponible_sis * $TASA_CAP_IND_CONST);
                    $calc_exp_vida = floor($total_imponible_sis * $TASA_EXP_VIDA_CONST);

                    $leyes_sociales_empleado = $sum_desc_worker - $sum_sindicato;
                    $leyes_sociales_empleador = $calc_sis + $calc_seguro_cesantia_patronal + $calc_cap_ind + $calc_exp_vida;

                    $monto_leyes_final = $leyes_sociales_empleado + $leyes_sociales_empleador + $calc_aporte_mutual - $sum_asig_fam;
                }
            }
        }
        $v['ley'] = $monto_leyes_final;

        // Devolución Boletos
        $dev_bol = 0;
        if ($sum_bol > 0) {
            $dev_bol = $sum_bol - (($sum_bol / 5000) * 1095);
        }

        $haberes = $sum_adm + $v['sub'] + $v['min'] + $dev_bol + $v['ot1'];
        $nuevos_cargos_total = $v['der_loz'] + $v['seg_car'] + $v['gps'] + $v['bol_gar'] + $v['bol_ga2'];
        $descuentos = $v['adm_app'] + $v['ley'] + $total_vueltas + $v['ant'] + $v['pmi'] + $v['san'] + $v['amu'] + $v['gru'] + $v['pol'] + $v['asg'] + $nuevos_cargos_total;
        $saldo_final = $haberes - $descuentos;

        // Escribir fila
        $col = 'A';
        $sheet->setCellValue($col++ . $rowIndex, $bus['numero_maquina']);
        $sheet->setCellValue($col++ . $rowIndex, $bus['empleador_nombre']);
        $sheet->setCellValue($col++ . $rowIndex, $bus['empleador_rut']);

        $sheet->setCellValue($col++ . $rowIndex, $v['der_loz']);
        $sheet->setCellValue($col++ . $rowIndex, $v['seg_car']);
        $sheet->setCellValue($col++ . $rowIndex, $v['gps']);
        $sheet->setCellValue($col++ . $rowIndex, $v['bol_gar']);
        $sheet->setCellValue($col++ . $rowIndex, $v['bol_ga2']);

        $sheet->setCellValue($col++ . $rowIndex, $haberes);
        $sheet->setCellValue($col++ . $rowIndex, $descuentos);
        $sheet->setCellValue($col++ . $rowIndex, $saldo_final);

        // Formato moneda (col I a K) -> Ahora con nuevas columnas es J, K, L... 
        // A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, I=9, J=10, K=11
        // D,E,F,G,H son montos. I, J, K son montos finales.
        // Formatear desde D hasta K
        $sheet->getStyle('D' . $rowIndex . ':K' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');

        $rowIndex++;
    }
}

// Auto-size
foreach (range('A', 'K') as $colC) {
    if ($colC === 'B') continue;
    $sheet->getColumnDimension($colC)->setAutoSize(true);
}
// Forzar ancho razonable para empleador si es muy largo
$sheet->getColumnDimension('B')->setAutoSize(false);
$sheet->getColumnDimension('B')->setWidth(40);

// Nombres de Meses en Español
$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];
$nombreMes = isset($meses[$mes]) ? $meses[$mes] : $mes;

// Salida
if (ob_get_length()) ob_end_clean();

// Generar nombre de archivo temporal único
$temp_file = tempnam(sys_get_temp_dir(), 'excel_bp_');
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

// Verificar y enviar
if (file_exists($temp_file)) {
    // Limpiar cualquier buffer previo por seguridad extremo
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "Reporte_Flota_" . $nombreMes . "_" . $anio . ".xlsx";

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($temp_file));

    readfile($temp_file);
    unlink($temp_file); // Eliminar archivo temporal
    exit;
} else {
    die("Error crítico: No se pudo generar el archivo temporal.");
}
