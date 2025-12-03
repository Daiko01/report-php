<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $nombre = trim($_POST['nombre']);
    $rut = trim($_POST['rut']);
    $estado_previsional = trim($_POST['estado_previsional']);

    // Campos nuevos INP
    $sistema_previsional = $_POST['sistema_previsional'];
    // Importante: Recibimos "18.84" del form, lo guardamos como "0.1884"
    $tasa_inp = !empty($_POST['tasa_inp']) ? (float)$_POST['tasa_inp'] / 100 : 0;

    $afp_id = !empty($_POST['afp_id']) ? $_POST['afp_id'] : null;
    $sindicato_id = !empty($_POST['sindicato_id']) ? $_POST['sindicato_id'] : null;
    $tiene_cargas = isset($_POST['tiene_cargas']) ? 1 : 0;
    $numero_cargas = ($tiene_cargas == 1) ? (int)$_POST['numero_cargas'] : 0;

    if (!validarRUT($rut)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El RUT ingresado no es válido.'];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }
    $rut_formateado = formatearRUT($rut);

    if (!esUnico($pdo, $rut_formateado, 'trabajadores', 'rut')) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "El RUT ya está registrado."];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }

    // Si es AFP y Activo, debe tener AFP seleccionada
    if ($estado_previsional == 'Activo' && $sistema_previsional == 'AFP' && $afp_id == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Debe seleccionar una AFP."];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }

    if ($estado_previsional == 'Pensionado') {
        $afp_id = null;
        $tasa_inp = 0;
    }

    try {
        // INSERT con los campos de INP
        $sql = "INSERT INTO trabajadores (rut, nombre, sistema_previsional, tasa_inp_decimal, estado_previsional, afp_id, sindicato_id, tiene_cargas, numero_cargas) 
                VALUES (:rut, :nombre, :sistema, :tasa, :estado, :afp, :sindicato, :tiene, :numero)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_formateado,
            ':nombre' => $nombre,
            ':sistema' => $sistema_previsional,
            ':tasa' => $tasa_inp,
            ':estado' => $estado_previsional,
            ':afp' => $afp_id,
            ':sindicato' => $sindicato_id,
            ':tiene' => $tiene_cargas,
            ':numero' => $numero_cargas
        ]);

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Trabajador creado exitosamente!'];
        header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error BD: ' . $e->getMessage()];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }
}
