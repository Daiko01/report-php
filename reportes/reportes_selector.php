<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Lógica de Filtro (por GET)
$empleador_id = isset($_GET['empleador_id']) && $_GET['empleador_id'] != '' ? (int)$_GET['empleador_id'] : null;
$mes_inicio = isset($_GET['mes_inicio']) && $_GET['mes_inicio'] != '' ? (int)$_GET['mes_inicio'] : null;
$ano_inicio = isset($_GET['ano_inicio']) && $_GET['ano_inicio'] != '' ? (int)$_GET['ano_inicio'] : null;
$mes_termino = isset($_GET['mes_termino']) && $_GET['mes_termino'] != '' ? (int)$_GET['mes_termino'] : null;
$ano_termino = isset($_GET['ano_termino']) && $_GET['ano_termino'] != '' ? (int)$_GET['ano_termino'] : null;

// Verificar si hay filtro por período (todos los campos deben estar)
$tiene_filtro_periodo = ($mes_inicio !== null && $ano_inicio !== null && $mes_termino !== null && $ano_termino !== null);
$tiene_filtro = $tiene_filtro_periodo || $empleador_id !== null;

// Obtener lista de empleadores para el selector
try {
    $stmt_emp = $pdo->query("SELECT id, nombre, rut FROM empleadores ORDER BY nombre");
    $empleadores_lista = $stmt_emp->fetchAll();
} catch (PDOException $e) {
    $empleadores_lista = [];
}

