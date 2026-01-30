<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Check Role
$role = $_SESSION['user_role'] ?? '';
$isAdmin = ($role === 'admin');

$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');
$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');

$sql = "
    SELECT 
        p.id, 
        p.fecha, 
        p.nro_guia, 
        b.numero_maquina,
        e.nombre as empleador,
        t.nombre as conductor, 
        p.ingreso,
        (p.ingreso - (p.gasto_administracion + p.gasto_petroleo + p.gasto_boletos + p.gasto_aseo + p.gasto_viatico + p.gasto_varios)) as liquido,
        p.estado,
        p.bus_id
    FROM produccion_buses p
    LEFT JOIN buses b ON p.bus_id = b.id
    LEFT JOIN empleadores e ON b.empleador_id = e.id
    LEFT JOIN trabajadores t ON p.conductor_id = t.id
    WHERE DATE(p.fecha) = :fecha
    ORDER BY p.id DESC
";

$params = ['fecha' => $fechaFiltro];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$guias) {
    exit;
}

foreach ($guias as $g) {
    // Check closure per row (efficient enough for small daily list) or could be in join. 
    // Let's do a quick check here or Use Join implies complexity with group by if multiple closures? 
    // Closure is 1 per bus/month.
    // Let's use a simple query inside loop or modify main query. 
    // Modifying main query is better but I didn't want to mess up the complex calc. 
    // Let's do it inside the loop for safety as it's only for one day display.

    $mes = date('n', strtotime($g['fecha']));
    $ano = date('Y', strtotime($g['fecha']));

    $estado = $g['estado'] ?? 'Abierto';

    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM cierres_maquinas WHERE bus_id = ? AND mes = ? AND anio = ? AND estado = 'Cerrado'");
    $stmtC->execute([$g['bus_id'], $mes, $ano]);
    $isMesCerrado = ($stmtC->fetchColumn() > 0);

    // Check Permissions for Reopening
    $canReopen = $isAdmin || ($role === 'contador');

    // Column: Checkbox for bulk action
    echo "<tr>";
    echo "<td class='text-center'>";
    if ($estado !== 'Cerrada' || $canReopen) {
        // Add data-status to help JS distinguish
        echo "<input type='checkbox' class='form-check-input guia-selector' value='{$g['id']}' data-status='{$estado}'>";
    }
    echo "</td>";

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

    // Column: Estado
    // $estado is already defined above in loop (fetched or defaulted)

    echo "<td class='text-center'>";
    if ($isMesCerrado) {
        echo "<span class='badge bg-danger shadow-sm'><i class='fas fa-lock me-1'></i> Mes Cerrado</span>";
    } elseif ($estado === 'Cerrada') {
        echo "<span class='badge bg-secondary'><i class='fas fa-lock me-1'></i> Cerrada</span>";
    } else {
        echo "<span class='badge bg-success'><i class='fas fa-check-circle me-1'></i> Abierto</span>";
    }
    echo "</td>";

    echo "<td>
            <button class='btn btn-xs btn-outline-info' onclick='reprint(${g['id']})' title='Reimprimir'><i class='fas fa-print'></i></button>
            <a href='editar_guia.php?id=${g['id']}' class='btn btn-xs btn-outline-warning' title='Editar'><i class='fas fa-pen'></i></a>";

    // Individual Action Buttons
    if (!$isMesCerrado) {
        if ($estado !== 'Cerrada') {
            echo " <button class='btn btn-xs btn-outline-secondary' onclick='cerrarGuiaIndividual(${g['id']})' title='Cerrar Guía'><i class='fas fa-lock'></i></button>";
        } elseif ($canReopen) {
            echo " <button class='btn btn-xs btn-outline-danger' onclick='reabrirGuiaIndividual(${g['id']})' title='Reabrir Guía'><i class='fas fa-unlock'></i></button>";
        }
    }

    if ($isAdmin) {
        echo " <button class='btn btn-xs btn-outline-danger' onclick='eliminarGuia(${g['id']})' title='Eliminar'><i class='fas fa-trash'></i></button>";
    }

    echo "</td>";
    echo "</tr>";
}
