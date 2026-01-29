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
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit text-warning me-2"></i>Editar Guía #<?= $guia['nro_guia'] ?></h1>
        <a href="ingreso_guia.php" class="btn btn-secondary btn-sm shadow-sm"><i class="fas fa-arrow-left me-1"></i> Cancelar</a>
    </div>

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
                                <input type="date" class="form-control" name="fecha" id="fecha" value="<?= $guia['fecha'] ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold">N° Guía</label>
                                <input type="number" class="form-control fw-bold text-center" name="nro_guia" value="<?= $guia['nro_guia'] ?>" required>
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
                            <select class="form-select select2" id="empleador_id" name="empleador_id" required>
                                <?php foreach ($empleadores as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= $e['id'] == $emp_select_id ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- BUS -->
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">B. Bus</label>
                            <select class="form-select select2" id="bus_id" name="bus_id" required>
                                <!-- Populated by JS -->
                            </select>
                        </div>

                        <!-- CONDUCTOR -->
                        <div class="mb-2">
                            <label class="small fw-bold text-primary">C. Conductor</label>
                            <select class="form-select select2" id="conductor_id" name="conductor_id" required>
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
                                        <td><input type="number" class="form-control form-control-sm input-pecera folio-inicio" name="folios[<?= $t ?>][inicio]" value="<?= $inicio ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm input-pecera folio-fin" name="folios[<?= $t ?>][fin]" value="<?= $fin ?>"></td>
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
                                <input type="number" class="form-control form-control-sm input-gasto" name="<?= $key ?>" value="<?= $guia[$key] ?>">
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 pt-2">
                            <button type="button" class="btn btn-warning fw-bold text-dark shadow-sm" id="btnGuardar">
                                <i class="fas fa-save me-2"></i> ACTUALIZAR
                            </button>
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
        fetch(`../ajax/get_buses_por_empleador.php?empleador_id=${empId}`).then(r => r.json()).then(buses => {
            let opts = '';
            buses.forEach(b => opts += `<option value="${b.id}" ${b.id == busIdInit ? 'selected' : ''}>${b.numero_maquina}</option>`);
            $('#bus_id').html(opts);
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
        $('#btnGuardar').click(function() {
            // Reuse same process endpoint? Yes, it supports ON DUPLICATE KEY UPDATE based on ID if we pass it properly? 
            // Actually, update process endpoint to handle Explicit Update if 'guia_id' is present?
            // Wait, 'guardar_guia_process.php' uses `INSERT ... ON DUPLICATE KEY UPDATE`. 
            // It relies on (bus_id, fecha, nro_guia) constraint OR primary key.
            // It doesn't check 'id'. 
            // If I change the Nro Guia or Date in Edit, it might create a new one.
            // Risk: editing 'nro_guia' to a new number makes a new record.
            // We should fix 'guardar_guia_process.php' to respect 'guia_id' if sent for UPDATE.

            const formData = new FormData($('#formGuia')[0]);
            // Add 'action' or 'mode'
            formData.append('mode', 'update');

            Swal.fire({
                title: 'Actualizando...',
                didOpen: () => Swal.showLoading()
            });

            fetch('guardar_guia_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Éxito', 'Guía actualizada', 'success').then(() => {
                            window.location.href = 'ingreso_guia.php';
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
        });
    });
</script>