<?php
// buses/validar_importacion.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ACCIÓN: VALIDAR Y DETECTAR DUPLICADOS ---
    if ($action === 'validate') {
        if (!isset($_FILES['archivo_csv'])) {
            echo json_encode(['error' => true, 'message' => 'No se cargó archivo.']);
            exit;
        }

        $mes = (int)$_POST['mes'];
        $anio = (int)$_POST['anio'];
        $file = $_FILES['archivo_csv']['tmp_name'];
        $handle = fopen($file, "r");

        // Saltar cabecera
        fgetcsv($handle, 1000, ";");

        $rows = [];
        $validData = [];

        // 1. CACHE DE BUSES FILTRADOS POR SISTEMA ACTUAL
        $busesCache = [];
        $stmtB = $pdo->prepare("SELECT b.id, b.numero_maquina, e.nombre as empleador_nombre 
                               FROM buses b 
                               JOIN empleadores e ON b.empleador_id = e.id 
                               WHERE e.empresa_sistema_id = ?");
        $stmtB->execute([ID_EMPRESA_SISTEMA]);
        while ($b = $stmtB->fetch()) {
            $busesCache[trim($b['numero_maquina'])] = $b;
        }

        // 2. CACHE DE TRABAJADORES
        $trabajadoresCache = [];
        $stmtT = $pdo->query("SELECT id, rut, nombre FROM trabajadores");
        while ($t = $stmtT->fetch()) {
            $rutClean = str_replace(['.', ',', ' '], '', $t['rut']);
            $trabajadoresCache[$rutClean] = $t;
        }

        // 3. PROCESAMIENTO CSV
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            if (!is_numeric($data[0])) continue;
            if (count($data) < 11) continue;

            $col_dia = (int)trim($data[0]);
            $col_guia = trim($data[1]);
            $col_bus_nro = trim($data[2]);
            $col_conductor_raw = trim($data[11] ?? '');

            $fecha_sql = "$anio-" . str_pad($mes, 2, "0", STR_PAD_LEFT) . "-" . str_pad($col_dia, 2, "0", STR_PAD_LEFT);
            $busData = $busesCache[$col_bus_nro] ?? null;

            $status = 'ok';
            $message = 'Listo';

            if (!$busData) {
                $status = 'error';
                $message = 'Máquina no pertenece a ' . NOMBRE_SISTEMA;
            } else {
                // --- DETECCIÓN DE DUPLICADOS ---
                $check = $pdo->prepare("SELECT id FROM produccion_buses WHERE bus_id = ? AND nro_guia = ? AND fecha = ?");
                $check->execute([$busData['id'], $col_guia, $fecha_sql]);
                if ($check->fetch()) {
                    $status = 'warning';
                    $message = 'Ya existe: se sobrescribirá.';
                }
            }

            // Mapeo de valores numéricos
            $ingreso = (int)preg_replace('/\D/', '', $data[3]);
            $pago_conductor = $ingreso > 0 ? round($ingreso * 0.22) : 0;

            $rowObj = [
                'fecha' => $fecha_sql,
                'bus_csv' => $col_bus_nro,
                'bus_id' => $busData['id'] ?? null,
                'empleador_nombre' => $busData['empleador_nombre'] ?? null,
                'guia' => $col_guia,
                'ingreso' => number_format($ingreso, 0, ',', '.'),
                'gasto_administracion' => number_format((int)preg_replace('/\D/', '', $data[6]), 0, ',', '.'),
                'status' => $status,
                'message' => $message,
                'raw' => $data // Guardamos la fila completa para el paso final
            ];

            if ($status !== 'error') $validData[] = $rowObj;
            $rows[] = $rowObj;
        }
        fclose($handle);

        $temp_name = 'import_' . uniqid() . '.json';
        file_put_contents(sys_get_temp_dir() . '/' . $temp_name, json_encode($validData));

        echo json_encode(['rows' => $rows, 'temp_file' => $temp_name]);
        exit;
    }

    // --- ACCIÓN: GUARDADO FINAL (INSERT + UPDATE) ---
    if ($action === 'save') {
        $tempPath = sys_get_temp_dir() . '/' . $_POST['temp_file'];
        if (!file_exists($tempPath)) {
            echo json_encode(['success' => false, 'message' => 'Expiró el tiempo de espera.']);
            exit;
        }

        $importData = json_decode(file_get_contents($tempPath), true);
        $mes = (int)$_POST['mes'];
        $ano = (int)$_POST['anio'];

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO produccion_buses 
                (bus_id, fecha, nro_guia, ingreso, gasto_petroleo, gasto_boletos, gasto_administracion, gasto_aseo, gasto_viatico, gasto_varios, pago_conductor, aporte_previsional) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                ingreso=VALUES(ingreso), gasto_petroleo=VALUES(gasto_petroleo), gasto_administracion=VALUES(gasto_administracion), pago_conductor=VALUES(pago_conductor)");

            foreach ($importData as $row) {
                $d = $row['raw'];
                $stmt->execute([
                    $row['bus_id'],
                    $row['fecha'],
                    $d[1],
                    (int)preg_replace('/\D/', '', $d[3]), // ingreso
                    (int)preg_replace('/\D/', '', $d[4]), // petroleo
                    (int)preg_replace('/\D/', '', $d[5]), // boletos
                    (int)preg_replace('/\D/', '', $d[6]), // admin
                    (int)preg_replace('/\D/', '', $d[7]), // aseo
                    (int)preg_replace('/\D/', '', $d[8]), // viatico
                    (int)preg_replace('/\D/', '', $d[10]), // varios
                    (int)round((int)preg_replace('/\D/', '', $d[3]) * 0.22), // pago cond
                    (int)preg_replace('/\D/', '', $d[9]) // aportes
                ]);
            }

            $pdo->commit();
            unlink($tempPath);
            echo json_encode(['success' => true, 'message' => 'Producción importada y actualizada con éxito.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        }
        exit;
    }
}