try {
    // 3. Buscar reportes
    $sql = "SELECT 
                p.empleador_id, p.mes, p.ano, 
                e.nombre as empleador_nombre, 
                e.rut as empleador_rut,
                STR_TO_DATE(CONCAT(p.ano, '-', p.mes, '-01'), '%Y-%m-%d') as fecha_reporte
            FROM planillas_mensuales p
            JOIN empleadores e ON p.empleador_id = e.id
            WHERE 1=1";

    $params = [];
    
    // Filtro por empleador
    if ($empleador_id !== null) {
        $sql .= " AND p.empleador_id = :empleador_id";
        $params[':empleador_id'] = $empleador_id;
    }
    
    $sql .= " GROUP BY p.empleador_id, p.mes, p.ano, e.nombre, e.rut";
    
    // Aplicar filtro de rango de período si está completo
    if ($tiene_filtro_periodo) {
        $fecha_inicio = sprintf('%04d-%02d-01', $ano_inicio, $mes_inicio);
        $ultimo_dia_mes = date('t', strtotime(sprintf('%04d-%02d-01', $ano_termino, $mes_termino)));
        $fecha_termino = sprintf('%04d-%02d-%02d', $ano_termino, $mes_termino, $ultimo_dia_mes);
        
        $sql .= " HAVING fecha_reporte BETWEEN :fecha_inicio AND :fecha_termino";
        $params[':fecha_inicio'] = $fecha_inicio;
        $params[':fecha_termino'] = $fecha_termino;
    }

    // Siempre ordenar por más reciente primero
    $sql .= " ORDER BY p.ano DESC, p.mes DESC, e.nombre";

    // Si no hay filtro, mostrar solo los últimos 10
    if (!$tiene_filtro) {
        $sql .= " LIMIT 10";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportes_encontrados = $stmt->fetchAll();
} catch (PDOException $e) {
    $reportes_encontrados = [];
}

// Definir meses para uso en el formulario
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// 4. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<!-- CSS específico para reportes -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/reportes/reports.css">

<div class="reports-page">
    <div class="container-fluid px-4">
        <!-- Header -->
        <div class="card reports-header-card mb-4 fade-in">
            <div class="card-body">
                <h1 class="reports-title">
                    <i class="fas fa-file-pdf text-primary me-2"></i>
                    Visualización de Reportes
                </h1>
                <p class="reports-subtitle mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Gestiona y visualiza tus reportes de planillas previsionales
                </p>
            </div>
        </div>

        <!-- Filtro -->
        <div class="card filter-card mb-4 fade-in">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-filter text-primary me-2"></i>
                    Filtrar por Rango de Períodos
                </h6>
            </div>
            <div class="card-body">
                <form action="reportes_selector.php" method="GET" id="filtro-form">
                    <div class="row g-3">
                        <!-- Filtro por Empleador -->
                        <div class="col-md-12 col-lg-4">
                            <label for="empleador_id" class="form-label">
                                <i class="fas fa-building me-1"></i> Empleador
                            </label>
                            <select class="form-select select2-empleador" name="empleador_id" id="empleador_id">
                                <option value="">Todos los empleadores</option>
                                <?php
                                foreach ($empleadores_lista as $emp) {
                                    $selected = ($empleador_id == $emp['id']) ? 'selected' : '';
                                    echo "<option value=\"{$emp['id']}\" $selected>" . 
                                         htmlspecialchars($emp['nombre']) . ' (' . htmlspecialchars($emp['rut']) . ')' . 
                                         "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Período de Inicio -->
                        <div class="col-md-6 col-lg-2">
                            <label for="mes_inicio" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i> Mes Inicio
                            </label>
                            <select class="form-select select2-mes" name="mes_inicio" id="mes_inicio">
                                <option value="">Todos</option>
                                <?php
                                foreach ($meses as $num => $nombre) {
                                    $selected = ($mes_inicio == $num) ? 'selected' : '';
                                    echo "<option value=\"$num\" $selected>$nombre</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="ano_inicio" class="form-label">Año Inicio</label>
                            <select class="form-select select2-ano" name="ano_inicio" id="ano_inicio">
                                <option value="">Todos</option>
                                <?php
                                $ano_actual = date('Y');
                                for ($i = $ano_actual; $i >= $ano_actual - 10; $i--) {
                                    $selected = ($ano_inicio == $i) ? 'selected' : '';
                                    echo "<option value=\"$i\" $selected>$i</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Período de Término -->
                        <div class="col-md-6 col-lg-2">
                            <label for="mes_termino" class="form-label">
                                <i class="fas fa-calendar-check me-1"></i> Mes Término
                            </label>
                            <select class="form-select select2-mes" name="mes_termino" id="mes_termino">
                                <option value="">Todos</option>
                                <?php
                                foreach ($meses as $num => $nombre) {
                                    $selected = ($mes_termino == $num) ? 'selected' : '';
                                    echo "<option value=\"$num\" $selected>$nombre</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="ano_termino" class="form-label">Año Término</label>
                            <select class="form-select select2-ano" name="ano_termino" id="ano_termino">
                                <option value="">Todos</option>
                                <?php
                                for ($i = $ano_actual; $i >= $ano_actual - 10; $i--) {
                                    $selected = ($ano_termino == $i) ? 'selected' : '';
                                    echo "<option value=\"$i\" $selected>$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Aplicar Filtro
                        </button>
                        <?php if ($tiene_filtro): ?>
                            <a href="reportes_selector.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Opción de organización para descarga -->
                    <?php if (count($reportes_encontrados) > 0): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="organizacion_descarga" id="org_mes" value="mes" checked>
                                <label class="form-check-label" for="org_mes">
                                    <strong>Organizar por Mes/Año:</strong> Todos los empleadores de cada mes juntos (ej: Junio 2024/Empleador A, B, C...)
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="organizacion_descarga" id="org_empleador" value="empleador">
                                <label class="form-check-label" for="org_empleador">
                                    <strong>Organizar por Empleador:</strong> Todos los meses de cada empleador juntos (ej: Empleador A/Junio, Julio, Agosto...)
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Resultados -->
        <div class="card results-card fade-in">
            <div class="card-header results-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">
                        <i class="fas fa-list-ul text-primary me-2"></i>
                <?php 
                if ($tiene_filtro_periodo) {
                    echo "Resultados: " . $meses[$mes_inicio] . " $ano_inicio - " . $meses[$mes_termino] . " $ano_termino";
                } elseif ($empleador_id !== null) {
                    $emp_nombre = '';
                    foreach ($empleadores_lista as $e) {
                        if ($e['id'] == $empleador_id) {
                            $emp_nombre = $e['nombre'];
                            break;
                        }
                    }
                    echo "Reportes de: " . htmlspecialchars($emp_nombre);
                } else {
                    echo 'Últimos 10 Reportes Generados';
                }
                ?>
                        <?php if (count($reportes_encontrados) > 0): ?>
                            <span class="badge bg-primary ms-2">
                                <?php echo count($reportes_encontrados); ?>
                            </span>
                        <?php endif; ?>
                    </h6>
                </div>
                <button class="btn btn-success" id="btn-descargar-zip" <?php echo count($reportes_encontrados) == 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-file-archive me-2"></i> Descargar ZIP
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (count($reportes_encontrados) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-reports mb-0" id="tabla-reportes">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="select-all-reports">
                                        </div>
                                    </th>
                                    <th><i class="fas fa-building me-2"></i>Empleador</th>
                                    <th><i class="fas fa-id-card me-2"></i>RUT</th>
                                    <th><i class="fas fa-calendar-alt me-2"></i>Período</th>
                                    <th style="width: 120px; text-align: center;"><i class="fas fa-cog me-2"></i>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes_encontrados as $r): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input report-checkbox" type="checkbox"
                                                    value="<?php echo $r['empleador_id']; ?>"
                                                    data-mes="<?php echo $r['mes']; ?>"
                                                    data-ano="<?php echo $r['ano']; ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['empleador_nombre']); ?></strong>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($r['empleador_rut']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary badge-period">
                                                <?php echo $meses[$r['mes']] . ' ' . $r['ano']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="ver_pdf.php?id=<?php echo $r['empleador_id']; ?>&mes=<?php echo $r['mes']; ?>&ano=<?php echo $r['ano']; ?>"
                                                class="btn btn-sm btn-primary" target="_blank" title="Ver PDF">
                                                <i class="fas fa-eye me-1"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h5 class="empty-state-title">No se encontraron reportes</h5>
                        <p class="empty-state-text">
                            <?php 
                            if ($tiene_filtro_periodo) {
                                echo "No hay planillas para el rango de período seleccionado.";
                            } elseif ($empleador_id !== null) {
                                echo "No hay reportes disponibles para este empleador.";
                            } else {
                                echo "No hay reportes disponibles en los últimos 10 períodos.";
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// 5. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
$(document).ready(function() {
    // Inicializar Select2 para los filtros
    $('.select2-empleador, .select2-mes, .select2-ano').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: function() {
            return $(this).data('placeholder') || 'Seleccione...';
        },
        allowClear: true
    });

    // Inicializar DataTables para la tabla de reportes
    const table = $('#tabla-reportes').DataTable({
        language: {
            url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        order: [[3, 'desc'], [1, 'asc']], // Ordenar por período (desc) y empleador (asc)
        columnDefs: [
            { orderable: false, targets: [0, 4] } // Deshabilitar ordenamiento en checkbox y acción
        ],
        responsive: true
    });

    // Validación del formulario de filtro
    $('#filtro-form').on('submit', function(e) {
        const mes_inicio = $('#mes_inicio').val();
        const ano_inicio = $('#ano_inicio').val();
        const mes_termino = $('#mes_termino').val();
        const ano_termino = $('#ano_termino').val();
        
        // Validar filtro de período: si alguno está lleno, todos deben estar llenos
        const campos_periodo_llenos = [mes_inicio, ano_inicio, mes_termino, ano_termino].filter(v => v !== '' && v !== null).length;
        
        if (campos_periodo_llenos > 0 && campos_periodo_llenos < 4) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Campos incompletos',
                text: 'Si filtra por período, debe completar todos los campos (Mes Inicio, Año Inicio, Mes Término, Año Término) o dejar todos vacíos.',
                confirmButtonText: 'Entendido'
            });
            return false;
        }
        
        // Validar que fecha término sea mayor o igual a fecha inicio
        if (mes_inicio && ano_inicio && mes_termino && ano_termino) {
            const fecha_inicio = new Date(ano_inicio, mes_inicio - 1, 1);
            const fecha_termino = new Date(ano_termino, mes_termino - 1, 1);
            
            if (fecha_termino < fecha_inicio) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Rango inválido',
                    text: 'El período de término debe ser mayor o igual al período de inicio.',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
        }
    });

    // Seleccionar todos los reportes (compatible con DataTables)
    $('#select-all-reports').on('click', function() {
        const isChecked = $(this).prop('checked');
        // Seleccionar solo los checkboxes visibles (paginación de DataTables)
        table.rows({ search: 'applied' }).nodes().to$().find('.report-checkbox').prop('checked', isChecked);
        updateDownloadButton();
    });

    // Actualizar checkbox "seleccionar todos" cuando cambian los checkboxes individuales
    $('#tabla-reportes tbody').on('change', '.report-checkbox', function() {
        const totalVisible = table.rows({ search: 'applied' }).count();
        const checkedVisible = table.rows({ search: 'applied' }).nodes().to$().find('.report-checkbox:checked').length;
        $('#select-all-reports').prop('checked', totalVisible > 0 && checkedVisible === totalVisible);
        updateDownloadButton();
    });

    // Actualizar estado del botón de descarga (contar todos los checkboxes, no solo visibles)
    function updateDownloadButton() {
        const checkedCount = $('.report-checkbox:checked').length;
        const btn = $('#btn-descargar-zip');
        
        if (checkedCount > 0) {
            btn.prop('disabled', false);
            btn.html(`<i class="fas fa-file-archive me-2"></i> Descargar ZIP (${checkedCount})`);
        } else {
            btn.prop('disabled', true);
            btn.html('<i class="fas fa-file-archive me-2"></i> Descargar ZIP');
        }
    }

    // Actualizar contador cuando se selecciona/deselecciona un checkbox (delegación de eventos para DataTables)
    $('#tabla-reportes tbody').on('change', '.report-checkbox', function() {
        updateDownloadButton();
    });

    // Descargar ZIP
    $('#btn-descargar-zip').on('click', function() {
        const reportes = [];
        $('.report-checkbox:checked').each(function() {
            reportes.push({
                empleador_id: $(this).val(),
                mes: $(this).data('mes'),
                ano: $(this).data('ano')
            });
        });

        if (reportes.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Sin selección',
                text: 'Debes seleccionar al menos un reporte para descargar.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        // Obtener tipo de organización seleccionado
        const organizacion = $('input[name="organizacion_descarga"]:checked').val() || 'mes';

        Swal.fire({
            title: 'Generando archivo ZIP...',
            html: '<p>Esto puede tardar unos momentos, por favor espera.</p>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('<?php echo BASE_URL; ?>/ajax/descargar_zip.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    reportes: reportes,
                    organizacion: organizacion
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Error en el servidor');
                    });
                }
                const contentDisposition = response.headers.get('Content-Disposition');
                const filename = contentDisposition ? contentDisposition.split('filename=')[1].replace(/"/g, '') : 'Reportes.zip';
                return response.blob().then(blob => ({
                    blob,
                    filename
                }));
            })
            .then(({blob, filename}) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename || 'Reportes.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Descarga exitosa!',
                    text: `Se ha descargado el archivo ZIP con ${reportes.length} reporte${reportes.length > 1 ? 's' : ''}.`,
                    confirmButtonText: 'Perfecto'
                });
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al generar ZIP',
                    text: error.message || 'No se pudo generar el archivo ZIP. Por favor, intenta nuevamente.',
                    confirmButtonText: 'Entendido'
                });
            });
    });

    // Inicializar estado del botón
    updateDownloadButton();
});
</script>
