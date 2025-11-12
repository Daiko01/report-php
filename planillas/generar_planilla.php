<?php
// planillas/generar_planilla.php (Versión corregida)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/CalculoPlanillaService.php'; // Usamos el servicio

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

// --- INICIO CORRECCIÓN BUGS 1 y 2 ---
// 3. Verificar si la planilla YA EXISTE
$stmt_check = $pdo->prepare("SELECT COUNT(id) FROM planillas_mensuales WHERE empleador_id = :eid AND mes = :mes AND ano = :ano");
$stmt_check->execute(['eid' => $empleador_id, 'mes' => $mes, 'ano' => $ano]);
$planilla_existe = $stmt_check->fetchColumn() > 0;

if ($planilla_existe) {
    // ¡La planilla ya existe! No regenerar. Solo redirigir a la grilla.
    // Esto protege los Aportes/APV que el usuario guardó.
    echo "<form id='redirectForm' action='cargar_grid.php' method='POST'>
            <input type='hidden' name='empleador_id' value='$empleador_id'>
            <input type='hidden' name='mes' value='$mes'>
            <input type='hidden' name='ano' value='$ano'>
          </form>
          <script type='text/javascript'>document.getElementById('redirectForm').submit();</script>";
    exit;
}
// --- FIN CORRECCIÓN BUGS 1 y 2 ---


// 4. Definir rango del período
$primer_dia = "$ano-$mes-01";
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));

// --- INICIO CORRECCIÓN BUG 3 (FINIQUITO) ---
// 5. Lógica de Búsqueda (CORREGIDA)
$sql_contratos = "SELECT * FROM contratos 
                  WHERE empleador_id = :eid
                  AND fecha_inicio <= :ultimo_dia -- El contrato debe haber comenzado
                  AND (
                      -- Caso 1: Contrato indefinido y no finiquitado
                      (esta_finiquitado = 0 AND fecha_termino IS NULL)
                      OR
                      -- Caso 2: Contrato a plazo fijo, no finiquitado, y vigente en el mes
                      (esta_finiquitado = 0 AND fecha_termino >= :primer_dia)
                      OR
                      -- Caso 3: Contrato finiquitado ESTE MES
                      (esta_finiquitado = 1 AND fecha_finiquito >= :primer_dia AND fecha_finiquito <= :ultimo_dia)
                  )";
// --- FIN CORRECCIÓN BUG 3 ---

$stmt_contratos = $pdo->prepare($sql_contratos);
$stmt_contratos->execute([
    ':eid' => $empleador_id,
    ':ultimo_dia' => $ultimo_dia,
    ':primer_dia' => $primer_dia
]);
$contratos_vigentes = $stmt_contratos->fetchAll();

if (empty($contratos_vigentes)) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => "No se encontraron contratos vigentes para este período."];
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}

// 6. Instanciar el servicio
$calculoService = new CalculoPlanillaService($pdo);

$pdo->beginTransaction();
try {
    // 7. Borrar planilla existente (por si acaso, aunque la lógica de arriba ya lo chequea)
    $pdo->prepare("DELETE FROM planillas_mensuales WHERE empleador_id = ? AND mes = ? AND ano = ?")
        ->execute([$empleador_id, $mes, $ano]);

    // 8. Preparar INSERT
    $sql_insert = "INSERT INTO planillas_mensuales (mes, ano, empleador_id, trabajador_id, sueldo_imponible, tipo_contrato, fecha_inicio, fecha_termino, dias_trabajados, aportes, adicional_salud_apv, cesantia_licencia_medica, cotiza_cesantia_pensionado, descuento_afp, descuento_salud, seguro_cesantia, sindicato, asignacion_familiar_calculada) 
                   VALUES (:mes, :ano, :eid, :tid, :sueldo, :tipo, :f_inicio, :f_termino, :dias, 0, 0, 0, 0, :desc_afp, :desc_salud, :desc_cesantia, :desc_sindicato, :desc_asig_fam)";
    $stmt_insert = $pdo->prepare($sql_insert);

    // 9. Recorrer contratos y generar filas
    foreach ($contratos_vigentes as $c) {

        // 9.1. Cálculo de Días y Sueldo Proporcional (CORREGIDO)
        $fecha_inicio_contrato = strtotime($c['fecha_inicio']);
        $fecha_inicio_mes = strtotime($primer_dia);
        $fecha_fin_mes = strtotime($ultimo_dia);

        // Determinar el fin del contrato (lo que ocurra primero)
        $fecha_fin_real = null;
        if ($c['esta_finiquitado']) {
            $fecha_fin_real = strtotime($c['fecha_finiquito']);
        } elseif ($c['fecha_termino']) {
            $fecha_fin_real = strtotime($c['fecha_termino']);
        }

        $dia_inicio_calculo = max($fecha_inicio_contrato, $fecha_inicio_mes);
        $dia_fin_calculo = $fecha_fin_mes;
        if ($fecha_fin_real !== null) {
            $dia_fin_calculo = min($fecha_fin_real, $fecha_fin_mes);
        }

        $dias_trabajados = 0;
        if ($dia_fin_calculo >= $dia_inicio_calculo) {
            $dias = ($dia_fin_calculo - $dia_inicio_calculo) / (60 * 60 * 24);
            $dias_trabajados = (int)round($dias) + 1;
        }
        if ($dias_trabajados > 30) $dias_trabajados = 30;

        $sueldo_proporcional = ($dias_trabajados == 30) ? $c['sueldo_imponible'] : round(($c['sueldo_imponible'] / 30) * $dias_trabajados);

        // 9.2. Crear fila temporal para el servicio de cálculo
        $fila_temporal = [
            'trabajador_id' => $c['trabajador_id'],
            'sueldo_imponible' => $sueldo_proporcional,
            'dias_trabajados' => $dias_trabajados,
            'tipo_contrato' => $c['tipo_contrato'],
            'cotiza_cesantia_pensionado' => 0 // Asumir 0 al generar
        ];

        // 9.3. LLAMAR AL SERVICIO DE CÁLCULO
        $calculos = $calculoService->calcularFila($fila_temporal, $mes, $ano);

        // 9.4. Insertar la fila pre-calculada
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
            // (Se insertan 0 en los campos variables)
            ':desc_afp' => $calculos['descuento_afp'],
            ':desc_salud' => $calculos['descuento_salud'],
            ':desc_cesantia' => $calculos['seguro_cesantia'],
            ':desc_sindicato' => $calculos['sindicato'],
            ':desc_asig_fam' => $calculos['asignacion_familiar_calculada']
        ]);
    }

    $pdo->commit();

    // 10. Redirigir a la Grilla para revisión
    echo "<form id='redirectForm' action='cargar_grid.php' method='POST'>
            <input type='hidden' name='empleador_id' value='$empleador_id'>
            <input type='hidden' name='mes' value='$mes'>
            <input type='hidden' name='ano' value='$ano'>
          </form>
          <script type='text/javascript'>document.getElementById('redirectForm').submit();</script>";
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error al generar la planilla: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . '/planillas/cargar_selector.php');
    exit;
}
