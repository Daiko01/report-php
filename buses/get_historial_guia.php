<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Check Role
$role = $_SESSION['user_role'] ?? '';
$isAdmin = ($role === 'admin');

$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT 
        p.id, 
        p.fecha, 
        p.nro_guia, 
        b.numero_maquina,
        e.nombre as empleador,
        t.nombre as conductor, 
        p.ingreso,
        (p.ingreso - (p.gasto_administracion + p.gasto_petroleo + p.gasto_boletos + p.gasto_aseo + p.gasto_viatico + p.gasto_varios)) as liquido
    FROM produccion_buses p
    JOIN buses b ON p.bus_id = b.id
    JOIN empleadores e ON b.empleador_id = e.id
    LEFT JOIN trabajadores t ON p.conductor_id = t.id
    WHERE DATE(p.fecha) = :fecha
    ORDER BY p.id DESC
");
$stmt->execute(['fecha' => $fechaFiltro]);
$guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$guias) {
    exit;
}

foreach ($guias as $g) {
    echo "<tr>";
    echo "<td class='text-start'>" . date('d/m', strtotime($g['fecha'])) . "</td>";
    echo "<td class='fw-bold text-start'># " . $g['nro_guia'] . "</td>";
    echo "<td class='text-start'>N&deg; " . $g['numero_maquina'] . "</td>";

    // Shorten Employer Name
    $empName = htmlspecialchars($g['empleador']);
    if (strlen($empName) > 15) {
        $empName = substr($empName, 0, 15) . '...';
    }
    echo "<td class='small text-start text-muted'>" . $empName . "</td>";

    $nameParts = explode(' ', $g['conductor'] ?? 'Sin Asignar');
    $shortName = $nameParts[0] . ' ' . ($nameParts[1] ?? '');
    echo "<td class='small text-start'>" . htmlspecialchars($shortName) . "</td>";
    echo "<td class='text-success text-start'>$ " . number_format($g['ingreso'], 0, ',', '.') . "</td>";
    echo "<td class='fw-bold text-start'>$ " . number_format($g['liquido'], 0, ',', '.') . "</td>";
    echo "<td>
            <button class='btn btn-xs btn-outline-info' onclick='reprint(${g['id']})' title='Reimprimir'><i class='fas fa-print'></i></button>
            <a href='editar_guia.php?id=${g['id']}' class='btn btn-xs btn-outline-warning' title='Editar'><i class='fas fa-pen'></i></a>";

    if ($isAdmin) {
        echo " <button class='btn btn-xs btn-outline-danger' onclick='eliminarGuia(${g['id']})' title='Eliminar'><i class='fas fa-trash'></i></button>";
    }

    echo "</td>";
    echo "</tr>";
}
