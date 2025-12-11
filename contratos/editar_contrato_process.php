<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') { header('Location: gestionar_contratos.php'); exit; }

// 1. Recoger datos
$contrato_id = (int)$_POST['id'];
$tipo_contrato = $_POST['tipo_contrato'];
$sueldo_imponible = (int)$_POST['sueldo_imponible'];
$pacto_colacion = (int)$_POST['pacto_colacion'];
$pacto_movilizacion = (int)$_POST['pacto_movilizacion'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_termino = !empty($_POST['fecha_termino']) ? $_POST['fecha_termino'] : null;
$es_part_time = isset($_POST['es_part_time']) ? 1 : 0;

// 2. Validaciones Básicas
if ($tipo_contrato == 'Fijo' && $fecha_termino == null) {
     $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Contrato Fijo requiere fecha término'];
     header('Location: editar_contrato.php?id=' . $contrato_id); exit;
}

if ($tipo_contrato == 'Indefinido') {
    $fecha_termino = null;
}

// Obtener datos del contrato actual (trabajador y empleador) para validar
$stmt_current = $pdo->prepare("SELECT trabajador_id, empleador_id FROM contratos WHERE id = :id");
$stmt_current->execute([':id' => $contrato_id]);
$current_contract = $stmt_current->fetch(PDO::FETCH_ASSOC);

if (!$current_contract) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Contrato no encontrado.'];
    header('Location: gestionar_contratos.php'); exit;
}

$trabajador_id = $current_contract['trabajador_id'];
$empleador_id = $current_contract['empleador_id'];

// Regla: Solapamiento (Misma lógica que en creación, excluyendo el contrato actual)
$sql_overlap = "SELECT id, empleador_id, tipo_contrato, fecha_inicio, fecha_termino 
                FROM contratos 
                WHERE trabajador_id = :tid 
                AND id != :current_id 
                AND esta_finiquitado = 0";

$stmt_overlap = $pdo->prepare($sql_overlap);
$stmt_overlap->execute([':tid' => $trabajador_id, ':current_id' => $contrato_id]);
$contratos_existentes = $stmt_overlap->fetchAll(PDO::FETCH_ASSOC);

foreach ($contratos_existentes as $c) {
    $inicio_existente = $c['fecha_inicio'];
    $termino_existente = $c['fecha_termino'];
    
    $se_solapan = false;

    if ($fecha_termino && $termino_existente) {
        if ($fecha_inicio <= $termino_existente && $fecha_termino >= $inicio_existente) $se_solapan = true;
    }
    elseif (is_null($fecha_termino) && $termino_existente) {
        if ($termino_existente >= $fecha_inicio) $se_solapan = true;
    }
    elseif ($fecha_termino && is_null($termino_existente)) {
        if ($fecha_termino >= $inicio_existente) $se_solapan = true;
    }
    elseif (is_null($fecha_termino) && is_null($termino_existente)) {
        $se_solapan = true; 
    }

    if ($se_solapan) {
        // REGLA STRICTA: NINGÚN CONTRATO PUEDE SOLAPARSE.
        
        $fecha_fin_msg = $termino_existente ? date('d-m-Y', strtotime($termino_existente)) : 'Indefinido';
        $fecha_inicio_msg = date('d-m-Y', strtotime($inicio_existente));
        
        $_SESSION['flash_message'] = [
            'type' => 'error', 
            'message' => "Error: Las fechas se solapan con un contrato existente (Inicia: $fecha_inicio_msg, Termina: $fecha_fin_msg). El contrato debe comenzar después de la fecha de término del anterior."
        ];
        header('Location: editar_contrato.php?id=' . $contrato_id); exit;
    }
}

// 3. Guardar
try {
    $sql = "UPDATE contratos SET 
                sueldo_imponible = :sueldo,
                pacto_colacion = :col,
                pacto_movilizacion = :mov,
                tipo_contrato = :tipo,
                fecha_inicio = :inicio,
                fecha_termino = :termino,
                es_part_time = :part_time
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sueldo' => $sueldo_imponible,
        ':col' => $pacto_colacion,
        ':mov' => $pacto_movilizacion,
        ':tipo' => $tipo_contrato,
        ':inicio' => $fecha_inicio,
        ':termino' => $fecha_termino,
        ':part_time' => $es_part_time,
        ':id' => $contrato_id
    ]);
    
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Contrato actualizado exitosamente!'];
    header('Location: editar_contrato.php?id=' . $contrato_id); exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()];
    header('Location: editar_contrato.php?id=' . $contrato_id); exit;
}
?>