<?php
// planillas/generar_planilla.php (MATCH DE RUT CORREGIDO Y ROBUSTO)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php';

// 1. Validar entrada
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['empleador_id']) || !isset($_POST['mes']) || !isset($_POST['ano'])) {
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}
$empleador_id = (int)$_POST['empleador_id'];
$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];

// 2. Verificar Cierre Mensual
$stmt_c = $pdo->prepare("SELECT esta_cerrado FROM cierres_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
$stmt_c->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
if ($stmt_c->fetchColumn()) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => "El período $mes/$ano está CERRADO. No se puede regenerar."];
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}

// 3. Verificar si la planilla YA EXISTE (Protección)
$stmt_check = $pdo->prepare("SELECT COUNT(id) FROM planillas_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
$stmt_check->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
if ($stmt_check->fetchColumn() > 0) {
    echo "<form id='redirectForm' action='cargar_grid.php' method='POST'>
            <input type='hidden' name='empleador_id' value='$empleador_id'>
            <input type='hidden' name='mes' value='$mes'>
            <input type='hidden' name='ano' value='$ano'>
          </form>
          <script type='text/javascript'>document.getElementById('redirectForm').submit();</script>";
    exit;
}

// 4. Definir rango
$primer_dia = "$ano-$mes-01";
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));

// 5. Buscar Contratos Vigentes
$sql_contratos = "SELECT c.*, t.rut as rut_trabajador 
                  FROM contratos c
                  JOIN trabajadores t ON c.trabajador_id = t.id
                  WHERE c.empleador_id = :eid
                  AND c.fecha_inicio <= :ultimo_dia
                  AND (c.fecha_termino IS NULL OR c.fecha_termino >= :primer_dia)
                  AND (c.fecha_finiquito IS NULL OR c.fecha_finiquito >= :primer_dia)
";

$stmt_contratos = $pdo->prepare($sql_contratos);
$stmt_contratos->execute([':eid' => $empleador_id, ':ultimo_dia' => $ultimo_dia, ':primer_dia' => $primer_dia]);
$contratos_vigentes = $stmt_contratos->fetchAll();

if (empty($contratos_vigentes)) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => "No se encontraron contratos vigentes."];
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}

// Aplicar lógica histórica de anexos
foreach ($contratos_vigentes as &$contrato) {
    $datos_historicos = obtener_datos_contrato_vigente($pdo, (int)$contrato['id'], $ultimo_dia);
    if ($datos_historicos) {
        $contrato['sueldo_imponible'] = $datos_historicos['sueldo_imponible'];
        $contrato['tipo_contrato'] = $datos_historicos['tipo_contrato'];
        $contrato['es_part_time'] = $datos_historicos['es_part_time'] ?? 0;
    }
}
unset($contrato);

$calculoService = new CalculoPlanillaService($pdo);

