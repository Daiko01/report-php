<?php
// ajax/get_notifications.php
require_once '../app/core/bootstrap.php';

header('Content-Type: application/json');

// Fecha hoy y límite 5 días
$hoy = date('Y-m-d');
$limite = date('Y-m-d', strtotime('+5 days'));

$notifications = [];

try {
    // 1. CONTRATOS POR VENCER (Próximos 5 días)
    $sqlData = "SELECT c.id, c.fecha_termino, t.nombre 
                FROM contratos c
                JOIN trabajadores t ON c.trabajador_id = t.id
                WHERE c.fecha_termino BETWEEN :hoy AND :limite 
                AND (c.esta_finiquitado = 0 OR c.esta_finiquitado IS NULL)
                ORDER BY c.fecha_termino ASC";

    $stmt = $pdo->prepare($sqlData);
    $stmt->execute([':hoy' => $hoy, ':limite' => $limite]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fecha_termino = new DateTime($row['fecha_termino']);
        $fecha_hoy = new DateTime($hoy);

        $interval = $fecha_hoy->diff($fecha_termino);
        $dias = $interval->days;
        $mensaje_dias = ($dias == 0) ? "Vence HOY" : "Vence en $dias días";

        $notifications[] = [
            'type' => 'contrato',
            'id' => $row['id'],
            'mensaje' => "Contrato de " . $row['nombre'],
            'subtexto' => $mensaje_dias,
            'fecha' => date('d/m/Y', strtotime($row['fecha_termino'])),
            'url' => BASE_URL . '/contratos/editar_contrato.php?id=' . $row['id']
        ];
    }

    // 2. LICENCIAS MÉDICAS QUE TERMINAN MAÑANA
    // Fecha objetivo: Mañana
    $manana = date('Y-m-d', strtotime('+1 day'));

    $sqlLic = "SELECT l.id, l.folio, t.nombre 
               FROM trabajador_licencias l
               JOIN trabajadores t ON l.trabajador_id = t.id
               WHERE l.fecha_fin = :manana";

    $stmtLic = $pdo->prepare($sqlLic);
    $stmtLic->execute([':manana' => $manana]);

    while ($row = $stmtLic->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'type' => 'licencia',
            'id' => $row['id'],
            'mensaje' => "Licencia de " . $row['nombre'],
            'subtexto' => "Termina MAÑANA",
            'fecha' => date('d/m/Y', strtotime($manana)),
            'url' => BASE_URL . '/maestros/gestionar_licencias.php'
        ];
    }
} catch (PDOException $e) {
    // error_log($e->getMessage());
}

echo json_encode(['count' => count($notifications), 'items' => $notifications]);
