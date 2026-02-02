<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
</head>

<body>
    <main>
        <!-- DATOS DEL EMPLEADOR (Horizontal 3 Cols Compact) -->
        <div class="info-empleador" style="overflow: auto;">
            <!-- 1. Nombre y Rut -->
            <div class="info-col primera">
                <p><span class="label">Empleador:</span> <?= htmlspecialchars($empleador['nombre']) ?></p>
                <p><span class="label">RUT:</span> <?= format_rut($empleador['rut']) ?></p>
            </div>
            <!-- 2. Mutual y Caja (CENTRAL) -->
            <div class="info-col segunda">
                <p><span class="label">C. Compensación:</span> <?= htmlspecialchars($empleador['caja_compensacion_nombre'] ?: 'N/A') ?></p>
                <p><span class="label">Mutual:</span> <?= htmlspecialchars($empleador['mutual_seguridad_nombre'] ?: 'ISL') ?></p>
                <p><span class="label">Tasa Mutual:</span> <?= sprintf("%.2f", $calculos_pie['tasa_mutual_aplicada_porc']) ?>%</p>
            </div>
            <!-- 3. Leyendas (DERECHA pero integrada) -->
            <div class="info-col tercera">
                <span class="fixed-marker marker-f">F : Plazo Fijo</span>
                <span class="fixed-marker marker-p">P : Part-time</span>
            </div>
        </div>

        <!-- TABLA PRINCIPAL (Compacta) -->
        <table class="tabla-principal">
            <thead>
                <tr>
                    <th style="width: 5%;">RUT</th>
                    <th style="width: 25%;">Nombre</th>
                    <th style="width: 3%;">Días</th> <!-- Muy estrecha -->
                    <th style="width: 6%;">Sueldo Imp.</th>
                    <th style="width: 6%;">AFP/INP</th>
                    <th style="width: 6%;">Salud</th>
                    <th style="width: 6%;">APV</th>
                    <th style="width: 6%;">Seg. Cesantía</th>
                    <th style="width: 6%;">Sindicato</th>
                    <th style="width: 5%;">Ces. Lic. Méd.</th> <!-- Reducido -->
                    <th class="total" style="width: 7%;">Total Desc.</th>
                    <th style="width: 6%;">Aportes</th>
                    <th style="width: 5%;">Asig. Familiar</th> <!-- Reducido -->
                    <th class="total" style="width: 7%;">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><?= format_rut($r['trabajador_rut']) ?></td>
                        <td class="nombre-trabajador">
                            <?php if ($r['tipo_contrato'] == 'Fijo'): ?><span class="fixed-marker marker-f">F</span><?php endif; ?>
                            <?php if (isset($r['es_part_time']) && $r['es_part_time'] == 1): ?><span class="fixed-marker marker-p">P</span><?php endif; ?>
                            <?= htmlspecialchars($r['trabajador_nombre']) ?>
                        </td>
                        <td style="text-align:center;"><?= $r['dias_trabajados'] ?></td>
                        <td><?= format_numero($r['sueldo_imponible']) ?></td>
                        <td>
                            <?php
                            if ($r['trabajador_estado_previsional'] == 'Pensionado') echo '<div style="font-size:6px;font-weight:bold;color:#444;">PENSIONADO</div>';
                            elseif ($r['sistema_previsional'] == 'INP') echo '<div style="font-size:6px;font-weight:bold;color:#444;">INP</div>';
                            else {
                                $nombre_afp_mostrar = $r['afp_historico_nombre'];
                                if (!empty($nombre_afp_mostrar)) echo '<div style="font-size:6px;font-weight:bold;color:#444;">' . mb_strtoupper($nombre_afp_mostrar) . '</div>';
                            }
                            echo format_numero($r['descuento_afp']);
                            ?>
                        </td>
                        <td><?= format_numero($r['descuento_salud']) ?></td>
                        <td><?= format_numero($r['adicional_salud_apv']) ?></td>
                        <td><?= format_numero($r['seguro_cesantia']) ?></td>
                        <td><?= format_numero($r['sindicato']) ?></td>
                        <td><?= format_numero($r['cesantia_licencia_medica']) ?></td>
                        <td class="total"><?= format_numero($r['total_descuentos']) ?></td>
                        <td><?= format_numero($r['aportes']) ?></td>
                        <td>
                            <?= format_numero($r['asignacion_familiar_calculada']) ?>
                            <?php if (!empty($r['tramo_letra'])): ?>
                                <br><span style="font-size:6px; color:#555;">(Tr <?= $r['tramo_letra'] ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="total"><?= format_numero($r['saldo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align:right;">Totales</th>
                    <th>-</th>
                    <th><?= format_numero($totales_tabla['sueldo_imponible']) ?></th>
                    <th><?= format_numero($totales_tabla['descuento_afp']) ?></th>
                    <th><?= format_numero($totales_tabla['descuento_salud']) ?></th>
                    <th><?= format_numero($totales_tabla['adicional_salud_apv']) ?></th>
                    <th><?= format_numero($totales_tabla['seguro_cesantia']) ?></th>
                    <th><?= format_numero($totales_tabla['sindicato']) ?></th>
                    <th><?= format_numero($totales_tabla['cesantia_licencia_medica']) ?></th>
                    <th class="total"><?= format_numero($totales_tabla['total_descuentos']) ?></th>
                    <th><?= format_numero($totales_tabla['aportes']) ?></th>
                    <th><?= format_numero($totales_tabla['asignacion_familiar_calculada']) ?></th>
                    <th class="total"><?= format_numero($totales_tabla['saldo']) ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- RESUMEN FINAL A DOS COLUMNAS (SEPARADO) -->
        <div class="resumen-container">
            <!-- Columna Izquierda -->
            <div class="resumen-col izq">
                <table class="tabla-resumen">
                    <thead>
                        <tr>
                            <th colspan="2">Detalle Técnico & Aportes Empresa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sub-Total Descuentos (Tabla)</td>
                            <td class="numero"><?= format_numero($calculos_pie['sub_total_desc_tabla']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Aporte Patronal (<?= sprintf("%.2f", $calculos_pie['tasa_mutual_aplicada_porc']) ?>%)</td>
                            <td class="numero"><?= format_numero($calculos_pie['aporte_patronal']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) SIS (Seguro Invalidez)</td>
                            <td class="numero"><?= format_numero($calculos_pie['sis']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Seguro Cesantía (Patronal)</td>
                            <td class="numero"><?= format_numero($calculos_pie['seguro_cesantia_patronal']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Cap. Individual (0,1%)</td>
                            <td class="numero"><?= format_numero($calculos_pie['capitalizacion_individual']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Expectativa de Vida (0,9%)</td>
                            <td class="numero"><?= format_numero($calculos_pie['expectativa_vida']) ?></td>
                        </tr>
                        <!-- Highlight SubTotal -->
                        <tr class="total">
                            <td>Sub-Total (Incluye Aportes)</td>
                            <td class="numero"><?= format_numero($calculos_pie['sub_total_desc_pie']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Columna Derecha -->
            <div class="resumen-col der">
                <table class="tabla-resumen">
                    <thead>
                        <tr>
                            <th colspan="2">Resumen Final & Leyes Sociales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>(+) Asignación Familiar</td>
                            <td class="numero"><?= format_numero($calculos_pie['asignacion_familiar']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Sindicato</td>
                            <td class="numero"><?= format_numero($calculos_pie['sindicato']) ?></td>
                        </tr>

                        <!-- GRAN TOTAL -->
                        <tr class="total-destacado">
                            <td>TOTAL LEYES SOCIALES</td>
                            <td class="numero final"><?= format_numero($calculos_pie['total_leyes_sociales']) ?></td>
                        </tr>

                        <tr>
                            <td style="padding-top:10px;">(-) Aportes Conductor</td>
                            <td class="numero" style="padding-top:10px;"><?= format_numero($calculos_pie['aportes_conductor']) ?></td>
                        </tr>
                        <tr>
                            <td>(+) Saldos Positivos</td>
                            <td class="numero"><?= format_numero($calculos_pie['saldos_positivos']) ?></td>
                        </tr>

                        <!-- RESTORED TOTAL DESCUENTOS -->
                        <tr class="total-final">
                            <td>TOTAL DESCUENTOS</td>
                            <td class="numero final"><?= format_numero($calculos_pie['total_descuentos_final']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>

</html>