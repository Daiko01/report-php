<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

// 1. Recoger datos
$contrato_id = (int)$_POST['contrato_id'];
$descripcion = trim($_POST['descripcion']);
$fecha_anexo = $_POST['fecha_anexo'];

// Campos opcionales
$nuevo_sueldo = !empty($_POST['nuevo_sueldo']) ? (int)$_POST['nuevo_sueldo'] : null;
$nueva_fecha_termino = !empty($_POST['nueva_fecha_termino']) ? $_POST['nueva_fecha_termino'] : null;
$nuevo_tipo_contrato = !empty($_POST['nuevo_tipo_contrato']) ? $_POST['nuevo_tipo_contrato'] : null;
// Part time es especial porque 0 es un valor válido
$nuevo_es_part_time = (isset($_POST['nuevo_es_part_time']) && $_POST['nuevo_es_part_time'] !== '') ? (int)$_POST['nuevo_es_part_time'] : null;

try {
    $pdo->beginTransaction();

    // 2. Guardar el registro del anexo
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

    // 3. Actualizar el contrato principal (Reflejar cambios vigentes)
    $updates = [];
    $params = [];

    if ($nuevo_sueldo !== null) {
        $updates[] = "sueldo_imponible = ?";
        $params[] = $nuevo_sueldo;
    }
    if ($nueva_fecha_termino !== null) {
        $updates[] = "fecha_termino = ?";
        $params[] = $nueva_fecha_termino;
    }
    if ($nuevo_tipo_contrato !== null) {
        $updates[] = "tipo_contrato = ?";
        $params[] = $nuevo_tipo_contrato;

        // Regla especial: Si pasa a Indefinido, borrar fecha término si no se especificó una nueva
        if ($nuevo_tipo_contrato == 'Indefinido' && $nueva_fecha_termino === null) {
            $updates[] = "fecha_termino = NULL";
        }
    }
    if ($nuevo_es_part_time !== null) {
        $updates[] = "es_part_time = ?";
        $params[] = $nuevo_es_part_time;
    }

    if (!empty($updates)) {
        $params[] = $contrato_id; // Para el WHERE
        $sql_update = "UPDATE contratos SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql_update)->execute($params);
    }

    $pdo->commit();

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Anexo agregado y contrato actualizado!'];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/editar_contrato.php?id=' . $contrato_id);
    exit;
}
