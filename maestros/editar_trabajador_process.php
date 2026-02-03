<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = (int)$_POST['id'];
    $estado_previsional = trim($_POST['estado_previsional']);
    $es_excedente = isset($_POST['es_excedente']) ? (int)$_POST['es_excedente'] : 0;
    $sistema_previsional = $_POST['sistema_previsional'];
    $tasa_inp = !empty($_POST['tasa_inp']) ? (float)$_POST['tasa_inp'] / 100 : 0;

    $afp_id = !empty($_POST['afp_id']) ? $_POST['afp_id'] : null;
    $sindicato_id = !empty($_POST['sindicato_id']) ? $_POST['sindicato_id'] : null;

    // Cargas y Tramo Manual
    $tiene_cargas = isset($_POST['tiene_cargas']) ? 1 : 0;
    $numero_cargas = ($tiene_cargas == 1) ? (int)$_POST['numero_cargas'] : 0;

    // Si tiene cargas y seleccionó un tramo, lo guardamos. Si no, NULL.
    $tramo_manual = ($tiene_cargas == 1 && !empty($_POST['tramo_manual'])) ? $_POST['tramo_manual'] : null;

    // Validaciones
    if ($estado_previsional == 'Activo' && $sistema_previsional == 'AFP' && $afp_id == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Debe seleccionar una AFP."];
        header('Location: ' . BASE_URL . '/maestros/editar_trabajador.php?id=' . $id);
        exit;
    }

    // Limpieza Lógica (Integridad)
    if ($es_excedente == 1) {
        $afp_id = null;
        $tasa_inp = 0;
        $sindicato_id = null;
        // Mantener sistema_previsional valido para enum
    } elseif ($estado_previsional == 'Pensionado') {
        $afp_id = null;
        $tasa_inp = 0;
    }

    try {
        // UPDATE ACTUALIZADO: Incluye 'tramo_asignacion_manual'
        $sql = "UPDATE trabajadores SET 
                    sistema_previsional = :sistema,
                    tasa_inp_decimal = :tasa,
                    estado_previsional = :estado, 
                    afp_id = :afp, 
                    sindicato_id = :sindicato, 
                    tiene_cargas = :tiene, 
                    numero_cargas = :numero,
                    tramo_asignacion_manual = :tramo,
                    es_excedente = :excedente
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sistema' => $sistema_previsional,
            ':tasa' => $tasa_inp,
            ':estado' => $estado_previsional,
            ':afp' => $afp_id,
            ':sindicato' => $sindicato_id,
            ':tiene' => $tiene_cargas,
            ':numero' => $numero_cargas,
            ':tramo' => $tramo_manual, // Guardamos el cambio
            ':excedente' => $es_excedente,
            ':id' => $id
        ]);

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Trabajador actualizado exitosamente!'];
        header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error BD: ' . $e->getMessage()];
        header('Location: ' . BASE_URL . '/maestros/editar_trabajador.php?id=' . $id);
        exit;
    }
}
