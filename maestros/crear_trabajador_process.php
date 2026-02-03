<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/lib/utils.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $nombre = trim($_POST['nombre']);
    $rut = trim($_POST['rut']);
    $estado_previsional = trim($_POST['estado_previsional']);
    $es_excedente = isset($_POST['es_excedente']) ? (int)$_POST['es_excedente'] : 0; // Default 0

    // Campos Previsionales
    $sistema_previsional = $_POST['sistema_previsional'];
    $tasa_inp = !empty($_POST['tasa_inp']) ? (float)$_POST['tasa_inp'] / 100 : 0;

    $afp_id = !empty($_POST['afp_id']) ? $_POST['afp_id'] : null;
    $sindicato_id = !empty($_POST['sindicato_id']) ? $_POST['sindicato_id'] : null;

    // Campos de Cargas
    $tiene_cargas = isset($_POST['tiene_cargas']) ? 1 : 0;
    $numero_cargas = ($tiene_cargas == 1) ? (int)$_POST['numero_cargas'] : 0;

    // --- NUEVO: Capturar Tramo Manual ---
    // Si tiene cargas y seleccionó un tramo, lo guardamos. Si seleccionó "Automático" (vacío), guardamos NULL.
    $tramo_manual = ($tiene_cargas == 1 && !empty($_POST['tramo_manual'])) ? $_POST['tramo_manual'] : null;

    // Validaciones
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

    if ($estado_previsional == 'Activo' && $sistema_previsional == 'AFP' && $afp_id == null) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Debe seleccionar una AFP."];
        header('Location: ' . BASE_URL . '/maestros/crear_trabajador.php');
        exit;
    }

    // Limpieza lógica
    if ($es_excedente == 1) {
        $estado_previsional = 'Activo'; // Mantener como activo visualmente o crear estado 'Exento'? 
        // User dijo: "Opción recomendada: Agregar flag booleano para no romper procesos que dependan del estado 'Activo'"
        // Asi que lo dejamos como viene (probablemente 'Activo' hidden o disabled)
        // FORCE NULLs
        $afp_id = null;
        $tasa_inp = 0;
        $sindicato_id = null;
        $sistema_previsional = 'AFP'; // Dummy value to satisfy ENUM/NOT NULL if exists, or NULL if allowed. Worker has ENUM('AFP','INP').
    } elseif ($estado_previsional == 'Pensionado') {
        $afp_id = null;
        $tasa_inp = 0;
    }

    try {
        // INSERT ACTUALIZADO: Incluye 'tramo_asignacion_manual'
        $sql = "INSERT INTO trabajadores (
                    rut, nombre, sistema_previsional, tasa_inp_decimal, estado_previsional, 
                    afp_id, sindicato_id, tiene_cargas, numero_cargas, tramo_asignacion_manual, es_excedente
                ) VALUES (
                    :rut, :nombre, :sistema, :tasa, :estado, 
                    :afp, :sindicato, :tiene, :numero, :tramo, :excedente
                )";

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
            ':numero' => $numero_cargas,
            ':numero' => $numero_cargas,
            ':tramo' => $tramo_manual, // Guardamos el valor (A, B, C, D o NULL)
            ':excedente' => $es_excedente
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
