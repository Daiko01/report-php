<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

// 1. Recoger datos
$trabajador_id = (int)$_POST['trabajador_id'];
$empleador_id = (int)$_POST['empleador_id'];
$tipo_contrato = $_POST['tipo_contrato'];
$sueldo_imponible = (int)$_POST['sueldo_imponible'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_termino = !empty($_POST['fecha_termino']) ? $_POST['fecha_termino'] : null;
$es_part_time = isset($_POST['es_part_time']) ? 1 : 0;

// --- 2. VALIDACIONES DE REGLAS DE NEGOCIO ---

// Regla: Plazo Fijo
if ($tipo_contrato == 'Fijo' && $fecha_termino == null) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: Un contrato a Plazo Fijo DEBE tener una Fecha de Término.'];
    header('Location: ' . BASE_URL . '/contratos/crear_contrato.php');
    exit;
}

// Regla: Indefinido
if ($tipo_contrato == 'Indefinido' && $fecha_termino != null) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: Un contrato Indefinido NO DEBE tener Fecha de Término.'];
    header('Location: ' . BASE_URL . '/contratos/crear_contrato.php');
    exit;
}

// Regla: Part Time (Tu nueva regla)
if ($es_part_time == 1 && $tipo_contrato != 'Fijo') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: El Part-Time solo puede seleccionarse para contratos de Plazo Fijo.'];
    header('Location: ' . BASE_URL . '/contratos/crear_contrato.php');
    exit;
}

// Regla: Solapamiento (La más compleja)
$sql_overlap = "SELECT id FROM contratos 
                WHERE trabajador_id = :tid 
                AND empleador_id = :eid
                AND esta_finiquitado = 0
                AND (
                    -- El nuevo contrato empieza DENTRO de uno existente
                    (:inicio_nuevo BETWEEN fecha_inicio AND fecha_termino)
                    OR
                    -- El nuevo contrato termina DENTRO de uno existente
                    (:termino_nuevo BETWEEN fecha_inicio AND fecha_termino)
                    OR
                    -- El nuevo contrato ENVUELVE a uno existente
                    (:inicio_nuevo <= fecha_inicio AND :termino_nuevo >= fecha_termino)
                    OR
                    -- Casos con Indefinidos (NULL)
                    (fecha_termino IS NULL AND :termino_nuevo IS NULL) -- Dos indefinidos
                    OR
                    (fecha_termino IS NULL AND :inicio_nuevo <= fecha_termino) -- No, esto está mal
                    (fecha_termino IS NULL AND :termino_nuevo >= fecha_inicio) -- Nuevo termina después de inicio indefinido
                    OR
                    (:termino_nuevo IS NULL AND :inicio_nuevo <= fecha_termino) -- Nuevo indefinido empieza antes de fin de uno viejo
                )";

// Lógica de solapamiento simplificada (y correcta)
// Dos rangos [s1, e1] y [s2, e2] se solapan si (s1 <= e2) Y (s2 <= e1)
// Adaptado para NULLs (Indefinido):
$sql_overlap = "SELECT id FROM contratos 
                WHERE trabajador_id = :tid 
                AND empleador_id = :eid
                AND esta_finiquitado = 0
                AND (fecha_inicio <= :termino_nuevo OR :termino_nuevo IS NULL)
                AND (fecha_termino >= :inicio_nuevo OR fecha_termino IS NULL)";

$stmt_overlap = $pdo->prepare($sql_overlap);
$stmt_overlap->execute([
    ':tid' => $trabajador_id,
    ':eid' => $empleador_id,
    ':inicio_nuevo' => $fecha_inicio,
    ':termino_nuevo' => $fecha_termino
]);

if ($stmt_overlap->fetch()) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: El trabajador ya tiene un contrato activo con este empleador que se solapa con estas fechas.'];
    header('Location: ' . BASE_URL . '/contratos/crear_contrato.php');
    exit;
}

// --- 3. GUARDAR DATOS ---
try {
    $sql = "INSERT INTO contratos (trabajador_id, empleador_id, sueldo_imponible, tipo_contrato, fecha_inicio, fecha_termino, es_part_time)
            VALUES (:tid, :eid, :sueldo, :tipo, :inicio, :termino, :part_time)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tid' => $trabajador_id,
        ':eid' => $empleador_id,
        ':sueldo' => $sueldo_imponible,
        ':tipo' => $tipo_contrato,
        ':inicio' => $fecha_inicio,
        ':termino' => $fecha_termino,
        ':part_time' => $es_part_time
    ]);

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Contrato creado exitosamente!'];
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/crear_contrato.php');
    exit;
}
