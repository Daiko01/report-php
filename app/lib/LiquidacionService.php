<?php
require_once __DIR__ . '/CalculoPlanillaService.php';

class LiquidacionService {
    private $pdo;
    private $calculoPrevisional;
    
    // Preparar statements para optimizar
    private $stmt_utm;
    private $stmt_tramos;

    // Cache Data
    private $cache_utm_valor = null;
    private $cache_tramos = [];
    private $cache_cargado = false;

    const TOPE_GRATIFICACION = 203125; 

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->calculoPrevisional = new CalculoPlanillaService($pdo);

        // 1. Consulta INTELIGENTE de UTM
        $this->stmt_utm = $this->pdo->prepare("
            SELECT valor_utm 
            FROM utm_valores 
            WHERE (ano < :ano OR (ano = :ano AND mes <= :mes))
            ORDER BY ano DESC, mes DESC 
            LIMIT 1
        ");

        // 2. Consulta de Tramos
        $this->stmt_tramos = $this->pdo->prepare("SELECT * FROM tabla_impuesto_unico ORDER BY tramo ASC");
    }

    /**
     * Pre-carga datos globales para el mes indicado.
     * Esto evita hacer query de UTM y Tramos por cada trabajador.
     */
    public function setPeriodo($mes, $ano) {
        // 1. Cargar UTM
        $this->stmt_utm->execute([':ano' => $ano, ':mes' => $mes]);
        $val = $this->stmt_utm->fetchColumn();
        $this->cache_utm_valor = $val ? (int)$val : 0;

        // 2. Cargar Tramos
        $this->stmt_tramos->execute();
        $this->cache_tramos = $this->stmt_tramos->fetchAll(PDO::FETCH_ASSOC);

        // 3. Propagar carga al servicio dependiente
        $this->calculoPrevisional->cargarDatosGlobales($mes, $ano);

        $this->cache_cargado = true;
    }

    public function calcularLiquidacion($contrato, $variables, $mes, $ano) {
        
        // --- 1. DEFINIR BASES IMPONIBLES ---
        $sueldo_base = (int)$contrato['sueldo_imponible'];
        $dias_trabajados = (int)$variables['dias_trabajados'];
        
        if ($dias_trabajados < 30 && $dias_trabajados > 0) {
            $sueldo_base = round(($sueldo_base / 30) * $dias_trabajados);
        }

        $horas_extras = (int)($variables['horas_extras_monto'] ?? 0);
        $bonos_imponibles = (int)($variables['bonos_imponibles'] ?? 0);
        $comisiones = (int)($variables['comisiones'] ?? 0);

        $base_gratificacion = $sueldo_base + $horas_extras;
        $gratificacion = min(round($base_gratificacion * 0.25), self::TOPE_GRATIFICACION);

        $total_imponible = $sueldo_base + $gratificacion + $horas_extras + $bonos_imponibles + $comisiones;

        // --- 2. OBTENER DESCUENTOS PREVISIONALES ---
        $fila_simulada = [
            'trabajador_id' => $contrato['trabajador_id'],
            'sueldo_imponible' => $total_imponible, 
            'tipo_contrato' => $contrato['tipo_contrato'],
            'cotiza_cesantia_pensionado' => $variables['cotiza_cesantia_pensionado'] ?? 0
        ];

        // OPTIMIZACIÓN: Si viene el objeto trabajador completo en $contrato, lo pasamos
        $trabajador_inyectado = $contrato['trabajador_obj'] ?? null;

        $leyes_sociales = $this->calculoPrevisional->calcularFila($fila_simulada, $mes, $ano, $trabajador_inyectado);

        $afp = $leyes_sociales['descuento_afp'];
        $salud = $leyes_sociales['descuento_salud'];
        $cesantia = $leyes_sociales['seguro_cesantia'];
        $sindicato = $leyes_sociales['sindicato'];
        $asig_familiar = $leyes_sociales['asignacion_familiar_calculada'];
        $apv_adicional = (int)($variables['adicional_salud_apv'] ?? 0);

        // --- 3. CÁLCULO TRIBUTARIO DINÁMICO (IMPUESTO ÚNICO) ---
        // Base Tributable = Imponible - (Leyes Sociales Obligatorias + APV)
        $base_tributable = $total_imponible - ($afp + $salud + $cesantia + $apv_adicional);
        
        // Si la base es negativa, es 0
        $base_tributable = max(0, $base_tributable);

        // Llamada a la nueva función con lógica UTM
        $impuesto_unico = $this->calcularImpuestoUnico($base_tributable, $mes, $ano);


        // --- 4. HABERES NO IMPONIBLES ---
        $pacto_col = (int)$contrato['pacto_colacion'];
        $pacto_mov = (int)$contrato['pacto_movilizacion'];
        
        $colacion = ($dias_trabajados == 30) ? $pacto_col : round(($pacto_col / 30) * $dias_trabajados);
        $movilizacion = ($dias_trabajados == 30) ? $pacto_mov : round(($pacto_mov / 30) * $dias_trabajados);
        $viaticos = (int)($variables['viaticos'] ?? 0);
        
        $total_no_imponible = $colacion + $movilizacion + $viaticos + $asig_familiar;


        // --- 5. OTROS DESCUENTOS ---
        $anticipos = (int)($variables['anticipos'] ?? 0);
        $prestamos = (int)($variables['prestamos'] ?? 0);
        $otros_desc = (int)($variables['otros_descuentos'] ?? 0);


        // --- 6. TOTALES FINALES ---
        $total_haberes = $total_imponible + $total_no_imponible;
        $total_descuentos = $afp + $salud + $cesantia + $apv_adicional + $impuesto_unico + $sindicato + $anticipos + $prestamos + $otros_desc;
        $sueldo_liquido = $total_haberes - $total_descuentos;

        return [
            'sueldo_base' => $sueldo_base,
            'gratificacion_legal' => $gratificacion,
            'horas_extras_monto' => $horas_extras,
            'bonos_imponibles' => $bonos_imponibles,
            'comisiones' => $comisiones,
            'total_imponible' => $total_imponible,
            'descuento_afp' => $afp,
            'descuento_salud' => $salud,
            'seguro_cesantia' => $cesantia,
            'adicional_salud_apv' => $apv_adicional,
            'base_tributable' => $base_tributable,
            'impuesto_unico' => $impuesto_unico,
            'colacion' => $colacion,
            'movilizacion' => $movilizacion,
            'viaticos' => $viaticos,
            'asig_familiar' => $asig_familiar,
            'total_no_imponible' => $total_no_imponible,
            'anticipos' => $anticipos,
            'prestamos' => $prestamos,
            'sindicato' => $sindicato,
            'otros_descuentos' => $otros_desc,
            'total_haberes' => $total_haberes,
            'total_descuentos' => $total_descuentos,
            'sueldo_liquido' => $sueldo_liquido
        ];
    }

