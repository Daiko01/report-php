<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Validate ID
if (!isset($_GET['id'])) {
    header('Location: ingreso_guia.php');
    exit;
}
$guia_id = (int)$_GET['id'];

// Get Data
$stmt = $pdo->prepare("SELECT * FROM produccion_buses WHERE id = ?");
$stmt->execute([$guia_id]);
$guia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guia) {
    die("Guía no encontrada.");
}

// Get Details (Pecera)
$stmtDet = $pdo->prepare("SELECT * FROM produccion_detalle_boletos WHERE guia_id = ?");
$stmtDet->execute([$guia_id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Index details by tariff for easier population
$peceraMap = [];
foreach ($detalles as $d) {
    $peceraMap[$d['tarifa']] = $d;
}

// Cargar Empleadores Iniciales
$empleadores = $pdo->query("SELECT id, nombre FROM empleadores WHERE empresa_sistema_id = " . ID_EMPRESA_SISTEMA . " ORDER BY nombre")->fetchAll();

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<style>
    /* Estilos Específicos para la "Pecera" y Voucher */
    .input-pecera {
        font-weight: bold;
        text-align: center;
        color: #4e73df;
    }

    .input-pecera:focus {
        background-color: #f8f9fc;
        border-color: #4e73df;
    }

    .total-fila {
        font-weight: bold;
        text-align: right;
    }

    .bg-readonly {
        background-color: #eaecf4 !important;
        color: #6e707e;
        cursor: not-allowed;
    }

    /* Hide print area in edit mode usually */
    @media print {
        #voucherArea {
            display: none !important;
        }
    }
</style>

<div class="container-fluid">
    <?php
    $estado = $guia['estado'] ?? 'Abierto';
    $isClosed = ($estado === 'Cerrada');
    $isAdmin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
    $readonlyAttr = $isClosed ? 'disabled' : '';
    ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit text-warning me-2"></i>Editar Guía #<?= $guia['nro_guia'] ?>
            <?php if ($isClosed): ?>
                <span class="badge bg-secondary ms-2"><i class="fas fa-lock me-1"></i> CERRADA</span>
            <?php else: ?>
                <span class="badge bg-success ms-2"><i class="fas fa-check-circle me-1"></i> ABIERTA</span>
            <?php endif; ?>
        </h1>
        <a href="ingreso_guia.php" class="btn btn-secondary btn-sm shadow-sm"><i class="fas fa-arrow-left me-1"></i> Cancelar</a>
    </div>

    <!-- Alert for Closed State -->
    <?php if ($isClosed): ?>
        <div class="alert alert-secondary border-left-secondary shadow-sm" role="alert">
            <h4 class="alert-heading"><i class="fas fa-lock"></i> Guía Cerrada</h4>
            <p class="mb-0">Esta guía se encuentra cerrada y no puede ser modificada.
                <?php if ($isAdmin): ?>
                    Como administrador, puedes reabrirla para realizar correcciones.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <form id="formGuia" autocomplete="off">
        <!-- Hidden ID for Update -->
        <input type="hidden" name="guia_id" value="<?= $guia['id'] ?>">

        <div class="row">
            <!-- COLUMNA IZQUIERDA: DATOS MAQUINA & CHOFER -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-gradient-warning">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-bus me-1"></i> 1. Identificación</h6>
                    </div>
                    <div class="card-body">
                        <!-- FECHA Y N° GUIA -->
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="small fw-bold">Fecha</label>
                                <input type="date" class="form-control" name="fecha" id="fecha" value="<?= $guia['fecha'] ?>" required <?= $readonlyAttr ?>>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold">N° Guía</label>
                                <input type="number" class="form-control fw-bold text-center" name="nro_guia" value="<?= $guia['nro_guia'] ?>" required <?= $readonlyAttr ?>>
                            </div>
                        </div>

                        <!-- EMPLEADOR - PRE LOADED VIA JS OR HARDCODED -->
                        <!-- To keep it simple, we will trigger change event in JS to load buses -->
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">A. Empleador</label>
                            <!-- We find bus first to know employer -->
                            <?php
                            $bus = $pdo->query("SELECT empleador_id FROM buses WHERE id = " . $guia['bus_id'])->fetch();
                            $emp_select_id = $bus['empleador_id'];
                            ?>
                            <select class="form-select select2" id="empleador_id" name="empleador_id" required <?= $readonlyAttr ?>>
                                <?php foreach ($empleadores as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= $e['id'] == $emp_select_id ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- BUS -->
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">B. Bus</label>
                            <select class="form-select select2" id="bus_id" name="bus_id" required disabled>
                                <!-- Populated by JS, handled specifically for edit mode -->
                            </select>
                        </div>

                        <!-- CONDUCTOR -->
                        <div class="mb-2">
                            <label class="small fw-bold text-primary">C. Conductor</label>
                            <select class="form-select select2" id="conductor_id" name="conductor_id" required <?= $readonlyAttr ?>>
                                <!-- Populated by JS -->
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA CENTRAL: LA PECERA -->
            <div class="col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-ticket-alt me-1"></i> 2. Detalle Boletos</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0" id="tablaPecera">
                            <thead class="bg-light text-center small text-muted">
                                <tr>
                                    <th width="15%">Tarifa</th>
                                    <th width="25%">Inicio</th>
                                    <th width="25%">Fin</th>
                                    <th width="35%">Total ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $tarifas = [140, 270, 430, 500, 820, 1000];
                                foreach ($tarifas as $t):
                                    $inicio = $peceraMap[$t]['folio_inicio'] ?? '';
                                    $fin = $peceraMap[$t]['folio_fin'] ?? '';
                                ?>
                                    <tr data-tarifa="<?= $t ?>">
                                        <td class="align-middle text-center fw-bold bg-light"><?= $t ?></td>
                                        <td><input type="number" class="form-control form-control-sm input-pecera folio-inicio" name="folios[<?= $t ?>][inicio]" value="<?= $inicio ?>" <?= $readonlyAttr ?>></td>
                                        <td><input type="number" class="form-control form-control-sm input-pecera folio-fin" name="folios[<?= $t ?>][fin]" value="<?= $fin ?>" <?= $readonlyAttr ?>></td>
                                        <td class="align-middle text-end pe-3 text-dark fw-bold total-row-val">0</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">TOTAL:</td>
                                    <td class="text-end fw-bold text-success pe-3" id="displayTotalIngreso">$ 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA: EGRESOS -->
            <div class="col-lg-3">
                <div class="card shadow mb-4 border-left-danger">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-danger">3. Egresos</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $gastos = [
                            'gasto_administracion' => 'Admin.',
                            'gasto_imposiciones' => 'Imposiciones',
                            'gasto_boletos' => 'Boletos',
                            'gasto_aseo' => 'Aseo',
                            'gasto_viatico' => 'Viático',
                            'gasto_varios' => 'Varios',
                            'gasto_petroleo' => 'Petróleo'
                        ];
                        foreach ($gastos as $key => $label): ?>
                            <div class="mb-2">
                                <label class="small fw-bold"><?= $label ?></label>
                                <input type="number" class="form-control form-control-sm input-gasto" name="<?= $key ?>" value="<?= $guia[$key] ?>" <?= $readonlyAttr ?>>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 pt-2">
                            <?php if (!$isClosed): ?>
                                <!-- BOTONES ESTADO ABIERTO -->
                                <button type="button" class="btn btn-warning fw-bold text-dark shadow-sm" onclick="submitForm('guardar')">
                                    <i class="fas fa-save me-2"></i> ACTUALIZAR
                                </button>
                                <!-- Logica de cierre movida al listado principal -->
                            <?php else: ?>
                                <!-- BOTONES ESTADO CERRADO -->
                                <?php if ($isAdmin || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'contador')): ?>
                                    <button type="button" class="btn btn-danger fw-bold shadow-sm" onclick="submitForm('reabrir')">
                                        <i class="fas fa-unlock me-2"></i> REABRIR GUÍA
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // PRE-LOAD LOGIC
        const busIdInit = <?= $guia['bus_id'] ?>;
        const driverIdInit = <?= $guia['conductor_id'] ?>;

        // 1. Load Drivers
        fetch('../ajax/get_todos_conductores.php').then(r => r.json()).then(data => {
            let opts = '';
            data.forEach(d => opts += `<option value="${d.id}" ${d.id == driverIdInit ? 'selected' : ''}>${d.nombre}</option>`);
            $('#conductor_id').html(opts);
        });

        // 2. Load Buses for current employer
        const empId = $('#empleador_id').val();
        const isReadOnly = <?= $isClosed ? 'true' : 'false' ?>;
        fetch(`../ajax/get_buses_por_empleador.php?empleador_id=${empId}`).then(r => r.json()).then(buses => {
            let opts = '';
            buses.forEach(b => opts += `<option value="${b.id}" ${b.id == busIdInit ? 'selected' : ''}>${b.numero_maquina}</option>`);
            $('#bus_id').html(opts);
            if (!isReadOnly) $('#bus_id').prop('disabled', false); // Enable only if not read only
        });

        // Calc initials
        setTimeout(calculateTotals, 500);

        // --- CALCULATION LOGIC (Same as Create) ---
        function calculateTotals() {
            let grandTotal = 0;
            $('#tablaPecera tbody tr').each(function() {
                const $tr = $(this);
                const tarifa = parseInt($tr.data('tarifa'));
                const inicio = parseInt($tr.find('.folio-inicio').val()) || 0;
                const fin = parseInt($tr.find('.folio-fin').val()) || 0;
                let total = 0;
                if (fin >= inicio && inicio > 0) total = ((fin - inicio) + 1) * tarifa;
                $tr.find('.total-row-val').text(total > 0 ? '$ ' + total.toLocaleString('es-CL') : '0');
                grandTotal += total;
            });
            $('#displayTotalIngreso').text('$ ' + grandTotal.toLocaleString('es-CL'));
        }

        $(document).on('input', '.folio-inicio, .folio-fin', calculateTotals);

        // --- SAVE LOGIC ---
        window.submitForm = function(accion) {

            let titulo = 'Actualizando...';
            let confirmMsg = '';

            if (accion === 'cerrar') {
                titulo = 'Cerrando Guía...';
                confirmMsg = '¿Estás seguro de cerrar esta guía? No podrás editarla nuevamente a menos que seas Administrador.';
            } else if (accion === 'reabrir') {
                titulo = 'Reabriendo Guía...';
                confirmMsg = '¿Reabrir esta guía para edición?';
            }

            if (confirmMsg) {
                Swal.fire({
                    title: 'Confirmar Acción',
                    text: confirmMsg,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, proceder',
                    cancelButtonText: 'Cancelar'
                }).then((r) => {
                    if (r.isConfirmed) processSubmit(accion, titulo);
                });
            } else {
                processSubmit(accion, titulo);
            }
        };

        function processSubmit(accion, titulo) {
            // Enable ALL fields to ensure they are sent (Fecha, Nro Guia, Bus, Conductor, etc are disabled when closed)
            $('#formGuia :input').prop('disabled', false);

            const formData = new FormData($('#formGuia')[0]);
            formData.append('accion', accion);
            formData.append('mode', 'update');

            // Re-disable if not reopening, to maintain UI state (optional but good UI)
            // But if we are reloading page on success, it doesn't matter much.
            if (accion !== 'reabrir') {
                // Check logic: if it was closed, it should be disabled.
            }

            Swal.fire({
                title: titulo,
                didOpen: () => Swal.showLoading()
            });

            fetch('guardar_guia_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.text()) // Use text() first to debug if JSON fails
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Server Error:", text);
                        throw new Error("Respuesta del servidor inválida.");
                    }
                })
                .then(res => {
                    if (res.success) {
                        Swal.fire('Éxito', 'Operación realizada correctamente.', 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Ocurrió un error al procesar la solicitud: ' + err.message, 'error');
                });
        }
    });
</script>