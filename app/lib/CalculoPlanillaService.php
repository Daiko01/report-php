<?php
class CalculoPlanillaService
{
    private $pdo;
    private $stmt_trab;
    private $stmt_sind;
    private $stmt_afp_comision;
    private $stmt_tramos_af;

    private $tramos_af_cacheados = [];

    // Constantes
    const TOPE_IMPONIBLE_AFP = 2656956;
    const TOPE_IMPONIBLE_SALUD = 2656956;
    const TOPE_IMPONIBLE_CESANTIA = 4006198;
    const COTIZACION_SALUD_MINIMA = 0.07;
    const COTIZACION_AFP_OBLIGATORIA = 0.10;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->prepararConsultas();
    }

    private function prepararConsultas()
    {
        $this->stmt_trab = $this->pdo->prepare("SELECT afp_id, sindicato_id, estado_previsional, tiene_cargas, numero_cargas FROM trabajadores WHERE id = ?");
        $this->stmt_sind = $this->pdo->prepare("SELECT descuento FROM sindicatos WHERE id = ?");
        $this->stmt_afp_comision = $this->pdo->prepare(
            "SELECT comision_decimal FROM afp_comisiones_historicas 
             WHERE afp_id = :afp_id 
               AND (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes))
             ORDER BY ano_inicio DESC, mes_inicio DESC 
             LIMIT 1"
        );
    }

    private function getTramosAF($fecha_reporte)
    {
        if (isset($this->tramos_af_cacheados[$fecha_reporte])) {
            return $this->tramos_af_cacheados[$fecha_reporte];
        }
        $stmt = $this->pdo->prepare(
            "SELECT tramo, monto_por_carga, renta_maxima FROM cargas_tramos_historicos
             WHERE fecha_inicio = (
                 SELECT MAX(fecha_inicio) FROM cargas_tramos_historicos WHERE fecha_inicio <= :fecha_reporte
             )
             ORDER BY renta_maxima ASC"
        );
        $stmt->execute(['fecha_reporte' => $fecha_reporte]);
        $this->tramos_af_cacheados[$fecha_reporte] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->tramos_af_cacheados[$fecha_reporte];
    }

    /**
     * Esta es la función principal que calcula todo para una fila.
     * @param array $fila_planilla Los datos de la planilla (ID, sueldo, dias, etc.)
     * @param int $mes El mes del reporte
     * @param int $ano El año del reporte
     * @return array Array de descuentos calculados
     */
    public function calcularFila($fila_planilla, $mes, $ano)
    {
        $this->stmt_trab->execute([$fila_planilla['trabajador_id']]);
        $trabajador = $this->stmt_trab->fetch();

        $sueldo_imponible = (int)$fila_planilla['sueldo_imponible'];
        $dias_trabajados = (int)$fila_planilla['dias_trabajados'];
        // El imponible ya viene prorrateado, solo aplicamos topes

        $base_afp = min($sueldo_imponible, self::TOPE_IMPONIBLE_AFP);
        $base_salud = min($sueldo_imponible, self::TOPE_IMPONIBLE_SALUD);
        $base_cesantia = min($sueldo_imponible, self::TOPE_IMPONIBLE_CESANTIA);

        // Descuento AFP
        $descuento_afp = 0;
        if ($trabajador['estado_previsional'] == 'Activo' && $trabajador['afp_id']) {
            $this->stmt_afp_comision->execute(['afp_id' => $trabajador['afp_id'], 'ano' => $ano, 'mes' => $mes]);
            $comision_data = $this->stmt_afp_comision->fetch();
            $comision_afp = $comision_data ? (float)$comision_data['comision_decimal'] : 0.0;
            $descuento_afp = round($base_afp * (self::COTIZACION_AFP_OBLIGATORIA + $comision_afp));
        }

        // Descuento Salud
        $descuento_salud_7pct = round($base_salud * self::COTIZACION_SALUD_MINIMA);

        // Seguro Cesantía
        $seguro_cesantia_trabajador = 0;
        $es_pensionado_cotiza = ($trabajador['estado_previsional'] == 'Pensionado' && $fila_planilla['cotiza_cesantia_pensionado'] == 1);
        if ($trabajador['estado_previsional'] == 'Activo' || $es_pensionado_cotiza) {
            if ($fila_planilla['tipo_contrato'] == 'Indefinido') {
                $seguro_cesantia_trabajador = round($base_cesantia * 0.006);
            }
        }

        // Sindicato
        $descuento_sindicato = 0;
        if ($trabajador['sindicato_id']) {
            $this->stmt_sind->execute([$trabajador['sindicato_id']]);
            $sindicato = $this->stmt_sind->fetch();
            $descuento_sindicato = $sindicato ? (int)$sindicato['descuento'] : 0;
        }

        // Asignación Familiar
        $asignacion_familiar_calculada = 0;
        $tramos_af = $this->getTramosAF("$ano-$mes-01");
        if ($trabajador['tiene_cargas'] == 1 && $trabajador['numero_cargas'] > 0) {
            $monto_por_carga = 0;
            foreach ($tramos_af as $tramo) {
                if ($sueldo_imponible <= $tramo['renta_maxima']) {
                    $monto_por_carga = $tramo['monto_por_carga'];
                    break;
                }
            }
            $asignacion_familiar_calculada = $monto_por_carga * (int)$trabajador['numero_cargas'];
        }

        return [
            'descuento_afp' => $descuento_afp,
            'descuento_salud' => $descuento_salud_7pct,
            'seguro_cesantia' => $seguro_cesantia_trabajador,
            'sindicato' => $descuento_sindicato,
            'asignacion_familiar_calculada' => $asignacion_familiar_calculada
        ];
    }
}
