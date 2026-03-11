<?php
// buses/exportar_excel_resumen.php
// Script para generar Excel (XLSX) con diseño idéntico a la plantilla solicitada por el usuario
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
date_default_timezone_set('America/Santiago');

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
$unidad_id = isset($_REQUEST['unidad_id']) ? (int)$_REQUEST['unidad_id'] : 0;
$aplicar_subsidio = isset($_REQUEST['aplicar_subsidio']) ? (int)$_REQUEST['aplicar_subsidio'] : 0;

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

// CONSULTA PRINCIPAL
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
} elseif ($filtro_tipo === 'unidad' && $unidad_id) {
    $sqlBuses = "SELECT DISTINCT pb.bus_id, b.numero_maquina, b.patente, b.empleador_id,
                        e.nombre as empleador_nombre, e.rut as empleador_rut
                 FROM produccion_buses pb
                 JOIN buses b ON pb.bus_id = b.id
                 JOIN terminales t ON b.terminal_id = t.id
                 JOIN empleadores e ON b.empleador_id = e.id
                 WHERE MONTH(pb.fecha) = ? AND YEAR(pb.fecha) = ? 
                 AND t.unidad_id = ?";
    $params = [$mes, $anio, $unidad_id];
}
$sqlBuses .= " ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";

$stmtBuses = $pdo->prepare($sqlBuses);
$stmtBuses->execute($params);
$busesList = $stmtBuses->fetchAll();

if (!$busesList) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>Swal.fire({icon: "info",title: "Sin Información",text: "No se encontraron registros para el periodo seleccionado.",confirmButtonText: "Entendido"}).then(() => { window.close(); });</script></body></html>';
    exit;
}

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

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri'); // Cambiado a Calibri para mejor legibilidad
$spreadsheet->getDefaultStyle()->getFont()->setSize(10);
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Liquidaciones");

$cacheBusPagador = [];
$r = 1;

$thinBorder = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D0D0D0'], // Borde más suave
        ],
    ],
];
$greyFill = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F2F4F8'], // Gris azulado extra suave
    ],
    'font' => [
        'color' => ['rgb' => '404040'],
    ]
];
$blueFill = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E6F0FA'], // Celeste muy suave, menos agresivo
    ],
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '003366'], // Texto azul oscuro para contraste
    ]
];
$headerBlueFill = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D9E2EC'], // Azul grisáceo para cabeceras
    ],
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '102A43'], // Texto oscuro
    ]
];

