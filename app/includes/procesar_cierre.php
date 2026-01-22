<?php
// app/includes/procesar_cierre.php

/**
 * Procesa el guardado del Cierre Mensual de un bus.
 * Retorna un array con ['success' => bool, 'message' => string, 'type' => string]
 */
function procesar_grabado_cierre($pdo, $post_data)
{

    $bus_id = (int)$post_data['bus_id'];
    $mes = (int)$post_data['mes'];
    $anio = (int)$post_data['anio'];

    // Inputs Manuales
    $subsidio_operacional = (int)($post_data['subsidio_operacional'] ?? 0);
    $devolucion_minutos = (int)($post_data['devolucion_minutos'] ?? 0);
    $otros_ingresos_1 = (int)($post_data['otros_ingresos_1'] ?? 0);
    $otros_ingresos_2 = (int)($post_data['otros_ingresos_2'] ?? 0);
    $otros_ingresos_3 = (int)($post_data['otros_ingresos_3'] ?? 0);

    $anticipo = (int)($post_data['anticipo'] ?? 0);
    $asignacion_familiar = (int)($post_data['asignacion_familiar'] ?? 0);
    $pago_minutos = (int)($post_data['pago_minutos'] ?? 0);
    $saldo_anterior = (int)($post_data['saldo_anterior'] ?? 0);
    $ayuda_mutua = (int)($post_data['ayuda_mutua'] ?? 0);
    $servicio_grua = (int)($post_data['servicio_grua'] ?? 0);
    $poliza_seguro = (int)($post_data['poliza_seguro'] ?? 0);

    $valor_vueltas_directo = (int)($post_data['valor_vueltas_directo'] ?? 0);
    $valor_vueltas_local = (int)($post_data['valor_vueltas_local'] ?? 0);
    $cant_vueltas_directo = (int)($post_data['cant_vueltas_directo'] ?? 0);
    $cant_vueltas_local = (int)($post_data['cant_vueltas_local'] ?? 0);

    // Campos calculados
    $monto_leyes_sociales = (int)($post_data['monto_leyes_sociales'] ?? 0);
    $monto_administracion_aplicado = (int)($post_data['monto_administracion_aplicado'] ?? 0);

    // Cargos fijos
    $derechos_loza = (int)($post_data['derechos_loza'] ?? 0);
    $seguro_cartolas = (int)($post_data['seguro_cartolas'] ?? 0);
    $gps = (int)($post_data['gps'] ?? 0);
    $boleta_garantia = (int)($post_data['boleta_garantia'] ?? 0);
    $boleta_garantia_dos = (int)($post_data['boleta_garantia_dos'] ?? 0);

    try {
        $stmt = $pdo->prepare("INSERT INTO cierres_maquinas 
            (bus_id, mes, anio, subsidio_operacional, devolucion_minutos, otros_ingresos_1, otros_ingresos_2, otros_ingresos_3,
             anticipo, asignacion_familiar, pago_minutos, saldo_anterior, ayuda_mutua, servicio_grua, poliza_seguro,
             valor_vueltas_directo, valor_vueltas_local, cant_vueltas_directo, cant_vueltas_local,
             monto_leyes_sociales, monto_administracion_aplicado,
             derechos_loza, seguro_cartolas, gps, boleta_garantia, boleta_garantia_dos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
             subsidio_operacional = VALUES(subsidio_operacional),
             devolucion_minutos = VALUES(devolucion_minutos),
             otros_ingresos_1 = VALUES(otros_ingresos_1),
             otros_ingresos_2 = VALUES(otros_ingresos_2),
             otros_ingresos_3 = VALUES(otros_ingresos_3),
             anticipo = VALUES(anticipo),
             asignacion_familiar = VALUES(asignacion_familiar),
             pago_minutos = VALUES(pago_minutos),
             saldo_anterior = VALUES(saldo_anterior),
             ayuda_mutua = VALUES(ayuda_mutua),
             servicio_grua = VALUES(servicio_grua),
             poliza_seguro = VALUES(poliza_seguro),
             valor_vueltas_directo = VALUES(valor_vueltas_directo),
             valor_vueltas_local = VALUES(valor_vueltas_local),
             cant_vueltas_directo = VALUES(cant_vueltas_directo),
             cant_vueltas_local = VALUES(cant_vueltas_local),
             monto_leyes_sociales = VALUES(monto_leyes_sociales),
             monto_administracion_aplicado = VALUES(monto_administracion_aplicado),
             derechos_loza = VALUES(derechos_loza),
             seguro_cartolas = VALUES(seguro_cartolas),
             gps = VALUES(gps),
             boleta_garantia = VALUES(boleta_garantia),
             boleta_garantia_dos = VALUES(boleta_garantia_dos)
        ");

        $stmt->execute([
            $bus_id,
            $mes,
            $anio,
            $subsidio_operacional,
            $devolucion_minutos,
            $otros_ingresos_1,
            $otros_ingresos_2,
            $otros_ingresos_3,
            $anticipo,
            $asignacion_familiar,
            $pago_minutos,
            $saldo_anterior,
            $ayuda_mutua,
            $servicio_grua,
            $poliza_seguro,
            $valor_vueltas_directo,
            $valor_vueltas_local,
            $cant_vueltas_directo,
            $cant_vueltas_local,
            $monto_leyes_sociales,
            $monto_administracion_aplicado,
            $derechos_loza,
            $seguro_cartolas,
            $gps,
            $boleta_garantia,
            $boleta_garantia_dos
        ]);

        return [
            'success' => true,
            'message' => "Cierre guardado correctamente.",
            'type' => "success",
            'mes' => $mes,
            'anio' => $anio
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Error al guardar: " . $e->getMessage(),
            'type' => "danger"
        ];
    }
}
