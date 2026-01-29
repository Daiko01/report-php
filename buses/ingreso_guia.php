<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Cargar Empleadores Iniciales
$empleadores = $pdo->query("SELECT id, nombre FROM empleadores WHERE empresa_sistema_id = " . ID_EMPRESA_SISTEMA . " ORDER BY nombre")->fetchAll();

require_once dirname(__DIR__) . '/app/includes/header.php';
?>


<style>
    /* Estilos Específicos para la "Pecera" */
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
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register text-primary me-2"></i>Ingreso de Guía Diaria</h1>
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] != 'recaudador'): ?>
        <a href="cierre_mensual.php" class="btn btn-secondary btn-sm shadow-sm"><i class="fas fa-arrow-left me-1"></i> Ir a Cierres</a>
    <?php endif; ?>
</div>

<form id="formGuia" autocomplete="off">
    <div class="row">
        <!-- COLUMNA IZQUIERDA: DATOS MAQUINA & CHOFER -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-gradient-primary">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-bus me-1"></i> 1. Identificación</h6>
                </div>
                <div class="card-body">
                    <!-- FECHA Y N° GUIA -->
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Fecha</label>
                            <input type="date" class="form-control" name="fecha" id="fecha" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">N° Guía</label>
                            <input type="number" class="form-control fw-bold text-center" name="nro_guia" required>
                        </div>
                    </div>

                    <!-- EMPLEADOR -->
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">A. Empleador (Dueño)</label>
                        <select class="form-select select2" id="empleador_id" name="empleador_id" required>
                            <option value="" selected>Seleccione...</option>
                            <?php foreach ($empleadores as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- BUS -->
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">B. Bus (N° Máquina)</label>
                        <select class="form-select select2" id="bus_id" name="bus_id" disabled required>
                            <option value="">Primero seleccione empleador...</option>
                        </select>
                    </div>

                    <!-- PATENTE (Readonly) -->
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Patente</label>
                        <input type="text" class="form-control bg-readonly fw-bold text-center spacing-2" id="patente_display" readonly>
                    </div>

                    <hr>

                    <!-- CONDUCTOR -->
                    <div class="mb-2">
                        <label class="small fw-bold text-primary">C. Conductor (Todos)</label>
                        <select class="form-select select2" id="conductor_id" name="conductor_id" disabled required>
                            <option value="">Cargando conductores...</option>
                        </select>
                        <small class="text-xs text-muted d-block mt-1">
                            <i class="fas fa-info-circle"></i> Se listan todos los activos (incluso sin contrato vigente c/empleador).
                        </small>
                    </div>
                </div>
            </div>

        </div>

        <!-- COLUMNA CENTRAL: LA PECERA -->
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-ticket-alt me-1"></i> 2. Detalle de Boletos</h6>
                    <span class="badge bg-secondary cursor-pointer" onclick="recargarFolios()" title="Recargar últimos folios"><i class="fas fa-sync-alt"></i></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0" id="tablaPecera">
                        <thead class="bg-light text-center small text-muted">
                            <tr>
                                <th width="15%">Tarifa</th>
                                <th width="25%">Inicio Serie</th>
                                <th width="25%">Fin Serie</th>
                                <th width="35%">Total ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tarifas = [140, 270, 430, 500, 820, 1000];
                            foreach ($tarifas as $t):
                            ?>
                                <tr data-tarifa="<?= $t ?>">
                                    <td class="align-middle text-center fw-bold bg-light"><?= $t ?></td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm input-pecera folio-inicio" name="folios[<?= $t ?>][inicio]" placeholder="0">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm input-pecera folio-fin" name="folios[<?= $t ?>][fin]" placeholder="0">
                                    </td>
                                    <td class="align-middle text-end pe-3 text-dark fw-bold total-row-val">0</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold pt-3">TOTAL INGRESOS:</td>
                                <td class="text-end fw-bold text-success fs-5 pt-3 pe-3" id="displayTotalIngreso">$ 0</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end small text-muted fst-italic">Proporción Conductor (22%):</td>
                                <td class="text-end small text-muted fst-italic pe-3" id="displayPropConductor">$ 0</td>
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
                    <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-minus-circle me-1"></i> 3. Egresos</h6>
                </div>
                <div class="card-body">
                    <!-- Campos de Gastos -->
                    <div class="mb-2">
                        <label class="small fw-bold">Admin.</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_administracion" placeholder="0">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Imposiciones</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_imposiciones" placeholder="0">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Boletos</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_boletos" placeholder="0">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Aseo</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_aseo" placeholder="0">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Viático</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_viatico" placeholder="0">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Otros / Varios</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_varios" placeholder="0">
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Petróleo</label>
                        <input type="number" class="form-control form-control-sm input-gasto" name="gasto_petroleo" placeholder="0">
                    </div>

                    <div class="d-grid gap-2 pt-2">
                        <button type="button" class="btn btn-primary fw-bold shadow-sm" id="btnGuardar">
                            <i class="fas fa-save me-2"></i> GUARDAR GUÍA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- HISTORIAL RÁPIDO (Últimas 10) -->
<div class="card shadow mb-4">
    <div class="card-header py-2 bg-gray-200 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark small text-uppercase">Últimos Ingresos</h6>
        <input type="date" id="filtroFechaHistorial" class="form-control form-control-sm w-auto" value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 small" id="historialRapido">
                <thead>
                    <tr>
                        <th class="text-start">Fecha</th>
                        <th class="text-start">N° Guía</th>
                        <th class="text-start">Bus</th>
                        <th class="text-start">Dueño</th>
                        <th class="text-start">Conductor</th>
                        <th class="text-start">Ingreso</th>
                        <th class="text-start">Líquido</th>
                        <th class="text-start">Acción</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    <!-- Loaded via JS -->
                    <tr>
                        <td colspan="7" class="text-muted py-2">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>



<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {

        // --- 1. SETUP INICIAL ---
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Cargar todos los conductores al inicio
        fetch('../ajax/get_todos_conductores.php')
            .then(r => r.json())
            .then(data => {
                let opts = '<option value="">Seleccione Conductor...</option>';
                data.forEach(d => {
                    const empName = d.empleador_nombre ? d.empleador_nombre : 'Sin Contrato Activo';
                    const empId = d.empleador_id ? d.empleador_id : 0;
                    opts += `<option value="${d.id}" data-emp-id="${empId}" data-emp-name="${empName}">${d.nombre} (${d.rut}) - [${empName}]</option>`;
                });
                $('#conductor_id').html(opts);
            });

        // Advertencia Cruce Empleadores
        $('#conductor_id').on('change', function() {
            const selectedOpt = $(this).find(':selected');
            const driverEmpId = selectedOpt.data('emp-id');
            const driverEmpName = selectedOpt.data('emp-name');
            const busEmpId = $('#empleador_id').val();

            // Si hay conductor seleccionado, hay dueño seleccionado, y NO coinciden (y el conductor SI tiene contrato con alguien mas)
            if (driverEmpId && busEmpId && driverEmpId != busEmpId && driverEmpId != 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Advertencia de Vinculación',
                    html: `Estás asignando un conductor (<b>${driverEmpName}</b>) a una máquina de otro dueño.<br>¿Confirmas que es un trabajo de <b>Excedente/Prestado</b>?`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, Continuar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        $('#conductor_id').val('').trigger('change');
                    }
                });
            }
        });

        // --- 2. LOGICA REACTIVA MAQUINA ---
        let currentBusId = 0;

        $('#empleador_id').on('change', function() {
            const empId = $(this).val();
            const $bus = $('#bus_id');

            $bus.html('<option>Cargando...</option>').prop('disabled', true);
            $('#patente_display').val('');
            $('.folio-inicio').val(''); // Limpiar folios
            calculateTotals(); // Reset

            if (empId) {
                fetch(`../ajax/get_buses_por_empleador.php?empleador_id=${empId}`)
                    .then(r => r.json())
                    .then(buses => {
                        let opts = '<option value="">Seleccione Bus...</option>';
                        buses.forEach(b => {
                            opts += `<option value="${b.id}" data-patente="${b.patente}">${b.numero_maquina}</option>`;
                        });
                        $bus.html(opts).prop('disabled', false);
                    });
            }
        });

        $('#bus_id').on('change', function() {
            const busId = $(this).val();
            const patente = $(this).find(':selected').data('patente');

            $('#patente_display').val(patente || '');
            currentBusId = busId;

            if (busId) {
                recargarFolios();
                $('#conductor_id').prop('disabled', false); // Enable Conductor
            } else {
                $('#conductor_id').prop('disabled', true); // Disable if no bus
            }
        });

        window.recargarFolios = function() {
            if (!currentBusId) return;

            // Efecto visual de carga
            $('.folio-inicio').addClass('bg-warning bg-opacity-10');

            fetch(`../ajax/get_ultimos_folios.php?bus_id=${currentBusId}`)
                .then(r => r.json())
                .then(folios => {
                    // Iterar la respuesta y llenar
                    for (const [tarifa, folioFin] of Object.entries(folios)) {
                        // Buscar input de inicio para esta tarifa
                        const $tr = $(`tr[data-tarifa="${tarifa}"]`);

                        // LOGICA CORREGIDA: Inicio Actual = Fin Anterior
                        const nextStart = folioFin > 0 ? folioFin : '';

                        $tr.find('.folio-inicio').val(nextStart);
                    }
                    $('.folio-inicio').removeClass('bg-warning bg-opacity-10');
                    calculateTotals();
                });
        }

        // --- 3. CALCULOS "LA PECERA" ---

        function calculateTotals() {
            let grandTotal = 0;

            $('#tablaPecera tbody tr').each(function() {
                const $tr = $(this);
                const tarifa = parseInt($tr.data('tarifa'));
                const inicio = parseInt($tr.find('.folio-inicio').val()) || 0;
                const fin = parseInt($tr.find('.folio-fin').val()) || 0;

                let cantidad = 0;
                let total = 0;

                // LOGICA CORREGIDA: Cantidad = Fin - Inicio
                if (fin > inicio && inicio > 0) {
                    cantidad = (fin - inicio);
                    total = cantidad * tarifa;
                }

                $tr.find('.total-row-val').text(total > 0 ? '$ ' + formatMoney(total) : '0');
                grandTotal += total;
            });

            // Ingreso Bruto
            $('#displayTotalIngreso').text('$ ' + formatMoney(grandTotal));

            // Proporción
            const prop = Math.round(grandTotal * 0.22);
            $('#displayPropConductor').text('$ ' + formatMoney(prop));

            calculateLiquid(grandTotal);
        }

        function calculateLiquid(ingreso) {
            let egresos = 0;
            $('.input-gasto').each(function() {
                egresos += parseInt($(this).val()) || 0;
            });

            // Use the display total, or Recalculate? Better pass it.
            // Note: displayTotalLiquido element is missing in HTML now (check Step 511), 
            // but the function might still be called. I should check if the element exists or if I need to remove this part.
            // I'll keep the function safe:
            const liquido = ingreso - egresos;
            if ($('#displayTotalLiquido').length) {
                $('#displayTotalLiquido').text('$ ' + formatMoney(liquido));
            }
        }

        // Listeners for calc
        $(document).on('input', '.folio-inicio, .folio-fin, .input-gasto', function() {
            calculateTotals();
        });

        // Format Helper
        function formatMoney(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // --- 4. GUARDADO Y VOUCHER ---
        $('#btnGuardar').click(function() {
            if (!$('#formGuia')[0].checkValidity()) {
                $('#formGuia')[0].reportValidity();
                return;
            }

            const formData = new FormData($('#formGuia')[0]);

            Swal.fire({
                title: 'Guardando...',
                didOpen: () => Swal.showLoading()
            });

            fetch('guardar_guia_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // SUCCESS MODAL CUSTOM
                        Swal.fire({
                            icon: 'success',
                            title: 'Guía Registrada Correctamente',
                            html: `
                                <div class="d-grid gap-2 mt-3">
                                    <button id="btnPostPrint" class="btn btn-lg btn-success"><i class="fas fa-print me-2"></i> IMPRIMIR COMPROBANTE</button>
                                    <button id="btnPostNew" class="btn btn-outline-secondary"><i class="fas fa-plus me-2"></i> NUEVA GUÍA</button>
                                </div>
                            `,
                            showConfirmButton: false, // Custom buttons
                            allowOutsideClick: false,
                            didOpen: () => {
                                // AUTO FOCUS PRINT
                                const bPrint = Swal.getHtmlContainer().querySelector('#btnPostPrint');
                                const bNew = Swal.getHtmlContainer().querySelector('#btnPostNew');
                                bPrint.focus();

                                // PRINT ACTION
                                bPrint.addEventListener('click', () => {
                                    window.open(`imprimir_voucher.php?id=${res.data.id}`, '_blank');
                                });
                                // NEW ACTION
                                bNew.addEventListener('click', () => {
                                    Swal.close();
                                    resetForm();
                                });
                            }
                        });

                        // Background Update
                        loadHistory();

                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
        });

        function resetForm() {
            $('#formGuia')[0].reset();
            $('.select2').val('').trigger('change');

            // Explicitly lock dependent fields
            $('#bus_id').prop('disabled', true);
            $('#conductor_id').prop('disabled', true);

            $('#fecha').val(new Date().toISOString().split('T')[0]);
            $('#displayTotalIngreso').text('$ 0');
            // Check if #displayTotalLiquido exists before setting text, though jQuery handles missing elements gracefully.
            if ($('#displayTotalLiquido').length) $('#displayTotalLiquido').text('$ 0');
            $('.total-row-val').text('0');
        }

        // --- 5. HISTORIAL RAPIDO ---
        function loadHistory() {
            const fecha = $('#filtroFechaHistorial').val();
            fetch(`get_historial_guia.php?fecha=${fecha}`)
                .then(r => r.text())
                .then(html => {
                    // Destroy if existing
                    if ($.fn.DataTable.isDataTable('#historialRapido')) {
                        $('#historialRapido').DataTable().destroy();
                    }

                    $('#historialRapido tbody').html(html);

                    // Init DataTables (Simple Mode)
                    $('#historialRapido').DataTable({
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
                        },
                        "paging": true,
                        "lengthChange": false, // No "Show entries"
                        "searching": true,
                        "ordering": false, // Backend ordered by ID DESC
                        "info": false,
                        "pageLength": 10,
                        "dom": '<"row"<"col-sm-12 col-md-12 text-end"f>>t<"row"<"col-sm-12"p>>' // Search right, Table, Pagination center
                    });
                })
                .catch(() => $('#historialRapido tbody').html(''));
        }

        $('#filtroFechaHistorial').on('change', function() {
            loadHistory();
        });

        // Initial Load
        loadHistory();

        // REPRINT LOGIC
        window.reprint = function(id) {
            window.open(`imprimir_voucher.php?id=${id}`, '_blank');
        };
        // DELETE LOGIC
        window.eliminarGuia = function(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "No podrás revertir esto. Se borrará la guía y sus detalles.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../ajax/eliminar_guia_prod.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'id=' + id
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                Swal.fire('Eliminado', 'La guía ha sido eliminada.', 'success');
                                loadHistory();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        });
                }
            });
        };
    });
</script>