foreach ($busesList as $index => $bus) {
    // Calcular LEYES SOCIALES Pagador
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
                $stmtHist = $pdo->prepare("SELECT bus_pagador_id FROM configuracion_leyes_mensual WHERE empleador_id = ? AND ((anio < ?) OR (anio = ? AND mes < ?)) ORDER BY anio DESC, mes DESC LIMIT 1");
                $stmtHist->execute([$empID, $anio, $anio, $mes]);
                $resHist = $stmtHist->fetch();
                if ($resHist) $busPagadorId = $resHist['bus_pagador_id'];
            }
        }
        $cacheBusPagador[$empID] = $busPagadorId;
    }

    // CABECERA (Fila 1)
    $sheet->setCellValue("A$r", 'EMPLEADOR:');
    $sheet->getStyle("A$r")->getFont()->setBold(true);

    $sheet->setCellValue("B$r", $bus['empleador_nombre']);
    $sheet->mergeCells("B$r:F$r");

    $sheet->setCellValue("G$r", 'RUT:');
    $sheet->getStyle("G$r")->getFont()->setBold(true);

    $sheet->setCellValue("H$r", $bus['empleador_rut']);
    $sheet->mergeCells("H$r:I$r");

    $sheet->setCellValue("J$r", 'RESUMEN MENSUAL DE PRODUCCIÓN');
    $sheet->mergeCells("J$r:N$r");
    $sheet->getStyle("J$r")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle("J$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue("O$r", 'BUS N°');
    // Asegurar que el número de máquina se guarde como texto para evitar separadores de miles automáticos
    $sheet->setCellValueExplicit("P$r", $bus['numero_maquina'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue("Q$r", 'Patente:');
    $sheet->setCellValue("R$r", $bus['patente']);
    $sheet->mergeCells("R$r:T$r");

    $sheet->getStyle("O$r:Q$r")->getFont()->setBold(true);
    $sheet->getStyle("P$r")->getFont()->setSize(12);
    // Alineamos el numero de bus al centro
    $sheet->getStyle("P$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $r++; // Fila 2
    $sheet->setCellValue("C$r", "PERIODO: $mes / $anio");
    $sheet->mergeCells("C$r:D$r");
    $sheet->getStyle("C$r")->getFont()->setBold(true);

    // Inicializar variables de produccion (se llenan solo en modo normal)
    $produccion = [];
    $sum = ['ing' => 0, 'pet' => 0, 'bol' => 0, 'adm' => 0, 'ase' => 0, 'via' => 0, 'pag' => 0, 'apo' => 0, 'var' => 0];

    if ($aplicar_subsidio != 1) {
        $r++; // Fila 3 - Cabeceras de Producción

        $headersDetalle = [
            ['A', 'A', 'Dia'],
            ['B', 'B', 'Guía'],
            ['C', 'D', 'Ingreso'],
            ['E', 'F', 'Petróleo'],
            ['G', 'H', 'Boletos'],
            ['I', 'I', 'Admin'],
            ['J', 'J', 'Aseo'],
            ['K', 'L', 'Viático'],
            ['M', 'N', '% Cond'],
            ['O', 'P', 'Aportes'],
            ['Q', 'Q', 'Varios'],
            ['R', 'U', 'Conductor']
        ];

        foreach ($headersDetalle as $hd) {
            $sheet->setCellValue($hd[0] . $r, $hd[2]);
            if ($hd[0] !== $hd[1]) {
                $sheet->mergeCells($hd[0] . $r . ':' . $hd[1] . $r);
            }
        }
        $sheet->getStyle("A$r:U$r")->applyFromArray($headerBlueFill);
        $sheet->getStyle("A$r:U$r")->getFont()->setBold(true);
        $sheet->getStyle("A$r:U$r")->applyFromArray($thinBorder);
        $sheet->getStyle("A$r:U$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r++;

        // Extraer Produccion
        $stmtProd = $pdo->prepare("
            SELECT p.*, t.nombre as nombre_conductor_real, t.es_excedente
            FROM produccion_buses p
            LEFT JOIN trabajadores t ON p.conductor_id = t.id
            WHERE p.bus_id = ? AND MONTH(p.fecha) = ? AND YEAR(p.fecha) = ?
            ORDER BY p.fecha ASC
        ");
        $stmtProd->execute([$bus['bus_id'], $mes, $anio]);
        $produccion = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        $sum = ['ing' => 0, 'pet' => 0, 'bol' => 0, 'adm' => 0, 'ase' => 0, 'via' => 0, 'pag' => 0, 'apo' => 0, 'var' => 0];

        foreach ($produccion as $rowP) {
            $ing = $rowP['ingreso'] ?? 0;
            $pet = $rowP['gasto_petroleo'] ?? 0;
            $bol = $rowP['gasto_boletos'] ?? 0;
            $adm = $rowP['gasto_administracion'] ?? 0;
            $ase = $rowP['gasto_aseo'] ?? 0;
            $via = $rowP['gasto_viatico'] ?? 0;
            $pag = $rowP['pago_conductor'] ?? 0;
            $apo = $rowP['aporte_previsional'] ?: ($rowP['gasto_imposiciones'] ?? 0);
            $var = $rowP['gasto_varios'] ?? 0;

            $nom = $rowP['nombre_conductor_real'] ?? ($rowP['conductor_nombre_csv'] ?? '---');
            if (!empty($rowP['es_excedente']) && $rowP['es_excedente'] == 1) $nom .= ' (EX)';

            $sum['ing'] += $ing;
            $sum['pet'] += $pet;
            $sum['bol'] += $bol;
            $sum['adm'] += $adm;
            $sum['ase'] += $ase;
            $sum['via'] += $via;
            $sum['pag'] += $pag;
            $sum['apo'] += $apo;
            $sum['var'] += $var;

            $dia = date('d', strtotime($rowP['fecha']));

            $sheet->setCellValue("A$r", $dia);
            $sheet->setCellValue("B$r", $rowP['nro_guia'] ?? '');
            $sheet->setCellValue("C$r", $ing);
            $sheet->mergeCells("C$r:D$r");
            $sheet->setCellValue("E$r", $pet);
            $sheet->mergeCells("E$r:F$r");
            $sheet->setCellValue("G$r", $bol);
            $sheet->mergeCells("G$r:H$r");
            $sheet->setCellValue("I$r", $adm);
            $sheet->setCellValue("J$r", $ase);
            $sheet->setCellValue("K$r", $via);
            $sheet->mergeCells("K$r:L$r");
            $sheet->setCellValue("M$r", $pag);
            $sheet->mergeCells("M$r:N$r");
            $sheet->setCellValue("O$r", $apo);
            $sheet->mergeCells("O$r:P$r");
            $sheet->setCellValue("Q$r", $var);
            $sheet->setCellValue("R$r", $nom);
            $sheet->mergeCells("R$r:U$r");

            $sheet->getStyle("A$r:U$r")->applyFromArray($thinBorder);
            $sheet->getStyle("A$r:B$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
        }

        // Totales Cierre de Tabla Diaria
        $sheet->setCellValue("A$r", "");
        $sheet->setCellValue("B$r", "TOTALES:");
        $sheet->setCellValue("C$r", $sum['ing']);
        $sheet->mergeCells("C$r:D$r");
        $sheet->setCellValue("E$r", $sum['pet']);
        $sheet->mergeCells("E$r:F$r");
        $sheet->setCellValue("G$r", $sum['bol']);
        $sheet->mergeCells("G$r:H$r");
        $sheet->setCellValue("I$r", $sum['adm']);
        $sheet->setCellValue("J$r", $sum['ase']);
        $sheet->setCellValue("K$r", $sum['via']);
        $sheet->mergeCells("K$r:L$r");
        $sheet->setCellValue("M$r", $sum['pag']);
        $sheet->mergeCells("M$r:N$r");
        $sheet->setCellValue("O$r", $sum['apo']);
        $sheet->mergeCells("O$r:P$r");
        $sheet->setCellValue("Q$r", $sum['var']);
        $sheet->mergeCells("R$r:U$r");

        $sheet->getStyle("A$r:U$r")->applyFromArray($greyFill);
        $sheet->getStyle("A$r:U$r")->getFont()->setBold(true);
        $sheet->getStyle("A$r:U$r")->applyFromArray($thinBorder);

        // Formato de miles
        $sheet->getStyle("C4:Q$r")->getNumberFormat()->setFormatCode('#,##0');
        $r += 2; // Espacio a los Summary Tables

    } else {
        // Modo subsidio: mostrar cabeceras + fila en blanco de totales sin datos
        $r++; // Fila 3 - Cabeceras
        $headersDetalle = [
            ['A', 'A', 'Dia'],
            ['B', 'B', 'Guía'],
            ['C', 'D', 'Ingreso'],
            ['E', 'F', 'Petróleo'],
            ['G', 'H', 'Boletos'],
            ['I', 'I', 'Admin'],
            ['J', 'J', 'Aseo'],
            ['K', 'L', 'Viático'],
            ['M', 'N', '% Cond'],
            ['O', 'P', 'Aportes'],
            ['Q', 'Q', 'Varios'],
            ['R', 'U', 'Conductor']
        ];
        foreach ($headersDetalle as $hd) {
            $sheet->setCellValue($hd[0] . $r, $hd[2]);
            if ($hd[0] !== $hd[1]) $sheet->mergeCells($hd[0] . $r . ':' . $hd[1] . $r);
        }
        $sheet->getStyle("A$r:U$r")->applyFromArray($headerBlueFill);
        $sheet->getStyle("A$r:U$r")->getFont()->setBold(true);
        $sheet->getStyle("A$r:U$r")->applyFromArray($thinBorder);
        $sheet->getStyle("A$r:U$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;
        // Fila de totales vacia
        $sheet->setCellValue("B$r", 'TOTALES:');
        $sheet->mergeCells("C$r:D$r");
        $sheet->mergeCells("R$r:U$r");
        $sheet->getStyle("A$r:U$r")->applyFromArray($greyFill);
        $sheet->getStyle("A$r:U$r")->getFont()->setBold(true);
        $sheet->getStyle("A$r:U$r")->applyFromArray($thinBorder);
        $r += 2; // Espacio a los Summary Tables
    } // fin if/else

    // Datos de Cierre
    $stmtCierre = $pdo->prepare("SELECT * FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ?");
    $stmtCierre->execute([$bus['bus_id'], $mes, $anio]);
    $cierre = $stmtCierre->fetch(PDO::FETCH_ASSOC) ?: [];

    // Subsidio: en modo ON usa subsidios_operacionales (bruto), en modo OFF usa cierres_maquinas
    $monto_subsidio_op = 0;
    $sub_descuento_gps = 0;
    $sub_descuento_boleta = 0;

    if ($aplicar_subsidio == 1) {
        $stmtSub = $pdo->prepare("SELECT monto_subsidio, descuento_gps, descuento_boleta FROM subsidios_operacionales WHERE bus_id = ? AND mes = ? AND anio = ?");
        $stmtSub->execute([$bus['bus_id'], $mes, $anio]);
        $resSub = $stmtSub->fetch();
        if ($resSub) {
            $monto_subsidio_op = (int)$resSub['monto_subsidio'];
            $sub_descuento_gps = (int)($resSub['descuento_gps'] ?? 0);
            $sub_descuento_boleta = (int)($resSub['descuento_boleta'] ?? 0);
        }
    }

    // Con switch OFF, el subsidio viene del campo del cierre mensual
    $sub_val = ($aplicar_subsidio == 1)
        ? $monto_subsidio_op
        : (int)($cierre['subsidio_operacional'] ?? 0);

    $v = [
        'sub' => $sub_val,
        'min' => (int)($cierre['devolucion_minutos'] ?? 0),
        'ot1' => (int)($cierre['otros_ingresos_1'] ?? 0),
        'ot2' => (int)($cierre['otros_ingresos_2'] ?? 0),
        'ot3' => (int)($cierre['otros_ingresos_3'] ?? 0),
        'ant' => (int)($cierre['anticipo'] ?? 0),
        'asg' => (int)($cierre['asignacion_familiar'] ?? 0),
        'pmi' => (int)($cierre['pago_minutos'] ?? 0),
        'san' => (int)($cierre['saldo_anterior'] ?? 0),
        'amu' => (int)($cierre['ayuda_mutua'] ?? 0),
        'gru' => (int)($cierre['servicio_grua'] ?? 0),
        'pol' => (int)($cierre['poliza_seguro'] ?? 0),
        'ley' => (int)($cierre['monto_leyes_sociales'] ?? 0),
        'adm_app' => isset($cierre['monto_administracion_aplicado']) ? (int)$cierre['monto_administracion_aplicado'] : $adminGlobal,
        'der_loz' => (int)($cierre['derechos_loza'] ?? 0),
        'seg_car' => (int)($cierre['seguro_cartolas'] ?? 0),
        'gps' => (int)($cierre['gps'] ?? 0),
        'bol_gar' => (int)($cierre['boleta_garantia'] ?? 0),
        'bol_ga2' => (int)($cierre['boleta_garantia_dos'] ?? 0)
    ];

    $cvd = (int)($cierre['cant_vueltas_directo'] ?? 0);
    $vvd = (int)($cierre['valor_vueltas_directo'] ?? 0);
    $cvl = (int)($cierre['cant_vueltas_local'] ?? 0);
    $vvl = (int)($cierre['valor_vueltas_local'] ?? 0);
    $total_vueltas = ($cvd * $vvd) + ($cvl * $vvl);

    // MODO SUBSIDIO: Si el switch está ON, anular TODOS los cargos.
    // El subsidio es un pago independiente, no debe incluir admin, leyes ni cargos del mes.
    // Excepción: GPS y Boleta Garantía se toman del descuento registrado en subsidios_operacionales.
    if ($aplicar_subsidio == 1) {
        $v['min'] = 0;
        $v['ot1'] = 0;
        $v['ant'] = 0;
        $v['asg'] = 0;
        $v['pmi'] = 0;
        $v['san'] = 0;
        $v['amu'] = 0;
        $v['gru'] = 0;
        $v['pol'] = 0;
        $v['ley'] = 0;
        $v['adm_app'] = 0;
        $v['der_loz'] = 0;
        $v['seg_car'] = 0;
        $v['gps'] = $sub_descuento_gps;
        $v['bol_gar'] = $sub_descuento_boleta;
        $v['bol_ga2'] = 0;
        $total_vueltas = 0;
    }

    // Leyes
    $monto_leyes_final = $v['ley'];
    if ($busPagadorId > 0 && $bus['bus_id'] == $busPagadorId && $monto_leyes_final == 0) {
        $empleadorIdCalc = $bus['empleador_id'] ?? 0;
        $stmtPlan = $pdo->prepare("SELECT sueldo_imponible, descuento_afp, descuento_salud, adicional_salud_apv, seguro_cesantia, sindicato, cesantia_licencia_medica, asignacion_familiar_calculada, sis_aplicado, tasa_mutual_aplicada, (SELECT estado_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as estado_prev, (SELECT sistema_previsional FROM trabajadores WHERE id = planillas_mensuales.trabajador_id) as sistema_prev, tipo_contrato, cotiza_cesantia_pensionado FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ?");
        $stmtPlan->execute([$empleadorIdCalc, $mes, $anio]);
        $rowsPlan = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

        if ($rowsPlan) {
            $total_imponible_all = 0;
            $total_imponible_sis = 0;
            $sum_desc_worker = 0;
            $sum_sindicato = 0;
            $sum_asig_fam = 0;
            $calc_seguro_cesantia_patronal = 0;
            $tasa_sis = isset($rowsPlan[0]['sis_aplicado']) ? ((float)$rowsPlan[0]['sis_aplicado'] / 100) : 0.0;
            $tasa_mutual = isset($rowsPlan[0]['tasa_mutual_aplicada']) ? ((float)$rowsPlan[0]['tasa_mutual_aplicada'] / 100) : 0.0;

            foreach ($rowsPlan as $rw) {
                $total_imponible_all += $rw['sueldo_imponible'];
                if ($rw['sistema_prev'] == 'AFP' && $rw['estado_prev'] != 'Pensionado') $total_imponible_sis += $rw['sueldo_imponible'];
                $sum_desc_worker += ($rw['descuento_afp'] + $rw['descuento_salud'] + $rw['adicional_salud_apv'] + $rw['seguro_cesantia'] + $rw['sindicato'] + $rw['cesantia_licencia_medica']);
                $sum_sindicato += $rw['sindicato'];
                $sum_asig_fam += $rw['asignacion_familiar_calculada'];

                if ($rw['estado_prev'] == 'Activo' || (isset($rw['cotiza_cesantia_pensionado']) && $rw['cotiza_cesantia_pensionado'] == 1)) {
                    $rate_sc = ($rw['tipo_contrato'] == 'Fijo') ? 0.03 : 0.024;
                    $calc_seguro_cesantia_patronal += floor($rw['sueldo_imponible'] * $rate_sc);
                }
            }
            $monto_leyes_final = ($sum_desc_worker - $sum_sindicato) + (floor($total_imponible_sis * $tasa_sis) + $calc_seguro_cesantia_patronal + floor($total_imponible_sis * 0.001) + floor($total_imponible_sis * 0.009)) + floor($total_imponible_all * $tasa_mutual) - $sum_asig_fam;
        }
    }
    $v['ley'] = $monto_leyes_final;

    $dev_bol = ($sum['bol'] > 0) ? ($sum['bol'] - (($sum['bol'] / 5000) * 1095)) : 0;
    $var_cargos = $v['pol'] + $v['gru'] + $v['pmi'];

    $haberes = $sum['adm'] + $v['sub'] + $v['min'] + $dev_bol + $v['ot1'] + $v['ot2'] + $v['ot3'];
    $total_nuevos_cargos = $v['der_loz'] + $v['seg_car'] + $v['gps'] + $v['bol_gar'] + $v['bol_ga2'];
    // total_vueltas es solo informativo, NO se descuenta del saldo
    $descuentos = $v['adm_app'] + $v['ley'] + $v['ant'] + $v['pmi'] + $v['san'] + $v['amu'] + $v['gru'] + $v['pol'] + $v['asg'] + $total_nuevos_cargos;
    $saldo_final = $haberes - $descuentos;

    // ---------------------------------------------------------
    // DIBUJO DE LOS 4 RECUADROS DE RESUMEN (Filas 7 a 12 de screenshot)
    // ---------------------------------------------------------

    // CABECERAS CUADROS
    $sheet->setCellValue("A$r", "INGRESOS / HABERES");
    $sheet->mergeCells("A$r:D$r");
    $sheet->setCellValue("E$r", "DESC. / CARGOS VARIOS");
    $sheet->mergeCells("E$r:J$r");
    $sheet->setCellValue("K$r", "CARGOS FIJOS / OTROS");
    $sheet->mergeCells("K$r:O$r");
    $sheet->setCellValue("P$r", "RESUMEN TOTAL");
    $sheet->mergeCells("P$r:U$r");

    $sheet->getStyle("A$r:U$r")->applyFromArray($headerBlueFill);
    $sheet->getStyle("A$r:U$r")->getFont()->setBold(true);
    $sheet->getStyle("A$r:U$r")->applyFromArray($thinBorder);
    $sheet->getStyle("A$r:U$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $r++;

    $rBoxStart = $r; // Fila 7

    // INGRESOS (A:D)
    $c1 = [
        ['Guías Cortadas (N° ' . count($produccion) . ')', $sum['adm']],
        ['Subsidio Operacional', $v['sub']],
        ['Devolución Minutos', $v['min']],
        ['Devolución Boletos', $dev_bol],
        ['Otros Ingresos', $v['ot1'] + $v['ot2'] + $v['ot3']]
    ];
    // DESC VARIOS (E:J) — Control Vueltas removido, es solo informativo
    $c2 = [
        ['Administración', $v['adm_app']],
        ['Leyes Sociales', $v['ley']],
        ['Anticipos', $v['ant']],
        ['Asignación Familiar', $v['asg']],
        ['Saldo Ant / Ay. Mutua', $v['san'] + $v['amu']],
        ['Varios (Seguro/Grúa/Etc)', $var_cargos]
    ];
    // CARGOS FIJOS (K:O)
    $c3 = [
        ['Derechos de Loza', $v['der_loz']],
        ['Seguro y Cartolas', ($mes == 9 ? $v['seg_car'] : 0)],
        ['GPS', $v['gps']],
        ['Boleta Garantía', $v['bol_gar']],
        ['Asesorías Varias', $v['bol_ga2']]
    ];

    $maxRows = 6; // Fila 7 a 12 inclusive

    for ($i = 0; $i < $maxRows; $i++) {
        $currentRow = $rBoxStart + $i;

        // INGRESOS
        if ($i < 5) {
            $sheet->setCellValue("A$currentRow", $c1[$i][0]);
            $sheet->mergeCells("A$currentRow:C$currentRow");
            $sheet->setCellValue("D$currentRow", $c1[$i][1]);
        }
        // DESCUENTOS
        if ($i < 6) {
            $sheet->setCellValue("E$currentRow", $c2[$i][0]);
            $sheet->mergeCells("E$currentRow:I$currentRow");
            $sheet->setCellValue("J$currentRow", $c2[$i][1]);
        }
        // CARGOS
        if ($i < 5) {
            $sheet->setCellValue("K$currentRow", $c3[$i][0]);
            $sheet->mergeCells("K$currentRow:N$currentRow");
            $sheet->setCellValue("O$currentRow", $c3[$i][1]);
        }
    }

    // Filas informativas de Control Vueltas (Solo Referencia, no descuento)
    $greenRefFill = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF5EA']],
        'font' => ['italic' => true, 'color' => ['rgb' => '2D6A4F'], 'size' => 8]
    ];
    $greenHeaderFill = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1ECE0']],
        'font' => ['bold' => true, 'color' => ['rgb' => '1B4332'], 'size' => 8]
    ];

    // Fila referencia cabecera
    $rRef = $rBoxStart + 6;
    $sheet->setCellValue("E$rRef", 'Control Vueltas (Solo Ref.)');
    $sheet->mergeCells("E$rRef:J$rRef");
    $sheet->getStyle("E$rRef:J$rRef")->applyFromArray($greenHeaderFill);
    $sheet->getStyle("E$rRef:J$rRef")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("E$rRef:J$rRef")->applyFromArray($thinBorder);
    $rRef++;

    // Fila Dir
    $sheet->setCellValue("E$rRef", 'Dir: ' . $cvd . ' x $' . number_format($vvd, 0, ',', '.'));
    $sheet->mergeCells("E$rRef:I$rRef");
    $sheet->setCellValue("J$rRef", $cvd * $vvd);
    $sheet->getStyle("E$rRef:J$rRef")->applyFromArray($greenRefFill);
    $sheet->getStyle("E$rRef:J$rRef")->applyFromArray($thinBorder);
    $sheet->getStyle("J$rRef")->getNumberFormat()->setFormatCode('#,##0');
    $rRef++;

    // Fila Loc
    $sheet->setCellValue("E$rRef", 'Loc: ' . $cvl . ' x $' . number_format($vvl, 0, ',', '.'));
    $sheet->mergeCells("E$rRef:I$rRef");
    $sheet->setCellValue("J$rRef", $cvl * $vvl);
    $sheet->getStyle("E$rRef:J$rRef")->applyFromArray($greenRefFill);
    $sheet->getStyle("E$rRef:J$rRef")->applyFromArray($thinBorder);
    $sheet->getStyle("J$rRef")->getNumberFormat()->setFormatCode('#,##0');

    // Fila 12 Totales manuales
    $rTotal = $rBoxStart + 5; // Índice del loop si $i == 5
    $sheet->setCellValue("A$rTotal", "TOTAL HABERES");
    $sheet->mergeCells("A$rTotal:C$rTotal");
    $sheet->setCellValue("D$rTotal", $haberes);
    $sheet->getStyle("A$rTotal:D$rTotal")->applyFromArray($greyFill);
    $sheet->getStyle("A$rTotal:D$rTotal")->getFont()->setBold(true);

    $sheet->setCellValue("K$rTotal", "SUBTOTAL CARGOS");
    $sheet->mergeCells("K$rTotal:N$rTotal");
    $sheet->setCellValue("O$rTotal", $total_nuevos_cargos);
    $sheet->getStyle("K$rTotal:O$rTotal")->applyFromArray($greyFill);
    $sheet->getStyle("K$rTotal:O$rTotal")->getFont()->setBold(true);

    // RESUMEN TOTAL (P:U)
    // Fila 7: Haberes
    $sheet->setCellValue("P" . $rBoxStart, "Total Haberes:");
    $sheet->mergeCells("P" . $rBoxStart . ":R" . $rBoxStart);
    $sheet->setCellValue("S" . $rBoxStart, $haberes);
    $sheet->mergeCells("S" . $rBoxStart . ":U" . $rBoxStart);
    $sheet->getStyle("S" . $rBoxStart)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Fila 8: Descuentos
    $sheet->setCellValue("P" . ($rBoxStart + 1), "Total Desc. + Cargos:");
    $sheet->mergeCells("P" . ($rBoxStart + 1) . ":R" . ($rBoxStart + 1));
    $sheet->setCellValue("S" . ($rBoxStart + 1), $descuentos);
    $sheet->mergeCells("S" . ($rBoxStart + 1) . ":U" . ($rBoxStart + 1));
    $sheet->getStyle("S" . ($rBoxStart + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Fila 9: Vacia P:U
    $sheet->mergeCells("P" . ($rBoxStart + 2) . ":U" . ($rBoxStart + 2));

    // Fila 10: SALDO FINAL A PAGAR Titulo
    $sheet->setCellValue("P" . ($rBoxStart + 3), "SALDO FINAL A PAGAR");
    $sheet->mergeCells("P" . ($rBoxStart + 3) . ":U" . ($rBoxStart + 3));

    // Fila 11 y 12: Valor del saldo
    $sheet->setCellValueExplicit("P" . ($rBoxStart + 4), "$ " . number_format($saldo_final, 0, ',', '.'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); // Formateado explicitamente para evitar el overflow
    $sheet->mergeCells("P" . ($rBoxStart + 4) . ":U" . ($rBoxStart + 5));

    // Estilos del recuadro Azul
    $sheet->getStyle("P" . ($rBoxStart + 3) . ":U" . ($rBoxStart + 5))->applyFromArray($blueFill);
    $sheet->getStyle("P" . ($rBoxStart + 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("P" . ($rBoxStart + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("P" . ($rBoxStart + 4))->getFont()->setSize(16); // Ligeramente mas grande para destacar mas

    // Formato Miles
    $sheet->getStyle("D$rBoxStart:D$rTotal")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("J$rBoxStart:J$rTotal")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("O$rBoxStart:O$rTotal")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("S" . $rBoxStart . ":S" . ($rBoxStart + 1))->getNumberFormat()->setFormatCode('#,##0');

    // Bordes cuadros
    $sheet->getStyle("A$rBoxStart:D$rTotal")->applyFromArray($thinBorder);
    $sheet->getStyle("E$rBoxStart:J$rTotal")->applyFromArray($thinBorder);
    $sheet->getStyle("K$rBoxStart:O$rTotal")->applyFromArray($thinBorder);
    $sheet->getStyle("P$rBoxStart:U$rTotal")->applyFromArray($thinBorder);

    $r = $rTotal + 4; // Espacio
}

// --------------------------------------------------------------------------
// AJUSTES FINALES DE HOJA
// --------------------------------------------------------------------------

// Redimensionar columnas para que coincida con el mockup
$sheet->getColumnDimension('A')->setWidth(13); // Aumentado para que calce la palabra "EMPLEADOR:"
$sheet->getColumnDimension('B')->setWidth(15); // Guia
$sheet->getColumnDimension('C')->setWidth(10);  // Ingreso pt1
$sheet->getColumnDimension('D')->setWidth(13); // Ingreso pt2 (Totales caben aca)
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(10);
$sheet->getColumnDimension('G')->setWidth(10);
$sheet->getColumnDimension('H')->setWidth(10);
$sheet->getColumnDimension('I')->setWidth(11); // Admin, A
$sheet->getColumnDimension('J')->setWidth(11); // Aseo, J
$sheet->getColumnDimension('K')->setWidth(10);
$sheet->getColumnDimension('L')->setWidth(10);
$sheet->getColumnDimension('M')->setWidth(10);
$sheet->getColumnDimension('N')->setWidth(10);
$sheet->getColumnDimension('O')->setWidth(11);
$sheet->getColumnDimension('P')->setWidth(11);
$sheet->getColumnDimension('Q')->setWidth(11);
$sheet->getColumnDimension('R')->setWidth(13);
$sheet->getColumnDimension('S')->setWidth(13);
$sheet->getColumnDimension('T')->setWidth(13);
$sheet->getColumnDimension('U')->setWidth(14);

$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

// Salida
if (ob_get_length()) ob_end_clean();

$temp_file = tempnam(sys_get_temp_dir(), 'excel_bp_');
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

if (file_exists($temp_file)) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    $filename = "Liquidaciones_" . $nombreMes . "_" . $anio . ".xlsx";
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($temp_file));
    readfile($temp_file);
    unlink($temp_file);
    exit;
} else {
    die("Error crítico: No se pudo generar el archivo temporal.");
}
