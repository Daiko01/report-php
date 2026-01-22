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
}
