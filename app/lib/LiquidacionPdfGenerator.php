<?php
use Mpdf\Mpdf;

class LiquidacionPdfGenerator {

    public static function generarHtml($id_liquidacion, $pdo) {
        // 1. Consultar Datos
        $sql = "SELECT l.*, 
                       t.nombre as trab_nom, t.rut as trab_rut, t.sistema_previsional,
                       e.nombre as emp_nom, e.rut as emp_rut,
                       a.nombre as afp_nom
                FROM liquidaciones l
                JOIN trabajadores t ON l.trabajador_id = t.id
                JOIN empleadores e ON l.empleador_id = e.id
                LEFT JOIN afps a ON t.afp_id = a.id
                WHERE l.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_liquidacion]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$d) return null;

        // 2. Preparar Textos
        $nombre_prevision = ($d['sistema_previsional'] == 'INP') ? 'INP' : ($d['afp_nom'] ?: 'AFP');

        // Formatear mes
        $fecha_obj = DateTime::createFromFormat('!m', $d['mes']);
        $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'LLLL');
        $mes_nombre = mb_strtoupper($formatter->format($fecha_obj));
        
        // Cálculos visuales finales
        $total_imponible = $d['sueldo_base'] + $d['gratificacion_legal'];
        $total_no_imp = $d['colacion'] + $d['movilizacion'] + $d['asig_familiar']; // + viaticos
        $liquido = $d['sueldo_liquido']; // Ya viene calculado, pero es bueno verificar
        
        // 3. Generar HTML
        ob_start();
        ?>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; }
            .box { border: 1px solid #000; padding: 8px; margin-bottom: 10px; }
            .header { text-align: center; font-weight: bold; font-size: 14pt; margin-bottom: 5px; text-transform: uppercase; }
            .sub-header { text-align: center; margin-bottom: 15px; font-size: 11pt; }
            .col-container { width: 100%; margin-bottom: 10px; }
            .col-left { float: left; width: 49%; }
            .col-right { float: right; width: 49%; }
            table { width: 100%; border-collapse: collapse; font-size: 9pt; }
            td { padding: 2px 0; }
            .amount { text-align: right; }
            .label { text-align: left; }
            .total-row td { border-top: 1px solid #000; font-weight: bold; padding-top: 2px; }
            .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; display:block; }
            .liquido-box { border: 2px solid #000; padding: 10px; text-align: center; font-size: 12pt; font-weight: bold; margin-top: 10px; background: #f0f0f0; }
            .firma-section { margin-top: 80px; width: 100%; }
            .firma-line { width: 40%; margin: 0 auto; border-top: 1px solid #000; text-align: center; padding-top: 5px; }
            .footer-legal { font-size: 8pt; text-align: justify; margin-top: 30px; }
        </style>

        <div class="header">LIQUIDACIÓN DE SUELDO</div>
        <div class="sub-header">MES: <?php echo $mes_nombre . ' DE ' . $d['ano']; ?></div>

        <div class="box">
            <table style="width:100%">
                <tr>
                    <td style="width:15%"><strong>EMPLEADOR:</strong></td>
                    <td style="width:45%"><?php echo htmlspecialchars($d['emp_nom']); ?></td>
                    <td style="width:10%"><strong>RUT:</strong></td>
                    <td style="width:30%"><?php echo $d['emp_rut']; ?></td>
                </tr>
                <tr>
                    <td><strong>TRABAJADOR:</strong></td>
                    <td><?php echo htmlspecialchars($d['trab_nom']); ?></td>
                    <td><strong>RUT:</strong></td>
                    <td><?php echo $d['trab_rut']; ?></td>
                </tr>
            </table>
        </div>

        <div class="col-container">
            <div class="col-left">
                <div class="box" style="min-height: 250px;">
                    <span class="section-title">HABERES</span>
                    <table>
                        <tr><td class="label">Sueldo Base</td><td class="amount">$<?php echo number_format($d['sueldo_base'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Gratificación Legal</td><td class="amount">$<?php echo number_format($d['gratificacion_legal'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Horas Extras</td><td class="amount">$0</td></tr>
                        <tr><td colspan="2">&nbsp;</td></tr>
                        <tr class="total-row"><td class="label">TOTAL IMPONIBLE</td><td class="amount">$<?php echo number_format($d['total_imponible'],0,',','.'); ?></td></tr>
                        
                        <tr><td colspan="2">&nbsp;</td></tr>
                        
                        <tr><td class="label">Bonos</td><td class="amount">$<?php echo number_format($d['bonos_imponibles'] ?? 0,0,',','.'); ?></td></tr>
                        <tr><td class="label">Movilización</td><td class="amount">$<?php echo number_format($d['movilizacion'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Colación</td><td class="amount">$<?php echo number_format($d['colacion'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Asig. Familiar</td><td class="amount">$<?php echo number_format($d['asig_familiar'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Viáticos / Otros No Imp.</td><td class="amount">0</td></tr>
                        
                        <tr><td colspan="2">&nbsp;</td></tr>
                        <tr class="total-row"><td class="label">TOTAL HABERES</td><td class="amount">$<?php echo number_format($d['total_haberes'],0,',','.'); ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="col-right">
                <div class="box" style="min-height: 250px;">
                    <span class="section-title">DESCUENTOS</span>
                    <table>
                        <tr><td class="label">Previsión (<?php echo $nombre_prevision; ?>)</td><td class="amount">$<?php echo number_format($d['descuento_afp'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Salud (7%)</td><td class="amount">$<?php echo number_format($d['descuento_salud'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Seguro Cesantía</td><td class="amount">$<?php echo number_format($d['seguro_cesantia'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Adicional Salud / APV</td><td class="amount">$<?php echo number_format($d['adicional_salud_apv'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Impuesto Único</td><td class="amount">$<?php echo number_format($d['impuesto_unico'],0,',','.'); ?></td></tr>
                        
                        <tr><td colspan="2">&nbsp;</td></tr>
                        
                        <tr><td class="label">Anticipos</td><td class="amount">$<?php echo number_format($d['anticipos'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Sindicato</td><td class="amount">$<?php echo number_format($d['sindicato'],0,',','.'); ?></td></tr>
                        <tr><td class="label">Préstamos / Otros</td><td class="amount">0</td></tr>

                        <tr><td colspan="2">&nbsp;</td></tr>
                        <tr class="total-row"><td class="label">TOTAL DESCUENTOS</td><td class="amount">$<?php echo number_format($d['total_descuentos'],0,',','.'); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div style="clear:both;"></div>

        <div class="liquido-box">
            LÍQUIDO A PAGAR: $<?php echo number_format($liquido,0,',','.'); ?>
        </div>

        <div class="footer-legal">
            Certifico que he recibido de mi empleador, a mi entera satisfacción, el saldo líquido indicado en la presente liquidación, no teniendo cargo ni cobro alguno posterior que hacer por los conceptos de esta liquidación.
        </div>

        <div class="firma-section">
            <div class="firma-line">
                <strong>FIRMA TRABAJADOR</strong><br>
                RUT: <?php echo $d['trab_rut']; ?>
            </div>
        </div>
        <?php
        // Retornamos HTML y también datos clave para el nombre del archivo en el ZIP
        return [
            'html' => ob_get_clean(),
            'mes' => $d['mes'],
            'ano' => $d['ano'],
            'empleador' => $d['emp_nom'],
            'trabajador' => $d['trab_nom']
        ];
    }
}
?>