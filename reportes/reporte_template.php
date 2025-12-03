<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Cotizaciones</title>
</head>

<body>
    <main>
        <div class="info-empleador">
            <div class="info-col">
                <p><span class="label">Empleador:</span> <?= htmlspecialchars($empleador['nombre']) ?></p>
                <p><span class="label">RUT:</span> <?= format_rut($empleador['rut']) ?></p>

            </div>
            <div class="info-col">
                <p><span class="label">C. Compensación:</span> <?= htmlspecialchars($empleador['caja_compensacion_nombre'] ?: 'N/A') ?></p>
                <p><span class="label">Mutual:</span> <?= htmlspecialchars($empleador['mutual_seguridad_nombre'] ?: 'ISL (Defecto)') ?></p>
                <p><span class="label">Tasa Mutual:</span> <?= sprintf("%.2f", $calculos_pie['tasa_mutual_aplicada_porc']) ?>%</p>
            </div>
        </div>

        <table class="tabla-principal">
            <thead>
                <tr>
                    <th>RUT</th>
                    <th>Nombre</th>
                    <th style="width: 50px;">Días Trab.</th>
                    <th>Sueldo Imp.</th>
                    <th>AFP</th>
                    <th>Salud</th>
                    <th>APV</th>
                    <th>Seg. Cesantía</th>
                    <th>Sindicato</th>
                    <th>Ces. Lic. Méd.</th>
                    <th>Total Desc.</th>
                    <th>Aportes</th>
                    <th>Asig. Familiar</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><?= format_rut($r['trabajador_rut']) ?></td>
                        <td class="nombre-trabajador">
                            <?php if ($r['tipo_contrato'] == 'Fijo'): ?><span class="fixed-marker">F</span><?php endif; ?>

                            <?php if (isset($r['es_part_time']) && $r['es_part_time'] == 1): ?><span class="fixed-marker" style="color: #007bff;">P</span><?php endif; ?>

                            <?= htmlspecialchars($r['trabajador_nombre']) ?>
                        </td>
                        <td style="text-align: center;"><?= $r['dias_trabajados'] ?></td>
                        <td><?= format_numero($r['sueldo_imponible']) ?></td>
                        <td><?= format_numero($r['descuento_afp']) ?></td>
                        <td><?= format_numero($r['descuento_salud']) ?></td>
                        <td><?= format_numero($r['adicional_salud_apv']) ?></td>
                        <td><?= format_numero($r['seguro_cesantia']) ?></td>
                        <td><?= format_numero($r['sindicato']) ?></td>
                        <td><?= format_numero($r['cesantia_licencia_medica']) ?></td>
                        <td class="total"><?= format_numero($r['total_descuentos']) ?></td>
                        <td><?= format_numero($r['aportes']) ?></td>
                        <td><?= format_numero($r['asignacion_familiar_calculada']) ?></td>
                        <td class="total"><?= format_numero($r['saldo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">Totales</th>
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

        <table class="tabla-resumen">
            <thead>
                <tr>
                    <th colspan="2">Resumen de Totales</th>
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
                    <td>(+) SIS (Tasa Histórica)</td>
                    <td class="numero"><?= format_numero($calculos_pie['sis']) ?></td>
                </tr>
                <tr>
                    <td>(+) Seguro Cesantía Patronal</td>
                    <td class="numero"><?= format_numero($calculos_pie['seguro_cesantia_patronal']) ?></td>
                </tr>
                <tr>
                    <td>(+) Capitalización Individual (SIS)</td>
                    <td class="numero"><?= format_numero($calculos_pie['capitalizacion_individual']) ?></td>
                </tr>
                <tr>
                    <td>(+) Expectativa de Vida (SIS)</td>
                    <td class="numero"><?= format_numero($calculos_pie['expectativa_vida']) ?></td>
                </tr>
                <tr class="total">
                    <td>Sub-Total Descuentos</td>
                    <td class="numero"><?= format_numero($calculos_pie['sub_total_desc_pie']) ?></td>
                </tr>
                <tr>
                    <td>(-) Aportes Conductor</td>
                    <td class="numero"><?= format_numero($calculos_pie['aportes_conductor']) ?></td>
                </tr>
                <tr>
                    <td>(+) Saldos Positivos</td>
                    <td class="numero"><?= format_numero($calculos_pie['saldos_positivos']) ?></td>
                </tr>
                <tr class="total-final">
                    <td>Total Descuentos</td>
                    <td class="numero"><?= format_numero($calculos_pie['total_descuentos_final']) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td>Total Asignación Familiar</td>
                    <td class="numero"><?= format_numero($calculos_pie['asignacion_familiar']) ?></td>
                </tr>
                <tr>
                    <td>Total Sindicato</td>
                    <td class="numero"><?= format_numero($calculos_pie['sindicato']) ?></td>
                </tr>
                <tr class="total-final">
                    <td>Total Leyes Sociales (Empleado + Empleador)</td>
                    <td class="numero"><?= format_numero($calculos_pie['total_leyes_sociales']) ?></td>
                </tr>
            </tfoot>
        </table>

    </main>
</body>

</html>