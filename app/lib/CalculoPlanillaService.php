<?php
class CalculoPlanillaService
{
    private $pdo;
    private $stmt_trab;
    private $stmt_sind;
    private $stmt_afp_comision;

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
        // Consultamos los campos nuevos de la tabla trabajadores según tu imagen
        $this->stmt_trab = $this->pdo->prepare("
            SELECT 
                afp_id, 
                sindicato_id, 
                estado_previsional, 
                sistema_previsional, 
                tasa_inp_decimal, 
                tiene_cargas, 
                numero_cargas 
            FROM trabajadores 
            WHERE id = ?
        ");

        $this->stmt_sind = $this->pdo->prepare("SELECT descuento FROM sindicatos WHERE id = ?");

        // Consulta para obtener la comisión histórica de la AFP
        $this->stmt_afp_comision = $this->pdo->prepare("
            SELECT comision_decimal FROM afp_comisiones_historicas 
            WHERE afp_id = :afp_id 
              AND (ano_inicio < :ano OR (ano_inicio = :ano AND mes_inicio <= :mes))
            ORDER BY ano_inicio DESC, mes_inicio DESC 
            LIMIT 1
        ");
    }

    private function getTramosAF($fecha_reporte)
    {
        if (isset($this->tramos_af_cacheados[$fecha_reporte])) {
            return $this->tramos_af_cacheados[$fecha_reporte];
        }
        $stmt = $this->pdo->prepare("
            SELECT tramo, monto_por_carga, renta_maxima FROM cargas_tramos_historicos
            WHERE fecha_inicio = (
                SELECT MAX(fecha_inicio) FROM cargas_tramos_historicos WHERE fecha_inicio <= :fecha_reporte
            )
            ORDER BY renta_maxima ASC
        ");
        $stmt->execute(['fecha_reporte' => $fecha_reporte]);
        $this->tramos_af_cacheados[$fecha_reporte] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->tramos_af_cacheados[$fecha_reporte];
    }

    public function calcularFila($fila_planilla, $mes, $ano)
    {
        $this->stmt_trab->execute([$fila_planilla['trabajador_id']]);
        $trabajador = $this->stmt_trab->fetch(PDO::FETCH_ASSOC);

        // Si no encuentra trabajador (caso raro), evitar error fatal
        if (!$trabajador) {
            return [
                'descuento_afp' => 0,
                'descuento_salud' => 0,
                'seguro_cesantia' => 0,
                'sindicato' => 0,
                'asignacion_familiar_calculada' => 0
            ];
        }

        $sueldo_imponible = (int)$fila_planilla['sueldo_imponible'];

        $base_afp = min($sueldo_imponible, self::TOPE_IMPONIBLE_AFP);
        $base_salud = min($sueldo_imponible, self::TOPE_IMPONIBLE_SALUD);
        $base_cesantia = min($sueldo_imponible, self::TOPE_IMPONIBLE_CESANTIA);

        // --- 1. CÁLCULO PREVISIÓN (AFP o INP) ---
        $descuento_prevision = 0;

        if ($trabajador['estado_previsional'] == 'Activo') {

            if ($trabajador['sistema_previsional'] == 'INP') {
                // === CASO INP ===
                // Usamos la tasa decimal guardada en el trabajador (ej: 0.1884)
                $tasa_inp = (float)$trabajador['tasa_inp_decimal'];
                $descuento_prevision = round($base_afp * $tasa_inp);
            } else {
                // === CASO AFP ===
                if ($trabajador['afp_id']) {
                    $this->stmt_afp_comision->execute([
                        'afp_id' => $trabajador['afp_id'],
                        'ano' => $ano,
                        'mes' => $mes
                    ]);
                    $comision_data = $this->stmt_afp_comision->fetch(PDO::FETCH_ASSOC);

                    $comision_afp = $comision_data ? (float)$comision_data['comision_decimal'] : 0.0;
                    // 10% Obligatorio + Comisión AFP
                    $descuento_prevision = round($base_afp * (self::COTIZACION_AFP_OBLIGATORIA + $comision_afp));
                }
            }
        }

        // --- 2. Descuento Salud (7%) ---
        // Se aplica igual para todos
        $descuento_salud_7pct = round($base_salud * self::COTIZACION_SALUD_MINIMA);

        // --- 3. Seguro Cesantía ---
        $seguro_cesantia_trabajador = 0;

        // REGLA IMPORTANTE: INP NO PAGA SEGURO DE CESANTÍA
        if ($trabajador['sistema_previsional'] != 'INP') {

            // Revisar si debe cotizar (Activo o Pensionado con Toggle)
            $cotiza_cesantia = ($trabajador['estado_previsional'] == 'Activo');

            if ($trabajador['estado_previsional'] == 'Pensionado' && isset($fila_planilla['cotiza_cesantia_pensionado']) && $fila_planilla['cotiza_cesantia_pensionado'] == 1) {
                $cotiza_cesantia = true;
            }

            if ($cotiza_cesantia) {
                if ($fila_planilla['tipo_contrato'] == 'Indefinido') {
                    // Indefinido: 0.6% a cargo del trabajador
                    $seguro_cesantia_trabajador = round($base_cesantia * 0.006);
                }
                // Si es Fijo, el trabajador paga 0% (todo el empleador)
            }
        }

        // --- 4. Sindicato ---
        $descuento_sindicato = 0;
        if ($trabajador['sindicato_id']) {
            $this->stmt_sind->execute([$trabajador['sindicato_id']]);
            $sindicato = $this->stmt_sind->fetch(PDO::FETCH_ASSOC);
            $descuento_sindicato = $sindicato ? (int)$sindicato['descuento'] : 0;
        }

        // --- 5. Asignación Familiar ---
        $asignacion_familiar_calculada = 0;
        // Usamos el primer día del mes para buscar el tramo vigente
        $fecha_tramo = "$ano-$mes-01";
        $tramos_af = $this->getTramosAF($fecha_tramo);

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

        // Retornamos los valores calculados
        return [
            'descuento_afp' => $descuento_prevision,
            'descuento_salud' => $descuento_salud_7pct,
            'seguro_cesantia' => $seguro_cesantia_trabajador,
            'sindicato' => $descuento_sindicato,
            'asignacion_familiar_calculada' => $asignacion_familiar_calculada
        ];
    }
}
