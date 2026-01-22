<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

$contrato_id = (int)$_POST['contrato_id'];
$descripcion = trim($_POST['descripcion']);
$fecha_anexo = $_POST['fecha_anexo'];

// --- CORRECCIÓN DE CAPTURA DE DATOS ---
// 1. Sueldo: NULL si está vacío
$nuevo_sueldo = !empty($_POST['nuevo_sueldo']) ? (int)$_POST['nuevo_sueldo'] : null;

// 2. Fecha Término: NULL si está vacío
$nueva_fecha_termino = !empty($_POST['nueva_fecha_termino']) ? $_POST['nueva_fecha_termino'] : null;

// 3. Tipo Contrato: NULL si está vacío
$nuevo_tipo_contrato = !empty($_POST['nuevo_tipo_contrato']) ? $_POST['nuevo_tipo_contrato'] : null;

// 4. Part Time: Aquí estaba el error. Debemos permitir el valor '0'.
// Usamos isset y !== '' para diferenciar entre "no enviado" y "valor 0"
$nuevo_es_part_time = (isset($_POST['nuevo_es_part_time']) && $_POST['nuevo_es_part_time'] !== '') ? (int)$_POST['nuevo_es_part_time'] : null;


try {
    $pdo->beginTransaction();

    // 1. Guardar el Anexo
    $sql_anexo = "INSERT INTO anexos_contrato (contrato_id, fecha_anexo, descripcion, nuevo_sueldo, nueva_fecha_termino, nuevo_tipo_contrato, nuevo_es_part_time)
                  VALUES (:cid, :fecha, :desc, :sueldo, :f_termino, :tipo, :part_time)";
    $stmt_anexo = $pdo->prepare($sql_anexo);
    $stmt_anexo->execute([
        ':cid' => $contrato_id,
        ':fecha' => $fecha_anexo,
        ':desc' => $descripcion,
        ':sueldo' => $nuevo_sueldo,
        ':f_termino' => $nueva_fecha_termino,
        ':tipo' => $nuevo_tipo_contrato,
        ':part_time' => $nuevo_es_part_time
    ]);

    // 2. Actualizar el Contrato Principal (Reflejar el estado actual)
    $updates = [];
    $params = [];

    if ($nuevo_sueldo !== null) {
        $updates[] = "sueldo_imponible = ?";
        $params[] = $nuevo_sueldo;
    }

    // Lógica especial para Tipo de Contrato
    if ($nuevo_tipo_contrato !== null) {
        $updates[] = "tipo_contrato = ?";
        $params[] = $nuevo_tipo_contrato;

        // Si cambia a Indefinido y NO se envió una nueva fecha, se asume NULL (sin fecha término)
        if ($nuevo_tipo_contrato == 'Indefinido' && $nueva_fecha_termino === null) {
            $updates[] = "fecha_termino = NULL";
        }
    }

    // Lógica de Fecha (tiene prioridad sobre la lógica automática de arriba)
    if ($nueva_fecha_termino !== null) {
        $updates[] = "fecha_termino = ?";
        $params[] = $nueva_fecha_termino;
    }

    // Lógica de Part Time (CORREGIDA: Ahora acepta 0)
    if ($nuevo_es_part_time !== null) {
        $updates[] = "es_part_time = ?";
        $params[] = $nuevo_es_part_time;
    }

    // Ejecutar UPDATE solo si hay cambios
    // Ejecutar UPDATE solo si hay cambios y la fecha del anexo es hoy o anterior
    // RESTAURADO: Se debe actualizar el contrato base para que el sistema refleje el estado actual en listados y perfiles.
    // La función obtener_datos_contrato_vigente se usa en calculos complejos, pero la UI depende de la tabla contratos.
    if (!empty($updates) && $fecha_anexo <= date('Y-m-d')) {
        $params[] = $contrato_id; // ID para el WHERE
        $sql_update = "UPDATE contratos SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql_update)->execute($params);
    }

    $pdo->commit();

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Anexo guardado y contrato actualizado correctamente!'];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
}
