<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = $_POST['id'];
    $estado_previsional = trim($_POST['estado_previsional']);
    $sistema_previsional = $_POST['sistema_previsional'];
    $tasa_inp = !empty($_POST['tasa_inp']) ? (float)$_POST['tasa_inp'] / 100 : 0;

    $afp_id = !empty($_POST['afp_id']) ? $_POST['afp_id'] : null;
    $sindicato_id = !empty($_POST['sindicato_id']) ? $_POST['sindicato_id'] : null;
    $tiene_cargas = isset($_POST['tiene_cargas']) ? 1 : 0;
    $numero_cargas = ($tiene_cargas == 1) ? (int)$_POST['numero_cargas'] : 0;

    if ($estado_previsional == 'Activo' && $sistema_previsional == 'AFP' && $afp_id == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Debe seleccionar una AFP."];
        header('Location: ' . BASE_URL . '/maestros/editar_trabajador.php?id=' . $id);
        exit;
    }

    if ($estado_previsional == 'Pensionado') {
        $afp_id = null;
        $tasa_inp = 0;
    }

    try {
        $sql = "UPDATE trabajadores SET 
                    sistema_previsional = :sistema,
                    tasa_inp_decimal = :tasa,
                    estado_previsional = :estado, 
                    afp_id = :afp, 
                    sindicato_id = :sindicato, 
                    tiene_cargas = :tiene, 
                    numero_cargas = :numero
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
