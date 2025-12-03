<?php
// planillas/generar_planilla.php (MATCH DE RUT CORREGIDO Y ROBUSTO)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php';

// 1. Validar entrada
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['empleador_id']) || !isset($_POST['mes']) || !isset($_POST['ano'])) {
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php'); exit;
}
$empleador_id = (int)$_POST['empleador_id'];
$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];

// 2. Verificar Cierre Mensual
$stmt_c = $pdo->prepare("SELECT esta_cerrado FROM cierres_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
$stmt_c->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
if ($stmt_c->fetchColumn()) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => "El período $mes/$ano está CERRADO. No se puede regenerar."];
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php'); exit;
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
                  AND (
                      (c.esta_finiquitado = 0 AND c.fecha_termino IS NULL) OR
                      (c.esta_finiquitado = 0 AND c.fecha_termino >= :primer_dia) OR
                      (c.esta_finiquitado = 1 AND c.fecha_finiquito >= :primer_dia AND c.fecha_finiquito <= :ultimo_dia)
                  )";
                  
$stmt_contratos = $pdo->prepare($sql_contratos);
$stmt_contratos->execute([':eid' => $empleador_id, ':ultimo_dia' => $ultimo_dia, ':primer_dia' => $primer_dia]);
$contratos_vigentes = $stmt_contratos->fetchAll();

if (empty($contratos_vigentes)) {
     $_SESSION['flash_message'] = ['type' => 'warning', 'message' => "No se encontraron contratos vigentes."];
     header('Location: ' . BASE_URL . '/planillas/cargar_selector.php'); exit;
}

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

    $sql_insert = "INSERT INTO planillas_mensuales (mes, ano, empleador_id, trabajador_id, sueldo_imponible, tipo_contrato, fecha_inicio, fecha_termino, dias_trabajados, aportes, adicional_salud_apv, cesantia_licencia_medica, cotiza_cesantia_pensionado, descuento_afp, descuento_salud, seguro_cesantia, sindicato, asignacion_familiar_calculada) 
                   VALUES (:mes, :ano, :eid, :tid, :sueldo, :tipo, :f_inicio, :f_termino, :dias, :aportes, 0, 0, 0, :desc_afp, :desc_salud, :desc_cesantia, :desc_sindicato, :desc_asig_fam)";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    foreach ($contratos_vigentes as $c) {
        
        // 7.1. Lógica de Días
        $fecha_inicio_contrato = strtotime($c['fecha_inicio']);
        $fecha_inicio_mes = strtotime($primer_dia);
        $fecha_fin_mes = strtotime($ultimo_dia);
        $fecha_fin_real = null;
        if ($c['esta_finiquitado']) $fecha_fin_real = strtotime($c['fecha_finiquito']);
        elseif ($c['fecha_termino']) $fecha_fin_real = strtotime($c['fecha_termino']);

        $dia_inicio = max($fecha_inicio_contrato, $fecha_inicio_mes);
        $dia_fin = $fecha_fin_real ? min($fecha_fin_real, $fecha_fin_mes) : $fecha_fin_mes;
        
        $dias_trabajados = 0;
        if ($dia_fin >= $dia_inicio) $dias_trabajados = (int)round(($dia_fin - $dia_inicio) / (60 * 60 * 24)) + 1;
        if ($dias_trabajados > 30) $dias_trabajados = 30;
        if ($dias_trabajados <= 0) continue;

        $sueldo_proporcional = ($dias_trabajados == 30) ? $c['sueldo_imponible'] : round(($c['sueldo_imponible'] / 30) * $dias_trabajados);

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
            ':mes' => $mes, ':ano' => $ano, ':eid' => $empleador_id, ':tid' => $c['trabajador_id'],
            ':sueldo' => $sueldo_proporcional, ':tipo' => $c['tipo_contrato'], ':f_inicio' => $c['fecha_inicio'],
            ':f_termino' => $c['fecha_termino'], ':dias' => $dias_trabajados, 
            ':aportes' => $monto_aporte, // <-- AQUI ENTRA EL DINERO
            ':desc_afp' => $calculos['descuento_afp'], ':desc_salud' => $calculos['descuento_salud'],
            ':desc_cesantia' => $calculos['seguro_cesantia'], ':desc_sindicato' => $calculos['sindicato'],
            ':desc_asig_fam' => $calculos['asignacion_familiar_calculada']
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
?>