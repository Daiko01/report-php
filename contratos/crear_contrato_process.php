<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';



// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: gestionar_contratos.php');
    exit;
}

// 1. Recoger datos del formulario
$trabajador_id = (int)$_POST['trabajador_id'];
$empleador_id = (int)$_POST['empleador_id'];
$tipo_contrato = $_POST['tipo_contrato'];
$sueldo_imponible = (int)$_POST['sueldo_imponible'];

// NUEVOS CAMPOS (Corrección de sintaxis aplicada)
$pacto_colacion = !empty($_POST['pacto_colacion']) ? (int)$_POST['pacto_colacion'] : 0;
$pacto_movilizacion = !empty($_POST['pacto_movilizacion']) ? (int)$_POST['pacto_movilizacion'] : 0;

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_termino = !empty($_POST['fecha_termino']) ? $_POST['fecha_termino'] : null;
$es_part_time = isset($_POST['es_part_time']) ? 1 : 0;

// --- 2. VALIDACIONES DE NEGOCIO ---

// Regla: Plazo Fijo
if ($tipo_contrato == 'Fijo') {
    if ($fecha_termino == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: Un contrato a Plazo Fijo DEBE tener una Fecha de Término.'];
        header('Location: crear_contrato.php');
        exit;
    }
    if ($fecha_termino <= $fecha_inicio) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: La fecha de término debe ser posterior a la fecha de inicio.'];
        header('Location: crear_contrato.php');
        exit;
    }
}

// Regla: Indefinido (Forzar fecha nula)
if ($tipo_contrato == 'Indefinido') {
    $fecha_termino = null;
}

// Regla: Solapamiento
// Buscamos TODOS los contratos activos del trabajador, independientemente del empleador
$sql_overlap = "SELECT id, empleador_id, tipo_contrato, fecha_inicio, fecha_termino 
                FROM contratos 
                WHERE trabajador_id = :tid 
                AND esta_finiquitado = 0";

$stmt_overlap = $pdo->prepare($sql_overlap);
$stmt_overlap->execute([':tid' => $trabajador_id]);
$contratos_existentes = $stmt_overlap->fetchAll(PDO::FETCH_ASSOC);

foreach ($contratos_existentes as $c) {
    // 1. Determinar si hay solapamiento de fechas
    // Normalizar fechas para comparación (NULL = infinito)
    $inicio_existente = $c['fecha_inicio'];
    $termino_existente = $c['fecha_termino']; // Puede ser NULL (Indefinido)

    // Lógica de Solapamiento:
    // (StartA <= EndB) and (EndA >= StartB)
    // Manejo de NULLs:
    // Si Termino es NULL, se considera infinito.

    $se_solapan = false;

    // Caso 1: Ambos tienen fecha de término definida
    if ($fecha_termino && $termino_existente) {
        if ($fecha_inicio <= $termino_existente && $fecha_termino >= $inicio_existente) {
            $se_solapan = true;
        }
    }
    // Caso 2: El nuevo es Indefinido (NULL)
    elseif (is_null($fecha_termino) && $termino_existente) {
        // Se solapa si el existente termina DESPUÉS o el mismo día que inicia el nuevo
        if ($termino_existente >= $fecha_inicio) {
            $se_solapan = true;
        }
    }
    // Caso 3: El existente es Indefinido (NULL)
    elseif ($fecha_termino && is_null($termino_existente)) {
        // Se solapa si el nuevo termina DESPUÉS o el mismo día que inicia el existente
        if ($fecha_termino >= $inicio_existente) {
            $se_solapan = true;
        }
    }
    // Caso 4: Ambos son Indefinidos (NULL) -> Siempre se solapan (desde el inicio del último)
    elseif (is_null($fecha_termino) && is_null($termino_existente)) {
        $se_solapan = true;
    }

    if ($se_solapan) {
        // REGLA STRICTA: NINGÚN CONTRATO PUEDE SOLAPARSE.
        // Se debe respetar la secuencialidad.

        $fecha_fin_msg = $termino_existente ? date('d-m-Y', strtotime($termino_existente)) : 'Indefinido';
        $fecha_inicio_msg = date('d-m-Y', strtotime($inicio_existente));

        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "Error: Las fechas se solapan con un contrato existente (Inicia: $fecha_inicio_msg, Termina: $fecha_fin_msg). El nuevo contrato debe comenzar después de la fecha de término del anterior."
        ];
        header('Location: crear_contrato.php');
        exit;
    }
}

// --- 3. GUARDAR EN BASE DE DATOS ---
try {
    $sql = "INSERT INTO contratos (
                trabajador_id, 
                empleador_id, 
                sueldo_imponible, 
                pacto_colacion, 
                pacto_movilizacion, 
                tipo_contrato, 
                fecha_inicio, 
                fecha_termino, 
                es_part_time
            ) VALUES (
                :tid, :eid, :sueldo, :col, :mov, :tipo, :inicio, :termino, :part_time
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tid' => $trabajador_id,
        ':eid' => $empleador_id,
        ':sueldo' => $sueldo_imponible,
        ':col' => $pacto_colacion,
        ':mov' => $pacto_movilizacion,
        ':tipo' => $tipo_contrato,
        ':inicio' => $fecha_inicio,
        ':termino' => $fecha_termino,
        ':part_time' => $es_part_time
    ]);




    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Contrato creado exitosamente!'];
    header('Location: gestionar_contratos.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de Base de Datos: ' . $e->getMessage()];
    header('Location: crear_contrato.php');
    exit;
}
