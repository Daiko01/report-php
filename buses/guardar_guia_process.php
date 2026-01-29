<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // 1. Recibir y Validar Datos Básicos
    $fecha = $_POST['fecha'];
    $nro_guia = trim($_POST['nro_guia']);
    $bus_id = (int)$_POST['bus_id'];
    $empleador_id = (int)($_POST['empleador_id'] ?? 0);
    $conductor_id = (int)$_POST['conductor_id'];
    $guia_id = isset($_POST['guia_id']) ? (int)$_POST['guia_id'] : 0;

    if (!$fecha || !$nro_guia || !$bus_id || !$conductor_id) {
        throw new Exception("Faltan datos obligatorios (Fecha, Guía, Bus o Conductor).");
    }

    // --- VALIDACIONES DE REGLA DE NEGOCIO ---

    // 1. Validar N° de Guía Único
    $stmtCheckGuia = $pdo->prepare("
        SELECT b.numero_maquina, p.fecha 
        FROM produccion_buses p 
        JOIN buses b ON p.bus_id = b.id 
        WHERE p.nro_guia = ? AND p.id != ? 
        LIMIT 1
    ");
    $stmtCheckGuia->execute([$nro_guia, $guia_id]);
    $existing = $stmtCheckGuia->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $fechaEx = date('d/m/Y', strtotime($existing['fecha']));
        throw new Exception("El N° de Guía '{$nro_guia}' ya está registrado para el Bus N° {$existing['numero_maquina']} con fecha {$fechaEx}.");
    }

    // 2. Validar Máximo 2 Ingresos por Bus en el Día
    $stmtCheckLimit = $pdo->prepare("SELECT COUNT(*) FROM produccion_buses WHERE bus_id = ? AND fecha = ? AND id != ?");
    $stmtCheckLimit->execute([$bus_id, $fecha, $guia_id]);
    if ($stmtCheckLimit->fetchColumn() >= 2) {
        throw new Exception("El Bus seleccionado ya tiene 2 ingresos registrados para la fecha {$fecha}. No se permiten más.");
    }

    $folios = $_POST['folios'] ?? []; // Array [tarifa => [inicio => x, fin => y]]

    $ingreso_bruto = 0;
    $items_pecera = [];

    foreach ($folios as $tarifa => $f) {
        $tarifa = (int)$tarifa;
        $inicio = (int)($f['inicio'] ?? 0);
        $fin = (int)($f['fin'] ?? 0);
        $cantidad = 0;
        $monto = 0;

        if ($fin > $inicio && $inicio > 0) {
            $cantidad = ($fin - $inicio);
            $monto = $cantidad * $tarifa;
            $ingreso_bruto += $monto;
        }

        $items_pecera[] = [
            'tarifa' => $tarifa,
            'inicio' => $inicio,
            'fin' => $fin,
            'cantidad' => $cantidad,
            'monto' => $monto
        ];
    }

    // 3. Procesar Gastos
    $g_admin = (int)($_POST['gasto_administracion'] ?? 0);
    $g_petro = (int)($_POST['gasto_petroleo'] ?? 0);
    $g_bolet = (int)($_POST['gasto_boletos'] ?? 0);
    $g_aseo  = (int)($_POST['gasto_aseo'] ?? 0);
    $g_viati = (int)($_POST['gasto_viatico'] ?? 0);
    $g_vario = (int)($_POST['gasto_varios'] ?? 0);
    $g_impo  = (int)($_POST['gasto_imposiciones'] ?? 0);

    $pago_conductor = round($ingreso_bruto * 0.22);

    // 4. Iniciar Transacción y Guardar
    $pdo->beginTransaction();

    if ($guia_id > 0) {
        // --- UPDATE MODE ---
        $sql = "UPDATE produccion_buses SET 
                bus_id=?, fecha=?, nro_guia=?, ingreso=?, conductor_id=?, 
                gasto_administracion=?, gasto_petroleo=?, gasto_boletos=?, gasto_aseo=?, gasto_viatico=?, gasto_varios=?, gasto_imposiciones=?, aporte_previsional=?,
                pago_conductor=?
                WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $bus_id,
            $fecha,
            $nro_guia,
            $ingreso_bruto,
            $conductor_id,
            $g_admin,
            $g_petro,
            $g_bolet,
            $g_aseo,
            $g_viati,
            $g_vario,
            $g_impo,
            $g_impo, // Mirror Imposiciones -> Aporte Previsional
            $pago_conductor,
            $guia_id
        ]);
        // Update successful, keep guia_id
    } else {
        // --- INSERT MODE ---
        $sql = "INSERT INTO produccion_buses 
                (bus_id, fecha, nro_guia, ingreso, conductor_id, 
                 gasto_administracion, gasto_petroleo, gasto_boletos, gasto_aseo, gasto_viatico, gasto_varios, gasto_imposiciones, aporte_previsional,
                 pago_conductor) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                 ingreso=VALUES(ingreso), conductor_id=VALUES(conductor_id),
                 gasto_administracion=VALUES(gasto_administracion), gasto_petroleo=VALUES(gasto_petroleo),
                 gasto_boletos=VALUES(gasto_boletos), gasto_aseo=VALUES(gasto_aseo),
                 gasto_viatico=VALUES(gasto_viatico), gasto_varios=VALUES(gasto_varios), gasto_imposiciones=VALUES(gasto_imposiciones), aporte_previsional=VALUES(aporte_previsional),
                 pago_conductor=VALUES(pago_conductor)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $bus_id,
            $fecha,
            $nro_guia,
            $ingreso_bruto,
            $conductor_id,
            $g_admin,
            $g_petro,
            $g_bolet,
            $g_aseo,
            $g_viati,
            $g_vario,
            $g_impo,
            $g_impo, // Mirror Imposiciones -> Aporte Previsional
            $pago_conductor
        ]);

        // Obtener ID
        $stmtId = $pdo->prepare("SELECT id FROM produccion_buses WHERE bus_id=? AND fecha=? AND nro_guia=?");
        $stmtId->execute([$bus_id, $fecha, $nro_guia]);
        $guia_id = $stmtId->fetchColumn();
    }

    // B. Guardar Detalles (Borrar previos y reinsertar)
    if ($guia_id) {
        $pdo->prepare("DELETE FROM produccion_detalle_boletos WHERE guia_id = ?")->execute([$guia_id]);

        $sqlDet = "INSERT INTO produccion_detalle_boletos (guia_id, tarifa, folio_inicio, folio_fin, cantidad, monto_total) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtDet = $pdo->prepare($sqlDet);

        foreach ($items_pecera as $item) {
            if ($item['fin'] > 0) {
                $stmtDet->execute([
                    $guia_id,
                    $item['tarifa'],
                    $item['inicio'],
                    $item['fin'],
                    $item['cantidad'],
                    $item['monto']
                ]);
            }
        }
    }

    $pdo->commit();

    // 5. Preparar Datos para Voucher
    $busObj = $pdo->query("SELECT patente, numero_maquina FROM buses WHERE id = $bus_id")->fetch();
    $empObj = $pdo->query("SELECT nombre FROM empleadores WHERE id = " . ($empleador_id ?: 0))->fetch(); // Use passed employers id if available, logic check needed?
    // Wait, on Update we don't always pass employer_id. Let's fetch it from BUS.
    if (!$empObj) {
        $empIdFetch = $pdo->query("SELECT empleador_id FROM buses WHERE id = $bus_id")->fetchColumn();
        $empObj = $pdo->query("SELECT nombre FROM empleadores WHERE id = $empIdFetch")->fetch();
    }

    $condObj = $pdo->query("SELECT nombre, rut FROM trabajadores WHERE id = $conductor_id")->fetch();

    $total_gastos = $g_admin + $g_petro + $g_bolet + $g_aseo + $g_viati + $g_vario + $g_impo;
    $liquido = $ingreso_bruto - $total_gastos;

    $responsedata = [
        'id' => $guia_id,
        'fecha' => date('d/m/Y', strtotime($fecha)),
        'hora' => date('H:i'),
        'nro_guia' => $nro_guia,
        'bus' => $busObj['numero_maquina'],
        'patente' => $busObj['patente'],
        'empleador' => $empObj['nombre'],
        'conductor' => $condObj['nombre'] . ' (' . $condObj['rut'] . ')',
        'ingreso' => number_format($ingreso_bruto, 0, ',', '.'),
        'prop_conductor' => number_format($pago_conductor, 0, ',', '.'),
        'liquido' => number_format($liquido, 0, ',', '.'),
        'empresa_fantasia' => NOMBRE_SISTEMA,
        'direccion_empresa' => 'Oficina Central',
        'pecera' => $items_pecera,
        'gastos' => [
            ['label' => 'Administración', 'value' => number_format($g_admin, 0, ',', '.')],
            ['label' => 'Petróleo', 'value' => number_format($g_petro, 0, ',', '.')],
            ['label' => 'Boletos', 'value' => number_format($g_bolet, 0, ',', '.')],
            ['label' => 'Aseo', 'value' => number_format($g_aseo, 0, ',', '.')],
            ['label' => 'Viático', 'value' => number_format($g_viati, 0, ',', '.')],
            ['label' => 'Varios', 'value' => number_format($g_vario, 0, ',', '.')],
            ['label' => 'Imposiciones', 'value' => number_format($g_impo, 0, ',', '.')]
        ]
    ];

    echo json_encode(['success' => true, 'data' => $responsedata]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
