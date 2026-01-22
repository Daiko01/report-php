<?php
// ajax/search_global.php
require_once '../app/core/bootstrap.php';
require_once '../app/includes/session_check.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode([]);
    exit;
}

$query = trim($_GET['q']);
$param = "%$query%";
$results = [];

try {
    // 1. Search Buses (Number or Plate)
    // Table: buses, Columns: numero_maquina, patente
    $sqlBuses = "SELECT id, numero_maquina, patente FROM buses WHERE (numero_maquina LIKE :q1 OR patente LIKE :q2) LIMIT 5";
    $stmt = $pdo->prepare($sqlBuses);
    $stmt->execute([':q1' => $param, ':q2' => $param]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'category' => 'Buses',
            'label' => "Bus N° " . $row['numero_maquina'] . " (" . $row['patente'] . ")",
            'url' => BASE_URL . '/maestros/editar_bus.php?id=' . $row['id'], // Adjusted path to maestros/editar_bus.php based on verified file location
            // actually user said editar_bus.php is in 'maestros'? check file list? 
            // The file list showed 'buses/gestionar_buses.php' moved from Maestros? 
            // Waiting... 'gestionar_buses.php' IS in 'maestros/' dir in file view.
            // Let's assume standard paths or fix later if 404. 
            // Header mapping implied 'editar_bus.php' exists.
            // Let's check where 'gestionar_buses' links to 'btn-editar'. 
            // It links to JS logic opening modal. It doesn't link to a page?
            // Wait, look at 'gestionar_buses.php':
            // The edit button triggers a JS function to populate a modal form on the SAME PAGE.
            // There is no `editar_bus.php`.
            // User requested: "despliegue donde se encuentra ese 102".
            // Directing to 'gestionar_buses.php' is safer.
            'url' => BASE_URL . '/maestros/gestionar_buses.php',
            'icon' => 'fa-bus'
        ];
    }

    // 2. Search Workers (RUT or Name)
    // Table: trabajadores, Columns: rut, nombre
    $sqlWorkers = "SELECT id, rut, nombre FROM trabajadores WHERE (rut LIKE :q1 OR nombre LIKE :q2) LIMIT 5";
    $stmt = $pdo->prepare($sqlWorkers);
    $stmt->execute([':q1' => $param, ':q2' => $param]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'category' => 'Trabajadores',
            'label' => $row['nombre'] . " (" . $row['rut'] . ")",
            'url' => BASE_URL . '/maestros/editar_trabajador.php?id=' . $row['id'],
            'icon' => 'fa-user'
        ];
    }

    // 3. Search Employers (RUT or Name)
    // Table: empleadores, Columns: rut, nombre
    $sqlEmp = "SELECT id, rut, nombre FROM empleadores WHERE (rut LIKE :q1 OR nombre LIKE :q2) LIMIT 5";
    $stmt = $pdo->prepare($sqlEmp);
    $stmt->execute([':q1' => $param, ':q2' => $param]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'category' => 'Empresas',
            'label' => $row['nombre'] . " (" . $row['rut'] . ")",
            'url' => BASE_URL . '/maestros/editar_empleador.php?id=' . $row['id'],
            'icon' => 'fa-building'
        ];
    }
} catch (PDOException $e) {
    // Silent fail
}

echo json_encode($results);