    /**
     * Calcula el Impuesto Único basado en UTM y tablas oficiales.
     */
    private function calcularImpuestoUnico($base_tributable, $mes, $ano) {
        if ($base_tributable <= 0) return 0;

        $valor_utm = 0;
        $tramos = [];

        if ($this->cache_cargado) {
            $valor_utm = $this->cache_utm_valor;
            $tramos = $this->cache_tramos;
        } else {
            // Fallback (Modo antiguo / edición simple)
            $this->stmt_utm->execute([':ano' => $ano, ':mes' => $mes]);
            $valor_utm = $this->stmt_utm->fetchColumn();
            
            $this->stmt_tramos->execute();
            $tramos = $this->stmt_tramos->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!$valor_utm) return 0;

        $impuesto = 0;

        // 3. Recorrer tramos y encontrar el correspondiente
        foreach ($tramos as $t) {
            // Convertir límites UTM a Pesos
            // Limite inferior en pesos
            $limite_inf_pesos = $t['limite_inferior_utm'] * $valor_utm;
            
            // Limite superior en pesos (Si es NULL, es infinito)
            $limite_sup_pesos = ($t['limite_superior_utm'] === null) ? PHP_FLOAT_MAX : ($t['limite_superior_utm'] * $valor_utm);

            // Verificar si la base cae en este tramo
            // Lógica: Base > Limite Inferior AND Base <= Limite Superior
            if ($base_tributable > $limite_inf_pesos && $base_tributable <= $limite_sup_pesos) {
                
                // Calcular Factor
                $impuesto_bruto = $base_tributable * $t['factor'];
                
                // Calcular Rebaja (Rebaja en UTM * Valor UTM)
                $rebaja_pesos = $t['rebaja_utm'] * $valor_utm;
                
                // Impuesto Final = (Base * Factor) - Rebaja
                $impuesto = $impuesto_bruto - $rebaja_pesos;
                
                break; // Ya encontramos el tramo, salir del bucle
            }
        }

        // Retornar entero (redondeado estándar, sin decimales para pago)
        return max(0, round($impuesto));
    }
}
?>