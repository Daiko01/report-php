<?php
// ajax/get_notifications.php
require_once '../app/core/bootstrap.php';
require_once '../app/includes/session_check.php';

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
    // 3. (NUEVO) RECORDATORIO CIERRE REMUNERACIONES (Solo Admin, fin de mes)
    // Se activa desde el día 25 de cada mes
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $dia_actual = (int)date('d');
        // Para pruebas, se puede ajustar este límite. Por defecto >= 25.
        if ($dia_actual >= 25) {
            $mes_actual = date('m');
            $ano_actual = date('Y');
            
            // Contar planillas abiertas del mes actual
            // Una planilla está abierta si existe en planillas_mensuales pero NO en cierres_mensuales con esta_cerrado=1
            $sqlCierre = "SELECT COUNT(DISTINCT p.empleador_id) as abiertas
                          FROM planillas_mensuales p
                          LEFT JOIN cierres_mensuales c ON p.empleador_id = c.empleador_id 
                                                        AND p.mes = c.mes 
                                                        AND p.ano = c.ano
                          WHERE p.mes = :mes AND p.ano = :ano 
                          AND (c.esta_cerrado IS NULL OR c.esta_cerrado = 0)";
            
            $stmtCierre = $pdo->prepare($sqlCierre);
            $stmtCierre->execute([':mes' => $mes_actual, ':ano' => $ano_actual]);
            $rowCierre = $stmtCierre->fetch(PDO::FETCH_ASSOC);
            $abiertas = $rowCierre['abiertas'] ?? 0;

            if ($abiertas > 0) {
                 $notifications[] = [
                    'type' => 'cierre',
                    'id' => 'cierre_mes_' . $mes_actual, // ID único virtual
                    'mensaje' => "Cierre Remuneraciones",
                    'subtexto' => "$abiertas planillas por cerrar",
                    'fecha' => date('d/m/Y'),
                    'url' => BASE_URL . '/planillas/cierres_mensuales.php'
                ];
            }
        }
    }

} catch (PDOException $e) {
    // error_log($e->getMessage());
}

echo json_encode(['count' => count($notifications), 'items' => $notifications]);
