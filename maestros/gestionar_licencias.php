<?php
// maestros/gestionar_licencias.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// CONSTANTES & HELPERS
$TIPOS_LICENCIA = [
    'Enfermedad común',
    'Maternal',
    'Ley SANNA',
    'Accidente laboral',
    'Accidente trayecto',
    'Patología embarazo',
    'Enfermedad hijo menor',
    'Otros'
];

// OBTENER TRABAJADORES (FILTRADO POR IDOR)
// Solo trabajadores que pertenecen a empleadores de la EMPRESA_SISTEMA
$sqlTrab = "
SELECT t.id, t.nombre, t.rut
FROM trabajadores t
JOIN contratos c ON t.id = c.trabajador_id
JOIN empleadores e ON c.empleador_id = e.id
WHERE e.empresa_sistema_id = :sys_id
AND c.fecha_finiquito IS NULL -- Solo activos? O histórico? Mejor todos para permitir cargar historias.
GROUP BY t.id
ORDER BY t.nombre ASC
";
// Nota: Si un trabajador tiene múltiples contratos en distintas empresas del sistema, saldrá.
// Si un trabajador estuvo, se fue, y volvió, GROUP BY t.id lo consolida.

$stmtT = $pdo->prepare($sqlTrab);
$stmtT->execute(['sys_id' => ID_EMPRESA_SISTEMA]);
$trabajadores = $stmtT->fetchAll(PDO::FETCH_ASSOC);

// PROCESAR POST (CREAR / ELIMINAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'crear') {
                $trabajador_id = (int)$_POST['trabajador_id'];
                $fecha_inicio = $_POST['fecha_inicio'];
                $fecha_fin = $_POST['fecha_fin'];
                $tipo = $_POST['tipo_licencia'];
                if ($tipo === 'Otros') {
                    $tipo = trim($_POST['otro_tipo_licencia']);
                    if (empty($tipo)) throw new Exception("Debe especificar el tipo de licencia.");
                }
                $base_manual = !empty($_POST['base_imponible_manual']) ? (float)$_POST['base_imponible_manual'] : null;

                // AUTO-FOLIO
                // Generar Folio simple: LIC-YYYYMMDD-Random
                $folio = 'LIC-' . date('Ymd') . '-' . rand(1000, 9999);

                // VALIDAR SEGURIDAD (IDOR)
                $stmtCheck = $pdo->prepare("
SELECT COUNT(*) FROM trabajadores t
JOIN contratos c ON t.id = c.trabajador_id
JOIN empleadores e ON c.empleador_id = e.id
WHERE t.id = ? AND e.empresa_sistema_id = ?
");
                $stmtCheck->execute([$trabajador_id, ID_EMPRESA_SISTEMA]);
                if ($stmtCheck->fetchColumn() == 0) {
                    throw new Exception("Error de seguridad: Trabajador no pertenece a su sistema.");
                }

                $stmtIns = $pdo->prepare("INSERT INTO trabajador_licencias (trabajador_id, fecha_inicio, fecha_fin, tipo_licencia, folio, base_imponible_manual) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtIns->execute([$trabajador_id, $fecha_inicio, $fecha_fin, $tipo, $folio, $base_manual]);

                $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Licencia registrada correctamente. Folio: $folio"];
            } elseif ($_POST['action'] === 'editar') {
                if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
                    throw new Exception("Solo un administrador puede editar licencias.");
                }
                $id = (int)$_POST['id_licencia'];
                $fecha_inicio = $_POST['fecha_inicio'];
                $fecha_fin = $_POST['fecha_fin'];
                $tipo = $_POST['tipo_licencia'];
                if ($tipo === 'Otros') {
                    $tipo = trim($_POST['otro_tipo_licencia']);
                    if (empty($tipo)) throw new Exception("Debe especificar el tipo de licencia.");
                }
                $base_manual = !empty($_POST['base_imponible_manual']) ? (float)$_POST['base_imponible_manual'] : null;

                // VALIDAR IDOR
                $stmtCheck = $pdo->prepare("
                    SELECT l.id FROM trabajador_licencias l
                    JOIN trabajadores t ON l.trabajador_id = t.id
                    JOIN contratos c ON t.id = c.trabajador_id
                    JOIN empleadores e ON c.empleador_id = e.id
                    WHERE l.id = ? AND e.empresa_sistema_id = ?
                ");
                $stmtCheck->execute([$id, ID_EMPRESA_SISTEMA]);
                if ($stmtCheck->fetchColumn() === false) {
                    throw new Exception("No tiene permisos para editar esta licencia.");
                }

                // ACTUALIZAR (No cambiamos trabajador ni folio para simplificar, o sí? 
                // Mejor permitir solo fechas y tipo. Si quiere cambiar trabajador, que borre y cree nueva.)
                $stmtUpd = $pdo->prepare("UPDATE trabajador_licencias SET fecha_inicio = ?, fecha_fin = ?, tipo_licencia = ?, base_imponible_manual = ? WHERE id = ?");
                $stmtUpd->execute([$fecha_inicio, $fecha_fin, $tipo, $base_manual, $id]);

                $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Licencia actualizada correctamente."];
            } elseif ($_POST['action'] === 'eliminar') {
                if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
                    throw new Exception("Solo un administrador puede eliminar licencias.");
                }
                $id = (int)$_POST['id'];

                // VALIDAR IDOR AL BORRAR
                $stmtCheckDel = $pdo->prepare("
SELECT l.id FROM trabajador_licencias l
JOIN trabajadores t ON l.trabajador_id = t.id
JOIN contratos c ON t.id = c.trabajador_id
JOIN empleadores e ON c.empleador_id = e.id
WHERE l.id = ? AND e.empresa_sistema_id = ?
");
                $stmtCheckDel->execute([$id, ID_EMPRESA_SISTEMA]);
                if ($stmtCheckDel->fetchColumn() === false) {
                    throw new Exception("No tiene permisos para eliminar esta licencia.");
                }

                $stmtDel = $pdo->prepare("DELETE FROM trabajador_licencias WHERE id = ?");
                $stmtDel->execute([$id]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Licencia eliminada."];
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
        }

        header("Location: gestionar_licencias.php");
        exit;
    }
}

