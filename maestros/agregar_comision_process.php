<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $afp_id = (int)$_POST['afp_id'];
    $mes_inicio = (int)$_POST['mes_inicio'];
    $ano_inicio = (int)$_POST['ano_inicio'];
    $tasa_porcentaje = (float)$_POST['tasa_porcentaje'];
    $comision_decimal = $tasa_porcentaje / 100.0;

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO afp_comisiones_historicas (afp_id, mes_inicio, ano_inicio, comision_decimal) 
             VALUES (:afp_id, :mes, :ano, :tasa)"
        );
        $stmt->execute([
            ':afp_id' => $afp_id,
            ':mes' => $mes_inicio,
            ':ano' => $ano_inicio,
            ':tasa' => $comision_decimal
        ]);

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Nueva tasa agregada al historial!'];
        header('Location: ' . BASE_URL . '/maestros/gestionar_comisiones_afp.php?id=' . $afp_id);
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        header('Location: ' . BASE_URL . '/maestros/gestionar_comisiones_afp.php?id=' . $afp_id);
        exit;
    }
}