// --- AQUÍ ESTÁ LA CORRECCIÓN MAGISTRAL ---
// Usamos REPLACE en SQL para limpiar los puntos y guiones de la base de datos al vuelo.
// Así, si la BD tiene "12.345.678-9", se convierte temporalmente en "123456789" para compararlo.
$stmt_aporte_ext = $pdo->prepare("
    SELECT monto 
    FROM aportes_externos 
    WHERE REPLACE(REPLACE(rut_trabajador, '.', ''), '-', '') = :rut 
      AND mes = :mes 
      AND ano = :ano 
    LIMIT 1
");

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ?")->execute([$empleador_id, $mes, $ano]);

    $sql_insert = "INSERT INTO planillas_mensuales (mes, ano, empleador_id, trabajador_id, sueldo_imponible, tipo_contrato, fecha_inicio, fecha_termino, dias_trabajados, aportes, adicional_salud_apv, cesantia_licencia_medica, cotiza_cesantia_pensionado, descuento_afp, afp_historico_nombre, descuento_salud, seguro_cesantia, sindicato, asignacion_familiar_calculada, sueldo_base_snapshot, es_part_time_snapshot, tipo_jornada_snapshot) 
                   VALUES (:mes, :ano, :eid, :tid, :sueldo, :tipo, :f_inicio, :f_termino, :dias, :aportes, 0, 0, 0, :desc_afp, :afp_hist, :desc_salud, :desc_cesantia, :desc_sindicato, :desc_asig_fam, :s_snapshot, :pt_snapshot, :j_snapshot)";
    $stmt_insert = $pdo->prepare($sql_insert);

    foreach ($contratos_vigentes as $c) {

        // 7.1. Lógica de Días (Refactorizado con Service)
        $fecha_fin_efectiva = ($c['esta_finiquitado'] ?? false) ? $c['fecha_finiquito'] : $c['fecha_termino'];

        $dias_trabajados_bruto = $calculoService->calcularDiasTrabajados($c['fecha_inicio'], $fecha_fin_efectiva, $mes, $ano);

        // Calcular Licencias
        $dias_licencia = $calculoService->calcularDiasLicencia($c['trabajador_id'], $c['fecha_inicio'], $fecha_fin_efectiva, $mes, $ano);

        // Días efectivos = Brutos - Licencias
        $dias_trabajados = max(0, $dias_trabajados_bruto - $dias_licencia);

        if ($dias_trabajados <= 0) continue;

        $sueldo_proporcional = $calculoService->calcularProporcional($c['sueldo_imponible'], $dias_trabajados);

        // --- 7.2. MATCH DE APORTES ---

        // 1. Obtenemos el RUT del contrato (ej: 12.345.678-9)
        $rut_bd = $c['rut_trabajador'];

        // 2. Lo limpiamos completamente en PHP (ej: 123456789)
        $rut_limpio = str_replace(['.', '-'], '', $rut_bd);

        // 3. Buscamos. Como el SQL tiene REPLACE, encontrará el match aunque en la BD esté con puntos.
        $monto_aporte = 0;
        $stmt_aporte_ext->execute([
            ':rut' => $rut_limpio,
            ':mes' => $mes,
            ':ano' => $ano
        ]);
        $res_aporte = $stmt_aporte_ext->fetchColumn();

        if ($res_aporte) {
            $monto_aporte = (int)$res_aporte;
        }
        // ---------------------------

        $fila_temporal = [
            'trabajador_id' => $c['trabajador_id'],
            'sueldo_imponible' => $sueldo_proporcional,
            'dias_trabajados' => $dias_trabajados,
            'tipo_contrato' => $c['tipo_contrato'],
            'cotiza_cesantia_pensionado' => 0,
            'aportes' => $monto_aporte
        ];

        $calculos = $calculoService->calcularFila($fila_temporal, $mes, $ano);

        $stmt_insert->execute([
            ':mes' => $mes,
            ':ano' => $ano,
            ':eid' => $empleador_id,
            ':tid' => $c['trabajador_id'],
            ':sueldo' => $sueldo_proporcional,
            ':tipo' => $c['tipo_contrato'],
            ':f_inicio' => $c['fecha_inicio'],
            ':f_termino' => $c['fecha_termino'],
            ':dias' => $dias_trabajados,
            ':aportes' => $monto_aporte, // <-- AQUI ENTRA EL DINERO
            ':desc_afp' => $calculos['descuento_afp'],
            ':afp_hist' => $calculos['afp_historico_nombre'],
            ':desc_salud' => $calculos['descuento_salud'],
            ':desc_cesantia' => $calculos['seguro_cesantia'],
            ':desc_sindicato' => $calculos['sindicato'],
            ':desc_asig_fam' => $calculos['asignacion_familiar_calculada'],
            // Snapshots
            ':s_snapshot' => $sueldo_proporcional,
            ':pt_snapshot' => (int)($c['es_part_time'] ?? 0),
            ':j_snapshot' => ($c['es_part_time'] ?? 0) ? 'Part Time' : 'Full Time'
        ]);
    }

    $pdo->commit();

    echo "<form id='redirectForm' action='cargar_grid.php' method='POST'>
            <input type='hidden' name='empleador_id' value='$empleador_id'>
            <input type='hidden' name='mes' value='$mes'>
            <input type='hidden' name='ano' value='$ano'>
          </form>
          <script type='text/javascript'>document.getElementById('redirectForm').submit();</script>";
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}
