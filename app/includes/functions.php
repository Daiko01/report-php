<?php
// app/includes/functions.php

/**
 * Formatea un número como moneda o entero (sin decimales, con separador de miles)
 * Mismo comportamiento que la función original en ver_pdf.php
 */
function format_numero($num)
{
    if ($num === null || $num === 0 || $num === '') return "0";
    return number_format((int)$num, 0, ',', '.');
}

/**
 * Formatea un RUT chileno (XX.XXX.XXX-X)
 */
function format_rut($rut_str)
{
    if (empty($rut_str)) return "";
    // Limpiar puntos y guiones previos
    $rut_str = preg_replace('/[^0-9kK]/', '', $rut_str);

    // Separar cuerpo y verificador
    $verificador = substr($rut_str, -1);
    $cuerpo = substr($rut_str, 0, -1);

    if (empty($cuerpo)) return $rut_str;

    // Formatear cuerpo con puntos
    $cuerpo_formateado = number_format((int)$cuerpo, 0, ',', '.');

    return $cuerpo_formateado . '-' . $verificador;
}

/**
 * Devuelve el nombre del mes en español dado su número (1-12)
 */
function get_mes_nombre($numero_mes)
{
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
    return $meses[(int)$numero_mes] ?? '';
}

/**
 * Respuesta JSON estandarizada para AJAX
 */
function json_response($success, $message, $data = [])
{
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

/**
 * Log de errores personalizado (Wrapper para error_log)
 */
function log_system_error($message, $context = [])
{
    $log_msg = "[System Error] " . $message;
    if (!empty($context)) {
        $log_msg .= " Context: " . json_encode($context);
    }
    error_log($log_msg);
}

/**
 * Registrar acción en auditoría
 */
function log_audit($action, $description = '')
{
    global $pdo;
    if (!$pdo) return;

    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Calcular SIS (Seguro Invalidez y Sobrevivencia)
 */
function calcular_sis($sueldo_imponible, $tasa_decimal)
{
    return floor($sueldo_imponible * $tasa_decimal);
}

/**
 * Calcular Mutual de Seguridad
 */
function calcular_mutual($sueldo_imponible, $tasa_decimal)
{
    return floor($sueldo_imponible * $tasa_decimal);
}

/**
 * Calcular Seguro de Cesantía Patronal
 */
function calcular_cesantia_patronal($sueldo_imponible, $tipo_contrato, $estado_previsional, $cotiza_cesantia_pensionado = 0)
{
    $debe_pagar = false;

    // Normalizar estado
    $estado_previsional = ucfirst(strtolower($estado_previsional));

    if ($estado_previsional == 'Activo') {
        $debe_pagar = true;
    } elseif ($cotiza_cesantia_pensionado == 1) {
        $debe_pagar = true;
    }

    if (!$debe_pagar) return 0;

    if ($tipo_contrato == 'Fijo') {
        return floor($sueldo_imponible * 0.03); // 3.0%
    } else {
        return floor($sueldo_imponible * 0.024); // 2.4%
    }
}

/**
 * Obtener datos vigentes de un contrato (Sueldo, Tipo) considerando anexos en la fecha dada.
 * Retorna array con 'sueldo_imponible', 'tipo_contrato'.
 */
function obtener_datos_contrato_vigente($pdo, $contrato_id, $fecha_referencia)
{
    // 1. Obtener datos base
    $stmt = $pdo->prepare("SELECT sueldo_imponible, tipo_contrato, es_part_time FROM contratos WHERE id = ?");
    $stmt->execute([$contrato_id]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datos) return ['sueldo_imponible' => 0, 'tipo_contrato' => ''];

    // 2. Buscar TODOS los anexos vigentes a la fecha, ordenados cronológicamente
    $stmt_anexos = $pdo->prepare("
        SELECT nuevo_sueldo, nuevo_tipo_contrato, nuevo_es_part_time 
        FROM anexos_contrato
        WHERE contrato_id = ? AND fecha_anexo <= ? 
        ORDER BY fecha_anexo ASC
    ");
    $stmt_anexos->execute([$contrato_id, $fecha_referencia]);
    $anexos = $stmt_anexos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Aplicar cambios secuencialmente (Accumulator Pattern)
    // Esto permite que el Contrato Base tenga los valores ORIGINALES, y los anexos modifiquen lo que corresponda.
    foreach ($anexos as $anexo) {
        if (!empty($anexo['nuevo_sueldo'])) {
            $datos['sueldo_imponible'] = $anexo['nuevo_sueldo'];
        }
        if (!empty($anexo['nuevo_tipo_contrato'])) {
            $datos['tipo_contrato'] = $anexo['nuevo_tipo_contrato'];
        }
        // nuevo_es_part_time puede ser 0, verificar null/isset
        if (isset($anexo['nuevo_es_part_time'])) {
            $datos['es_part_time'] = $anexo['nuevo_es_part_time'];
        }
    }


    return $datos;
}
