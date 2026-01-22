<?php
class CalculoPlanillaService
{
    private $pdo;
    private $stmt_trab;
    private $stmt_sind;
    private $stmt_afp_comision;

    private $tramos_af_cacheados = [];

    // CACHE DATA
    private $cache_afp_comisiones = [];
    private $cache_sindicatos = [];
    private $cache_cargado = false;

    // Constantes
    const TOPE_IMPONIBLE_AFP = 2656956;
    const TOPE_IMPONIBLE_SALUD = 2656956;
    const TOPE_IMPONIBLE_CESANTIA = 4006198;
    const COTIZACION_SALUD_MINIMA = 0.07;
    const COTIZACION_AFP_OBLIGATORIA = 0.10;

    // Tope Gratificacion (4.75 IMM - Ajustar segun año)
    const TOPE_GRATIFICACION_MENSUAL = 203125;

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
                numero_cargas,
                tramo_asignacion_manual
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

    /**
     * Carga masiva de datos para evitar consultas N+1
     */
    public function cargarDatosGlobales($mes, $ano)
    {
        // 1. Cargar todas las comisiones vigentes de AFP para este mes
        // (Logica simplificada: traer todas y filtrar en memoria)
        $stmt = $this->pdo->query("SELECT * FROM afp_comisiones_historicas ORDER BY ano_inicio ASC, mes_inicio ASC");
        $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organizar por AFP y encontrar la vigente
        $mapa = [];
        foreach ($todas as $reg) {
            // Si la fecha es menor o igual al periodo
            if ($reg['ano_inicio'] < $ano || ($reg['ano_inicio'] == $ano && $reg['mes_inicio'] <= $mes)) {
                $mapa[$reg['afp_id']] = (float)$reg['comision_decimal'];
            }
        }
        $this->cache_afp_comisiones = $mapa;

        // 2. Cargar todos los sindicatos
        $stmt_s = $this->pdo->query("SELECT id, descuento FROM sindicatos");
        $sindicatos = $stmt_s->fetchAll(PDO::FETCH_KEY_PAIR); // [id => descuento]
        $this->cache_sindicatos = $sindicatos;

        $this->cache_cargado = true;
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

    /**
     * @param array $fila_planilla Datos de la fila de la planilla
     * @param int $mes
     * @param int $ano
     * @param array|null $trabajador_inyectado (Opcional) Datos del trabajador ya cargados para evitar query
     */
    public function calcularFila($fila_planilla, $mes, $ano, $trabajador_inyectado = null)
    {
        if ($trabajador_inyectado) {
            $trabajador = $trabajador_inyectado;
        } else {
            $this->stmt_trab->execute([$fila_planilla['trabajador_id']]);
            $trabajador = $this->stmt_trab->fetch(PDO::FETCH_ASSOC);
        }

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
                    $comision_afp = 0.0;
                    $nombre_afp_snapshot = '';

                    // Obtener nombre de la AFP (Snapshot)
                    $stmt_nombre_afp = $this->pdo->prepare("SELECT nombre FROM afps WHERE id = ?");
                    $stmt_nombre_afp->execute([$trabajador['afp_id']]);
                    $nombre_afp_snapshot = $stmt_nombre_afp->fetchColumn();

                    if ($this->cache_cargado && isset($this->cache_afp_comisiones[$trabajador['afp_id']])) {
                        // Usar CACHE
                        $comision_afp = $this->cache_afp_comisiones[$trabajador['afp_id']];
                    } else {
                        // Fallback a SQL estándar
                        $this->stmt_afp_comision->execute([
                            'afp_id' => $trabajador['afp_id'],
                            'ano' => $ano,
                            'mes' => $mes
                        ]);
                        $comision_data = $this->stmt_afp_comision->fetch(PDO::FETCH_ASSOC);
                        $comision_afp = $comision_data ? (float)$comision_data['comision_decimal'] : 0.0;
                    }

                    // 10% Obligatorio + Comisión AFP
                    $descuento_prevision = round($base_afp * (self::COTIZACION_AFP_OBLIGATORIA + $comision_afp));
                }
            }
        }

        // Si no es AFP o no tiene, el nombre es NULL
        if (!isset($nombre_afp_snapshot)) {
            $nombre_afp_snapshot = null;
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
            if ($this->cache_cargado && isset($this->cache_sindicatos[$trabajador['sindicato_id']])) {
                $descuento_sindicato = (int)$this->cache_sindicatos[$trabajador['sindicato_id']];
            } else {
                $this->stmt_sind->execute([$trabajador['sindicato_id']]);
                $sindicato = $this->stmt_sind->fetch(PDO::FETCH_ASSOC);
                $descuento_sindicato = $sindicato ? (int)$sindicato['descuento'] : 0;
            }
        }

        // --- 5. Asignación Familiar ---
        $asignacion_familiar_calculada = 0;
        $tipo_asignacion_familiar = 'AUTOMATICO';
        $tramo_letra_aplicada = null;

        // Usamos el primer día del mes para buscar el tramo vigente
        $fecha_tramo = "$ano-$mes-01";
        $tramos_af = $this->getTramosAF($fecha_tramo);

        // A. Verificar si tiene cálculo manual (Override)
        if (!empty($trabajador['tramo_asignacion_manual'])) {
            $tipo_asignacion_familiar = 'MANUAL';
            // Buscar el tramo específico manual
            foreach ($tramos_af as $tramo) {
                if ($tramo['tramo'] == $trabajador['tramo_asignacion_manual']) {
                    $monto_por_carga = $tramo['monto_por_carga'];
                    $tramo_letra_aplicada = $tramo['tramo'];
                    $asignacion_familiar_calculada = $monto_por_carga * (int)$trabajador['numero_cargas'];
                    break;
                }
            }
        }
        // B. Cálculo Automático
        elseif ($trabajador['tiene_cargas'] == 1 && $trabajador['numero_cargas'] > 0) {
            $monto_por_carga = 0;
            foreach ($tramos_af as $tramo) {
                // Tramos vienen ordenados por renta_maxima ASC
                if ($sueldo_imponible <= $tramo['renta_maxima']) {
                    $monto_por_carga = $tramo['monto_por_carga'];
                    $tramo_letra_aplicada = $tramo['tramo'];
                    break;
                }
            }
            // Si supera todos, quizás es tramo D (0).
            if ($tramo_letra_aplicada === null && !empty($tramos_af)) {
                $ultimo_tramo = end($tramos_af);
                $monto_por_carga = $ultimo_tramo['monto_por_carga'];
                $tramo_letra_aplicada = $ultimo_tramo['tramo'];
            }

            $asignacion_familiar_calculada = $monto_por_carga * (int)$trabajador['numero_cargas'];
        }

        // Corrección: Si el monto es 0, no asignamos letra (o ponemos D?)
        // Usualmente D es 0. Dejemos la letra si se detectó.

        // Retornamos los valores calculados
        return [
            'descuento_afp' => $descuento_prevision,
            'descuento_salud' => $descuento_salud_7pct,
            'seguro_cesantia' => $seguro_cesantia_trabajador,
            'sindicato' => $descuento_sindicato,
            'asignacion_familiar_calculada' => $asignacion_familiar_calculada,
            'afp_historico_nombre' => $nombre_afp_snapshot ?? null,
            'tipo_asignacion_familiar' => $tipo_asignacion_familiar,
            'tramo_asignacion_familiar' => $tramo_letra_aplicada
        ];
    }

    /**
     * Calcula los días trabajados en el mes bajo la lógica de 30 días comerciales.
     * Ajusta si el contrato inicia o termina dentro del mes.
     */
    public function calcularDiasTrabajados($fecha_inicio_contrato, $fecha_termino_contrato, $mes, $ano)
    {
        $dias_trabajados = 30;
        $primer_dia_mes = sprintf("%04d-%02d-01", $ano, $mes);
        $ultimo_dia_mes = date("Y-m-t", strtotime($primer_dia_mes));

        $inicio_ts = strtotime($fecha_inicio_contrato);
        $fin_ts = $fecha_termino_contrato ? strtotime($fecha_termino_contrato) : null;
        $inicio_mes_ts = strtotime($primer_dia_mes);
        $fin_mes_ts = strtotime($ultimo_dia_mes);

        // Caso 1: Contrato inicia este mes
        if ($inicio_ts >= $inicio_mes_ts) {
            $dia_inicio = (int)date('j', $inicio_ts);
            // Lógica comercial: 30 - dia_inicio + 1
            $dias_trabajados = 30 - $dia_inicio + 1;
        }

        // Caso 2: Contrato termina este mes
        if ($fin_ts && $fin_ts <= $fin_mes_ts) {
            $dia_fin = (int)date('j', $fin_ts);
            // Si inició antes de este mes, contamos desde el 1 hasta el fin
            if ($inicio_ts < $inicio_mes_ts) {
                // Si termina el 31, cuenta como 30
                if ($dia_fin == 31) {
                    $dias_trabajados = 30;
                } else {
                    $dias_trabajados = $dia_fin;
                }
            } else {
                // Si inició Y terminó en el mismo mes
                // Diferencia días reales + 1
                $diff = $fin_ts - $inicio_ts;
                $dias_reales = round($diff / (60 * 60 * 24)) + 1;
                $dias_trabajados = $dias_reales;
            }
        }

        // Ajuste febrero: Si es mes completo (inicia antes, termina despues), asegurar 30.
        // Ya está inicializado en 30, pero si febrero tiene 28/29, la lógica comercial mantiene 30 si trabajó todo el mes.

        // Return 
        return max(0, min(30, $dias_trabajados));
    }

    /**
     * Calcula los días de licencia médica que caen dentro del periodo activo (contrato) en el mes.
     * Excluye el día 31 de las licencias para ser consistente con la norma de 30 días.
     */
    public function calcularDiasLicencia($trabajador_id, $fecha_inicio_contrato, $fecha_termino_contrato, $mes, $ano)
    {
        $primer_dia = sprintf("%04d-%02d-01", $ano, $mes);
        $ultimo_dia = date("Y-m-t", strtotime($primer_dia));

        $inicio_mes_ts = strtotime($primer_dia);
        $fin_mes_ts = strtotime($ultimo_dia);

        $inicio_contrato_ts = strtotime($fecha_inicio_contrato);
        $fin_contrato_ts = $fecha_termino_contrato ? strtotime($fecha_termino_contrato) : null;

        // Definir el rango efectivo donde el trabajador "debería" estar trabajando
        $inicio_efectivo = max($inicio_contrato_ts, $inicio_mes_ts);
        $fin_efectivo = ($fin_contrato_ts && $fin_contrato_ts < $fin_mes_ts) ? $fin_contrato_ts : $fin_mes_ts;

        if ($inicio_efectivo > $fin_efectivo) return 0;

        // Buscar licencias que intercepten con el mes
        $stmt = $this->pdo->prepare("
            SELECT fecha_inicio, fecha_fin 
            FROM trabajador_licencias 
            WHERE trabajador_id = ? 
            AND fecha_inicio <= ? 
            AND fecha_fin >= ?
        ");
        $stmt->execute([$trabajador_id, $ultimo_dia, $primer_dia]);
        $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dias_descuento = 0;

        foreach ($licencias as $lic) {
            $l_ini = strtotime($lic['fecha_inicio']);
            $l_fin = strtotime($lic['fecha_fin']);

            // Intersección: Licencia vs Rango Efectivo
            $overlap_start = max($l_ini, $inicio_efectivo);
            $overlap_end = min($l_fin, $fin_efectivo);

            if ($overlap_start <= $overlap_end) {
                $current = $overlap_start;
                while ($current <= $overlap_end) {
                    $dia_numero = (int)date('j', $current);
                    if ($dia_numero != 31) {
                        $dias_descuento++;
                    }
                    $current = strtotime('+1 day', $current);
                }
            }
        }

        // Ajuste Febrero mes completo
        if ($mes == 2) {
            $dias_mes_feb = (int)date('t', $inicio_mes_ts);
            if ($dias_descuento == $dias_mes_feb) {
                $dias_descuento = 30;
            }
        }

        return $dias_descuento;
    }

    /**
     * Calcula sueldo proporcional en base a días trabajados (base 30).
     */
    public function calcularProporcional($monto_base, $dias_trabajados)
    {
        if ($dias_trabajados >= 30) return $monto_base;
        if ($dias_trabajados <= 0) return 0;

        return round(($monto_base / 30) * $dias_trabajados);
    }
}
