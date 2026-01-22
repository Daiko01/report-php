<?php
// planillas/dashboard_mensual.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Captura de filtros
$mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int)$_GET['mes'] : (int)date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$filtro_empleador = isset($_GET['empleador_id']) && $_GET['empleador_id'] !== '' ? (int)$_GET['empleador_id'] : null;

// Si el usuario elige "Ver todos los meses", podemos usar un valor especial como 0 o null
$ver_todos_los_meses = (isset($_GET['mes']) && $_GET['mes'] === 'todos');

$meses_nombres = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

try {
    // 1. Lista de empleadores para el filtro
    $stmt_emp_list = $pdo->prepare("SELECT id, nombre FROM empleadores WHERE empresa_sistema_id = ? ORDER BY nombre");
    $stmt_emp_list->execute([ID_EMPRESA_SISTEMA]);
    $empleadores_lista = $stmt_emp_list->fetchAll();

    // 2. Construcción de la consulta dinámica
    $where = " WHERE p.ano = :ano";
    $params = [':ano' => $ano];

    if (!$ver_todos_los_meses) {
        $where .= " AND p.mes = :mes";
        $params[':mes'] = $mes;
    }

    if ($filtro_empleador) {
        $where .= " AND p.empleador_id = :emp_id";
        $params[':emp_id'] = $filtro_empleador;
    }

    $sql = "
        SELECT 
            p.mes, p.ano,
            e.id as empleador_id, e.nombre, e.rut,
            COUNT(p.trabajador_id) as total_trabajadores,
            SUM(p.sueldo_imponible) as total_imponible,
            SUM(COALESCE(p.bonos_imponibles, 0)) as total_bonos,
            SUM(p.descuento_afp + p.descuento_salud + p.seguro_cesantia + p.sindicato) as total_descuentos
        FROM planillas_mensuales p
        JOIN empleadores e ON p.empleador_id = e.id
        $where
        GROUP BY p.empleador_id, p.mes, p.ano
        ORDER BY p.mes DESC, e.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error DB: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<style>
    :root {
        --primary-color: <?= COLOR_SISTEMA ?>;
    }

    .filter-bar {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e3e6f0;
    }

    .table-modern thead th {
        background-color: #f8f9fc;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 1px;
        color: #858796;
        border: none;
        padding: 15px 10px;
    }

    .table-modern td {
        vertical-align: middle;
        border-bottom: 1px solid #f1f1f1 !important;
        padding: 12px 10px;
    }

    .font-money {
        font-family: 'Roboto Mono', monospace;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .badge-periodo {
        background-color: #eaecf4;
        color: #4e73df;
        font-weight: bold;
        border-radius: 6px;
        padding: 5px 10px;
    }
</style>

<div class="container-fluid px-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 mt-3">
        <div>
            <h1 class="h4 mb-0 text-gray-800 fw-bold">Gestión de Planillas Generadas</h1>
            <p class="text-muted small mb-0">Listado detallado de cotizaciones y archivos PDF</p>
        </div>
        <div class="d-flex gap-2">
            <a href="generacion_masiva.php" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm rounded-pill">
                <i class="fas fa-magic me-1"></i> GENERACIÓN MASIVA
            </a>
        </div>
    </div>

    <div class="card filter-bar shadow-sm mb-4 border-0">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">EMPLEADOR</label>
                    <select name="empleador_id" class="form-select form-select-sm border-0 bg-light" onchange="this.form.submit()">
                        <option value="">Todos los empleadores</option>
                        <?php foreach ($empleadores_lista as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filtro_empleador == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">MES</label>
                    <select name="mes" class="form-select form-select-sm border-0 bg-light" onchange="this.form.submit()">
                        <option value="todos" <?= $ver_todos_los_meses ? 'selected' : '' ?>>--- TODOS LOS MESES ---</option>
                        <?php foreach ($meses_nombres as $num => $nombre): ?>
                            <option value="<?= $num ?>" <?= (!$ver_todos_los_meses && $num == $mes) ? 'selected' : '' ?>><?= $nombre ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold text-muted">AÑO</label>
                    <select name="ano" class="form-select form-select-sm border-0 bg-light" onchange="this.form.submit()">
                        <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= ($y == $ano) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-5 ms-auto text-end border-start ps-3">
                    <div class="d-inline-block text-start me-3 organization-options" style="vertical-align: middle;">
                        <div class="form-check form-check-inline small mb-0">
                            <input class="form-check-input" type="radio" name="org" id="orgM" value="mes" checked>
                            <label class="form-check-label text-muted" for="orgM">ZIP por Mes</label>
                        </div>
                        <div class="form-check form-check-inline small mb-0">
                            <input class="form-check-input" type="radio" name="org" id="orgE" value="empleador">
                            <label class="form-check-label text-muted" for="orgE">ZIP por Emp.</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-sm px-3 fw-bold shadow-sm" id="btnZipMasivo">
                        <i class="fas fa-file-archive me-1"></i> DESCARGAR ZIP
                    </button>
                    <a href="dashboard_mensual.php" class="btn btn-light btn-sm text-muted ms-1 border" title="Limpiar"><i class="fas fa-sync-alt"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern table-hover mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Periodo</th>
                            <th>Empleador / RUT</th>
                            <th class="text-center">Trabajadores</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen as $row):
                            $total_liquido_est = ($row['total_imponible'] + $row['total_bonos']) - $row['total_descuentos'];
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge-periodo"><?= $meses_nombres[$row['mes']] ?> <?= $row['ano'] ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nombre']) ?></div>
                                    <div class="small text-muted font-monospace"><?= htmlspecialchars($row['rut']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-primary border rounded-pill px-3 fw-bold"><?= $row['total_trabajadores'] ?></span>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="../reportes/ver_pdf.php?id=<?= $row['empleador_id'] ?>&mes=<?= $row['mes'] ?>&ano=<?= $row['ano'] ?>"
                                            target="_blank" class="btn btn-outline-danger btn-sm border-0" title="Ver PDF">
                                            <i class="fas fa-file-pdf fa-lg"></i>
                                        </a>
                                        <form action="cargar_grid.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="empleador_id" value="<?= $row['empleador_id'] ?>">
                                            <input type="hidden" name="mes" value="<?= $row['mes'] ?>">
                                            <input type="hidden" name="ano" value="<?= $row['ano'] ?>">
                                            <input type="hidden" name="source" value="dashboard">
                                            <button type="submit" class="btn btn-outline-primary btn-sm border-0" title="Editar Planilla">
                                                <i class="fas fa-edit fa-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($resumen)): ?>
                            <!-- DataTables maneja el estado vacío automáticamente -->
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    // Inyectar datos de PHP a JS para el reporte masivo
    // Inyectar datos de PHP a JS para el reporte masivo
    const resumenData = <?php echo json_encode($resumen); ?>;
    const DOWN_ZIP_URL = '<?php echo BASE_URL; ?>/ajax/descargar_zip.php';
    const verTodosLosMeses = <?php echo json_encode($ver_todos_los_meses); ?>;
    const mesActual = <?php echo json_encode($mes); ?>;
    const anoActual = <?php echo json_encode($ano); ?>;
    const mesesNombres = <?php echo json_encode($meses_nombres); ?>;

    $(document).ready(function() {
        if (!verTodosLosMeses) {
            $('.organization-options').hide();
        }

        $('#dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            pageLength: 25,
            dom: 'rtip',
            ordering: true,
            order: [
                [0, 'desc']
            ] // Ordenar por periodo más reciente
        });

        // Lógica de descarga ZIP Real
        $('#btnZipMasivo').click(function() {
            if (resumenData.length === 0) {
                Swal.fire('Atención', 'No hay datos en la tabla para generar el reporte.', 'warning');
                return;
            }

            // Si no es "todos los meses", forzamos organización por 'mes' (para estructura simple) o lo que corresponda en backend.
            // Pero el usuario pidió: "si es solo un mes especifico... se descargara con el nombre BP Mes y año.zip"
            // Backend espera 'mes' o 'empleador'. Si enviamos 'mes', crea carpeta Mes_Ano/archivo.
            // Si es un solo mes, quizás queramos solo los archivos en la raíz del ZIP?
            // El backend actual siempre crea carpeta. Ajustemos solo el nombre del ZIP aquí por ahora.
            const org = verTodosLosMeses ? $('input[name="org"]:checked').val() : 'mes';

            Swal.fire({
                title: 'Generando ZIP...',
                html: 'Esto puede tomar unos segundos.<br>Por favor espere.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(DOWN_ZIP_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        reportes: resumenData,
                        organizacion: org
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Error en el servidor');
                        });
                    }
                    return response.blob();
                })
                .then(blob => {
                    Swal.close();
                    // Crear link de descarga
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    // Intentar obtener nombre del header o usar default
                    let filename = '';
                    if (!verTodosLosMeses) {
                        // Nombre específico para un mes: "BP [Mes] [Año].zip"
                        // mesesNombres es objeto JS {1:'Enero'...}, pero PHP json_encode array asociativo -> JS Object
                        filename = `BP ${mesesNombres[mesActual]} ${anoActual}.zip`;
                    } else {
                        // Nombre genérico para todos los meses
                        filename = `Reportes_${org === 'empleador' ? 'PorEmpleador' : 'PorMes'}_${new Date().toISOString().slice(0,10)}.zip`;
                    }
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo generar el ZIP: ' + error.message, 'error');
                });
        });
    });
</script>