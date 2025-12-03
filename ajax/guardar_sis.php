<?php

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

require_once dirname(__DIR__) . '/app/includes/session_check.php';



if ($_SESSION['user_role'] != 'admin') {

    http_response_code(403);

    exit;

}



// Manejar tanto POST de formulario como JSON

$input = json_decode(file_get_contents('php://input'), true);

if ($input) {

    $_POST = $input; // Unificar entrada

}



$accion = $_POST['accion'] ?? '';

// Verificar CSRF para acciones de escritura
if ($accion == 'crear' || $accion == 'eliminar') {
    verify_csrf_token();
}



try {

    if ($accion == 'crear') {

        $mes = (int)$_POST['mes'];

        $ano = (int)$_POST['ano'];

        $tasa = (float)$_POST['tasa'] / 100;



        $stmt = $pdo->prepare("INSERT INTO sis_historico (mes_inicio, ano_inicio, tasa_sis_decimal) VALUES (?, ?, ?)");

        $stmt->execute([$mes, $ano, $tasa]);



        if (!$input) { // Si vino de formulario normal

            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Tasa SIS registrada.'];

            header('Location: ' . BASE_URL . '/admin/gestionar_sis.php');

        } else {

            echo json_encode(['success' => true]);

        }

    } elseif ($accion == 'eliminar') {

        $id = (int)$_POST['id'];

        $pdo->prepare("DELETE FROM sis_historico WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true]);

    }

} catch (Exception $e) {

    if ($input) echo json_encode(['success' => false, 'message' => $e->getMessage()]);

    else { /* Manejar error de form */

    }

}

