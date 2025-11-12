<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $descuento = (int)$_POST['descuento'];

    try {
        $stmt = $pdo->prepare("UPDATE sindicatos SET descuento = :descuento WHERE id = :id");
        $stmt->execute([':descuento' => $descuento, ':id' => $id]);

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Descuento de sindicato actualizado!'];
        header('Location: ' . BASE_URL . '/maestros/gestionar_sindicatos.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        header('Location: ' . BASE_URL . '/maestros/editar_sindicato.php?id=' . $id);
        exit;
    }
}