// LISTAR LICENCIAS (FILTRADO IDOR)
$sqlList = "
SELECT l.*, t.nombre as trabajador_nombre, t.rut
FROM trabajador_licencias l
JOIN trabajadores t ON l.trabajador_id = t.id
WHERE EXISTS (
SELECT 1 FROM contratos c
JOIN empleadores e ON c.empleador_id = e.id
WHERE c.trabajador_id = t.id AND e.empresa_sistema_id = :sys_id
)
ORDER BY l.fecha_inicio DESC
";
$stmtL = $pdo->prepare($sqlList);
$stmtL->execute(['sys_id' => ID_EMPRESA_SISTEMA]);
$licencias = $stmtL->fetchAll(PDO::FETCH_ASSOC);

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Gestión de Licencias Médicas</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaLicencia">
            <i class="fas fa-plus me-2"></i> Registrar Licencia
        </button>
    </div>

    <!-- Sección de Filtros Externa -->
    <!-- Sección de Filtros Externa (NUEVA) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-file-medical me-1"></i> Licencias Registradas</h6>

            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>

        <div class="collapse border-top bg-light p-3" id="filterPanel">
            <div class="row g-3">
                <div class="col-md-9">
                    <label class="form-label small fw-bold text-muted">Tipo Licencia:</label>
                    <select class="form-select form-select-sm" id="filterTipo" autocomplete="off">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-sm btn-outline-secondary w-100" id="btnClearFilters">
                        <i class="fas fa-times me-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Historial de Licencias</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable-es" id="tablaLicencias" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Trabajador</th>
                            <th>RUT</th>
                            <th>Tipo</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Días</th>
                            <th>Base Manual</th>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licencias as $lic):
                            $dias = (strtotime($lic['fecha_fin']) - strtotime($lic['fecha_inicio'])) / 86400 + 1;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($lic['folio']) ?></td>
                                <td><?= htmlspecialchars($lic['trabajador_nombre']) ?></td>
                                <td><?= $lic['rut'] ?></td>
                                <td><?= htmlspecialchars($lic['tipo_licencia']) ?></td>
                                <td><?= date('d/m/Y', strtotime($lic['fecha_inicio'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($lic['fecha_fin'])) ?></td>
                                <td class="text-center fw-bold"><?= round($dias) ?></td>
                                <td class="text-end">
                                    <?php if ($lic['base_imponible_manual']): ?>
                                        <span class="badge bg-warning text-dark">$<?= number_format($lic['base_imponible_manual'], 0, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-warning btn-editar me-1"
                                            data-id="<?= $lic['id'] ?>"
                                            data-trabajador="<?= $lic['trabajador_id'] ?>"
                                            data-fecha-inicio="<?= $lic['fecha_inicio'] ?>"
                                            data-fecha-fin="<?= $lic['fecha_fin'] ?>"
                                            data-tipo="<?= htmlspecialchars($lic['tipo_licencia']) ?>"
                                            data-base="<?= $lic['base_imponible_manual'] ?>"
                                            title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta licencia?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="id" value="<?= $lic['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Licencia -->
<div class="modal fade" id="modalNuevaLicencia" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Registrar Nueva Licencia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    <input type="hidden" name="id_licencia" id="licenciaId">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Trabajador</label>
                        <select class="form-select select2-worker" name="trabajador_id" required style="width:100%">
                            <option value="">Seleccione...</option>
                            <?php foreach ($trabajadores as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?> - <?= $t['rut'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipo de Licencia</label>
                        <select class="form-select" name="tipo_licencia" id="selectTipoLicencia" required>
                            <?php foreach ($TIPOS_LICENCIA as $tipo): ?>
                                <option value="<?= $tipo ?>"><?= $tipo ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2 d-none" id="divOtroTipo">
                            <input type="text" class="form-control" name="otro_tipo_licencia" placeholder="Especifique el tipo de licencia">
                        </div>
                    </div>

                    <!-- Folio Eliminado por Auto-Generación -->

                    <div class="mb-3 p-3 bg-light border rounded">
                        <label class="form-label fw-bold text-danger">Base Imponible Manual (Opcional)</label>
                        <input type="number" class="form-control" name="base_imponible_manual" step="0.01">
                        <div class="form-text text-muted small">
                            <i class="fas fa-exclamation-triangle"></i> Usar solo si el sistema no tiene historial de sueldos previo para este trabajador (ej. sistema nuevo).
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Licencia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // --- CONFIGURACIÓN DATATABLES ---
        var table = $('#tablaLicencias').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            orderCellsTop: true,
            fixedHeader: true,
            order: [
                [4, "desc"]
            ], // Ordenar por fecha inicio desc
            // Updated DOM
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',

            initComplete: function() {
                var api = this.api();

                // Styling Search
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar licencia...');
                $('.dataTables_length select').addClass('form-select shadow-sm');

                // Helper populateSelect
                function populateSelect(colIndex, selectId) {
                    var column = api.column(colIndex);
                    var select = $(selectId);

                    if (select.children('option').length <= 1) {
                        column.data().unique().sort().each(function(d, j) {
                            var cleanData = d;
                            if (typeof d === 'string' && d.indexOf('<') !== -1) {
                                cleanData = $('<div>').html(d).text().trim();
                            }
                            cleanData = (cleanData) ? String(cleanData).trim() : '';

                            if (cleanData !== '' && !select.find('option[value="' + cleanData + '"]').length) {
                                select.append('<option value="' + cleanData + '">' + cleanData + '</option>');
                            }
                        });
                    }
                }

                // Columna 3 es Tipo de Licencia
                populateSelect(3, '#filterTipo');
            }
        });

        // --- EXTERNAL FILTERS LOGIC ---

        // Custom filtering function
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                const fTipo = $('#filterTipo').val();
                const cellTipo = data[3] || ""; // Col 3 is Tipo

                if (fTipo && !cellTipo.includes(fTipo)) return false;

                return true;
            }
        );

        // Bindings
        $('#filterTipo').on('change', function() {
            table.draw();
        });

        $('#btnClearFilters').on('click', function() {
            $('#filterTipo').val('').trigger('change');

            // Clear Global Search Input
            $('.dataTables_filter input').val('');

            // Reset DataTable Search (Global + Columns)
            table.search('').columns().search('').draw();
        });

        // Inicializar Select2 con Fix de Modal
        $('.select2-worker').select2({
            dropdownParent: $('#modalNuevaLicencia'),
            placeholder: "Busque trabajador por nombre o RUT",
            allowClear: true,
            language: "es",
            width: '100%'
        });

        // Toggle "Otro" tipo
        $('#selectTipoLicencia').change(function() {
            if ($(this).val() === 'Otros') {
                $('#divOtroTipo').removeClass('d-none');
                $('input[name="otro_tipo_licencia"]').prop('required', true);
            } else {
                $('#divOtroTipo').addClass('d-none');
                $('input[name="otro_tipo_licencia"]').prop('required', false).val('');
            }
        });

        // EDITAR LICENCIA
        $('.btn-editar').click(function() {
            var id = $(this).data('id');
            var trabajador = $(this).data('trabajador');
            var inicio = $(this).data('fecha-inicio');
            var fin = $(this).data('fecha-fin');
            var tipo = $(this).data('tipo');
            var base = $(this).data('base');

            // Set Form Values
            $('#formAction').val('editar');
            $('#licenciaId').val(id);
            $('input[name="fecha_inicio"]').val(inicio);
            $('input[name="fecha_fin"]').val(fin);
            $('input[name="base_imponible_manual"]').val(base);

            // Set Worker (readonly in edit)
            $('.select2-worker').val(trabajador).trigger('change');
            $('.select2-worker').prop('disabled', true);
            // We need to send worker_id even if disabled? No, backend doesn't update it. 
            // But if 'crear' needs it, we are fine.

            // Set Tipo
            // Check if type exists in select options
            if ($("#selectTipoLicencia option[value='" + tipo + "']").length > 0) {
                $('#selectTipoLicencia').val(tipo).trigger('change'); // trigger change to hide/show 'otros'
            } else {
                // It's a custom type -> Set 'Otros' and fill input
                $('#selectTipoLicencia').val('Otros').trigger('change');
                $('input[name="otro_tipo_licencia"]').val(tipo);
            }

            $('.modal-title').text('Editar Licencia');
            $('#modalNuevaLicencia').modal('show');
        });

        // RESET MODAL ON CLOSE (volver a estado CREAR)
        $('#modalNuevaLicencia').on('hidden.bs.modal', function() {
            $('#formAction').val('crear');
            $('#licenciaId').val('');
            $('form')[0].reset();
            $('.select2-worker').val('').trigger('change');
            $('.select2-worker').prop('disabled', false); // Enable back
            $('#divOtroTipo').addClass('d-none');
            $('.modal-title').text('Registrar Nueva Licencia');
        });
    });
</script>