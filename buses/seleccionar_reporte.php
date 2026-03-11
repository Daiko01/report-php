<?php
// buses/seleccionar_reporte.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

$mes_actual = date('n');
$anio_actual = date('Y');
$buses = $pdo->query("SELECT id, numero_maquina, patente FROM buses ORDER BY numero_maquina")->fetchAll();
$empleadores = $pdo->query("SELECT id, nombre, rut FROM empleadores ORDER BY nombre")->fetchAll();
?>

<div class="container-fluid position-relative">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Generar Reporte Liquidación</h1>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card shadow border-0 mb-4">
                <div class="card-header py-3 bg-white d-flex align-items-center border-bottom-primary">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-sliders-h me-2"></i>Parámetros del Reporte</h6>
                </div>
                <div class="card-body p-4">
                    <form action="<?php echo BASE_URL; ?>/descargar-liquidacion-pdf" method="POST" target="_blank" id="formReporte">

                        <div class="row g-3 mb-4">
                            <!-- Mes -->
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted mb-1"><i class="far fa-calendar-alt me-1"></i> Mes</label>
                                <select name="mes" class="form-select select2">
                                    <?php
                                    $meses = [
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
                                    foreach ($meses as $num => $nombre): ?>
                                        <option value="<?= $num ?>" <?= $num == $mes_actual ? 'selected' : '' ?>><?= $nombre ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Año -->
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted mb-1"><i class="fas fa-calendar me-1"></i> Año</label>
                                <input type="number" name="anio" class="form-control" value="<?= $anio_actual ?>">
                            </div>
                        </div>

                        <hr class="sidebar-divider my-4">

                        <div class="mb-4">
                            <label class="small fw-bold text-muted mb-1"><i class="fas fa-filter me-1"></i> Tipo de Filtro</label>
                            <select name="filtro_tipo" id="filtro_tipo" class="form-select form-select-lg shadow-sm border-left-primary">
                                <option value="todos">Todos los Buses</option>
                                <option value="unidad">Por Unidad Operativa</option>
                                <option value="bus">Por Máquina Individual</option>
                                <option value="empleador">Por Empleador</option>
                            </select>
                            <div class="form-text text-muted small mt-1">Seleccione cómo desea filtrar los reportes generados.</div>
                        </div>

                        <!-- Opciones Dinámicas -->
                        <div class="card bg-light border-0 mb-4" id="dynamic-options" style="display:none;">
                            <div class="card-body">
                                <div id="row_bus" style="display:none;">
                                    <label class="small fw-bold text-primary mb-1">Seleccione Máquina</label>
                                    <select name="bus_id" id="bus_id" class="form-select select2 w-100">
                                        <?php foreach ($buses as $b): ?>
                                            <option value="<?= $b['id'] ?>">Nº <?= $b['numero_maquina'] ?> - <?= $b['patente'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="row_unidad" style="display:none;">
                                    <label class="small fw-bold text-primary mb-1">Seleccione Unidad Operativa</label>
                                    <select name="unidad_id" id="unidad_id" class="form-select select2 w-100">
                                        <option value="">Cargando unidades...</option>
                                    </select>
                                </div>

                                <div id="row_empleador" style="display:none;">
                                    <label class="small fw-bold text-primary mb-1">Seleccione Empleador</label>
                                    <select name="empleador_id" id="empleador_id" class="form-select select2 w-100">
                                        <?php foreach ($empleadores as $e): ?>
                                            <option value="<?= $e['id'] ?>"><?= $e['nombre'] ?> (<?= $e['rut'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div> <!-- End of card-body -->
                        </div> <!-- End of dynamic options card -->

                        <!-- Opciones Especiales y Offcanvas trigger -->
                        <div class="mb-4 d-flex justify-content-between align-items-center bg-white p-3 border rounded shadow-sm mt-4">
                            <div>
                                <div class="form-check form-switch form-check-lg d-flex align-items-center mb-0">
                                    <input class="form-check-input me-3 shadow-none border-secondary" type="checkbox" role="switch" id="aplicarSubsidio" name="aplicar_subsidio" value="1" style="height: 25px; width: 50px; cursor: pointer;">
                                    <label class="form-check-label h6 mb-0 text-primary fw-bold" for="aplicarSubsidio" style="cursor: pointer;">
                                        <i class="fas fa-hand-holding-usd me-1"></i> Incluir Subsidios Operacionales
                                    </label>
                                </div>
                                <div class="form-text text-muted small ms-5 mt-1">Si se activa, el reporte integrará el monto guardado por máquina.</div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary fw-bold rounded-pill shadow-sm px-3 js-btn-gestionar-subsidios" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSubsidios" aria-controls="offcanvasSubsidios">
                                    <i class="fas fa-list-ul me-2"></i>Gestionar Subsidios del Mes
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3">
                            <button type="submit" class="btn btn-danger btn-lg shadow-sm px-4 rounded-pill">
                                <i class="fas fa-file-pdf me-2"></i>Generar PDF
                            </button>
                            <button type="button" id="btn-excel" class="btn btn-success btn-lg shadow-sm px-4 rounded-pill ms-2">
                                <i class="fas fa-file-excel me-2"></i>Generar Excel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Offcanvas Gestión Subsidios -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasSubsidios" aria-labelledby="offcanvasSubsidiosLabel" style="width: 500px;">
    <div class="offcanvas-header bg-primary text-white">
        <h5 class="offcanvas-title fw-bold" id="offcanvasSubsidiosLabel"><i class="fas fa-hand-holding-usd me-2"></i>Subsidios: <span id="lblMesAnioData"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Barra acción rápida superior -->
    <div class="bg-light border-bottom p-3">
        <label class="small fw-bold text-muted mb-2 d-block">Configuración Rápida (Aplicar a todos):</label>
        <div class="input-group input-group-sm mb-0 shadow-sm">
            <span class="input-group-text bg-white">$</span>
            <input type="number" id="masivoMonto" class="form-control border-start-0" placeholder="Monto Subsidio Base">
            <button class="btn btn-primary fw-bold px-3" type="button" id="btnAplicarTodos">
                <i class="fas fa-bolt me-1"></i> Aplicar
            </button>
        </div>
        <small class="text-secondary d-block mt-2"><i class="fas fa-info-circle me-1"></i>Los cambios se guardan automáticamente al escribir en la tabla inferior.</small>
    </div>

    <!-- Contenedor de la tabla -->
    <div class="offcanvas-body p-0 position-relative">
        <div id="loaderSubsidios" class="d-none justify-content-center align-items-center position-absolute w-100 h-100 bg-white" style="z-index: 10; opacity: 0.8; top: 0; left: 0;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
        <form id="formSubsidiosTabla">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3 py-2">Máquina</th>
                            <th class="py-2 text-end" width="25%">Monto ($)</th>
                            <th class="py-2 text-end" width="22%">GPS ($)</th>
                            <th class="pe-3 py-2 text-end" width="22%">Bol. Garantía ($)</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySubsidios">
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">Abre el panel para cargar los buses</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Inicializar Select2 para filtro tipo si no es global
        // $('#filtro_tipo').select2({ theme: 'bootstrap-5' }); 
        // Usamos clase standard de bootstrap para el filtro principal para que se vea mas grande

        $('#filtro_tipo').on('change', function() {
            let val = $(this).val();
            $('#dynamic-options').hide();
            $('#row_bus, #row_unidad, #row_empleador').hide();

            if (val === 'bus') {
                $('#dynamic-options').slideDown();
                $('#row_bus').show();
            }
            if (val === 'unidad') {
                $('#dynamic-options').slideDown();
                $('#row_unidad').show();
            }
            if (val === 'empleador') {
                $('#dynamic-options').slideDown();
                $('#row_empleador').show();
            }
        });

        function verificarDatos(callback) {
            Swal.showLoading();
            $.ajax({
                url: '<?php echo BASE_URL; ?>/buses/check_reporte_info.php',
                type: 'POST',
                data: $('#formReporte').serialize(),
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.exists) {
                        callback();
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'Sin Información',
                            text: 'No se encontraron registros para el periodo seleccionado.',
                            confirmButtonText: 'Entendido'
                        });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error de verificación.',
                        confirmButtonText: 'Cerrar'
                    });
                }
            });
        }

        // PDF Button (es el único submit del form)
        $('#formReporte').on('submit', function(e) {
            e.preventDefault();
            var form = this; // Elemento DOM form puro

            verificarDatos(function() {
                form.submit(); // Llama al método nativo submit() ignorando el event handler de jQuery
            });
        });

        // Excel Button
        $('#btn-excel').click(function() {
            var form = $('#formReporte');
            var originalAction = form.attr('action');

            verificarDatos(function() {
                form.attr('action', '<?php echo BASE_URL; ?>/descargar-resumen-excel');
                form[0].submit(); // Enviar formulario

                // Restaurar acción original después de un breve delay
                setTimeout(function() {
                    form.attr('action', originalAction);
                }, 500);
            });
        });

        // ---------------------------------------------------------
        // LOGICA DE FILTRO DINAMICO DEPENDIENTE DE MES Y AÑO
        // ---------------------------------------------------------
        function cargarOpcionesDinamicas() {
            let mes = $('select[name="mes"]').val();
            let anio = $('input[name="anio"]').val();

            // Cargar Buses
            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/get_buses_by_date.php',
                type: 'GET',
                data: {
                    mes: mes,
                    anio: anio
                },
                dataType: 'json',
                success: function(res) {
                    let selectBus = $('#bus_id');
                    selectBus.empty();
                    if (res.success && res.data.length > 0) {
                        res.data.forEach(function(b) {
                            selectBus.append(new Option('Nº ' + b.numero_maquina + ' - ' + b.patente, b.id));
                        });
                    } else {
                        selectBus.append(new Option('Sin buses con liquidaciones activas', ''));
                    }
                }
            });

            // Cargar Empleadores
            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/get_empleadores_by_date.php',
                type: 'GET',
                data: {
                    mes: mes,
                    anio: anio
                },
                dataType: 'json',
                success: function(res) {
                    let selectEmp = $('#empleador_id');
                    selectEmp.empty();
                    if (res.success && res.data.length > 0) {
                        res.data.forEach(function(e) {
                            selectEmp.append(new Option(e.nombre + ' (' + e.rut + ')', e.id));
                        });
                    } else {
                        selectEmp.append(new Option('Sin empleadores con liquidaciones activas', ''));
                    }
                }
            });

            // Cargar Unidades Operativas
            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/get_unidades_by_date.php',
                type: 'GET',
                data: {
                    mes: mes,
                    anio: anio
                },
                dataType: 'json',
                success: function(res) {
                    let selectUni = $('#unidad_id');
                    selectUni.empty();
                    if (res.success && res.data.length > 0) {
                        res.data.forEach(function(u) {
                            selectUni.append(new Option('Unidad ' + u.numero, u.id));
                        });
                    } else {
                        selectUni.append(new Option('Sin unidades con liquidaciones activas', ''));
                    }
                }
            });
        }

        // Llamar en change
        $('select[name="mes"], input[name="anio"]').on('change', function() {
            cargarOpcionesDinamicas();
        });

        // Llamar por primera vez si estamos filtrando
        cargarOpcionesDinamicas();

        // ---------------------------------------------------------
        // LOGICA OFFCANVAS SUBSIDIOS (UX)
        // ---------------------------------------------------------

        let typingTimer;
        const doneTypingInterval = 800; // ms

        // Al abrir el offcanvas, cargar datos
        $('#offcanvasSubsidios').on('show.bs.offcanvas', function() {
            let mes = $('select[name="mes"]').val();
            let anio = $('input[name="anio"]').val();

            let filtro_tipo = $('#filtro_tipo').val();
            let empId = (filtro_tipo === 'empleador') ? $('select[name="empleador_id"]').val() : 0;
            let uniId = (filtro_tipo === 'unidad') ? $('select[name="unidad_id"]').val() : 0;

            let textoMes = $('select[name="mes"] option:selected').text();
            $('#lblMesAnioData').text(textoMes + ' ' + anio);

            cargarTablaSubsidios(mes, anio, empId, uniId);
        });

        function cargarTablaSubsidios(mes, anio, empId, uniId) {
            $('#loaderSubsidios').removeClass('d-none').addClass('d-flex');

            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/obtener_buses_subsidio.php',
                type: 'GET',
                data: {
                    mes: mes,
                    anio: anio,
                    empleador_id: empId,
                    unidad_id: uniId
                },
                dataType: 'json',
                success: function(res) {
                    $('#loaderSubsidios').removeClass('d-flex').addClass('d-none');
                    let tbody = $('#tbodySubsidios');
                    tbody.empty();

                    if (res.success && res.data.length > 0) {
                        res.data.forEach(b => {
                            let mto = b.monto_subsidio !== '' ? b.monto_subsidio : '';
                            let gps = b.descuento_gps !== '' ? b.descuento_gps : '';
                            let bol = b.descuento_boleta !== '' ? b.descuento_boleta : '';

                            let tr = `
                                <tr>
                                    <td class="ps-3 align-middle fw-bold text-secondary">
                                        Nº ${b.numero_maquina}
                                        <div class="fw-normal" style="font-size: 0.70rem;">${b.patente}</div>
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" class="form-control form-control-sm text-end in-sub-monto border-primary shadow-sm" data-id="${b.id}" name="subsidios[${b.id}][monto]" value="${mto}" placeholder="0">
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" class="form-control form-control-sm text-end in-sub-gps border-warning shadow-sm" data-id="${b.id}" name="subsidios[${b.id}][descuento_gps]" value="${gps}" placeholder="0">
                                    </td>
                                    <td class="pe-3 align-middle position-relative">
                                        <input type="number" class="form-control form-control-sm text-end in-sub-boleta border-danger" data-id="${b.id}" name="subsidios[${b.id}][descuento_boleta]" value="${bol}" placeholder="0">
                                        <i class="fas fa-check-circle text-success position-absolute save-indicator d-none" style="top: 15px; right: 5px; font-size: 10px;"></i>
                                    </td>
                                </tr>
                            `;
                            tbody.append(tr);
                        });
                    } else {
                        tbody.html('<tr><td colspan="3" class="text-center py-4 text-muted">No se encontraron buses.</td></tr>');
                    }
                },
                error: function() {
                    $('#loaderSubsidios').removeClass('d-flex').addClass('d-none');
                    $('#tbodySubsidios').html('<tr><td colspan="3" class="text-center py-4 text-danger">Error de carga.</td></tr>');
                }
            });
        }

        // Aplicar a todos (con confirmación SweetAlert2)
        $('#btnAplicarTodos').on('click', function() {
            let valMasivo = $('#masivoMonto').val();
            if (valMasivo === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo vacío',
                    text: 'Ingresa un monto antes de aplicar.',
                    confirmButtonColor: '#4a90d9',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            let montoFormateado = new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: 'CLP'
            }).format(valMasivo);
            let cantBuses = $('.in-sub-monto').length;

            Swal.fire({
                icon: 'question',
                title: 'Configuración Rápida',
                html: `¿Confirmas aplicar <strong>${montoFormateado}</strong> de subsidio operacional a los <strong>${cantBuses} bus(es)</strong> listados?`,
                showCancelButton: true,
                confirmButtonColor: '#4a90d9',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-bolt me-1"></i> Sí, aplicar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('.in-sub-monto').each(function() {
                        $(this).val(valMasivo);
                    });

                    $('#btnAplicarTodos').html('<i class="fas fa-check"></i> Listo');
                    setTimeout(() => {
                        $('#btnAplicarTodos').html('<i class="fas fa-bolt me-1"></i> Aplicar');
                    }, 1500);

                    guardarTodoElForm(true);

                    Swal.fire({
                        icon: 'success',
                        title: '¡Aplicado!',
                        text: `Subsidio de ${montoFormateado} guardado para ${cantBuses} bus(es).`,
                        timer: 2000,
                        showConfirmButton: false,
                        timerProgressBar: true
                    });
                }
            });
        });

        // Eventos Auto-Save en las celdas individuales (Al soltar tecla con delay o al salir del input)
        $('#tbodySubsidios').on('keyup', '.in-sub-monto, .in-sub-gps, .in-sub-boleta', function() {
            clearTimeout(typingTimer);
            let row = $(this).closest('tr');
            typingTimer = setTimeout(function() {
                guardarFila(row);
            }, doneTypingInterval);
        });

        $('#tbodySubsidios').on('keydown', '.in-sub-monto, .in-sub-gps, .in-sub-boleta', function() {
            clearTimeout(typingTimer);
        });

        // Alternativa al cambiar enfoque de celda (por si no tecleó mucho o usó flechas)
        $('#tbodySubsidios').on('change', '.in-sub-monto, .in-sub-gps, .in-sub-boleta', function() {
            let row = $(this).closest('tr');
            guardarFila(row);
        });

        function guardarFila(rowSelector) {
            let mes = $('select[name="mes"]').val();
            let anio = $('input[name="anio"]').val();
            let rowId = rowSelector.find('.in-sub-monto').data('id');
            let mto = rowSelector.find('.in-sub-monto').val() || 0;
            let gps = rowSelector.find('.in-sub-gps').val() || 0;
            let bol = rowSelector.find('.in-sub-boleta').val() || 0;

            let indicator = rowSelector.find('.save-indicator');
            indicator.removeClass('d-none text-danger').addClass('text-warning fa-spin fa-sync-alt').removeClass('fa-check-circle fa-times-circle');

            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/guardar_subsidios_masivo.php',
                type: 'POST',
                data: {
                    mes: mes,
                    anio: anio,
                    subsidios: {
                        [rowId]: {
                            monto: mto,
                            descuento_gps: gps,
                            descuento_boleta: bol
                        }
                    }
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        indicator.removeClass('fa-spin fa-sync-alt text-warning d-none').addClass('fa-check-circle text-success');
                        setTimeout(() => indicator.addClass('d-none'), 2000); // desaparecer tras 2 seg
                    } else {
                        indicator.removeClass('fa-spin fa-sync-alt text-warning d-none').addClass('fa-times-circle text-danger');
                    }
                },
                error: function() {
                    indicator.removeClass('fa-spin fa-sync-alt text-warning d-none').addClass('fa-times-circle text-danger');
                }
            });
        }

        // Helper para Guardado Completo (del Aplicar Todos)
        function guardarTodoElForm(ocultarLoaderData = false) {
            let mes = $('select[name="mes"]').val();
            let anio = $('input[name="anio"]').val();
            let dataForm = $('#formSubsidiosTabla').serializeArray();
            dataForm.push({
                name: 'mes',
                value: mes
            });
            dataForm.push({
                name: 'anio',
                value: anio
            });

            if (!ocultarLoaderData) $('#loaderSubsidios').removeClass('d-none').addClass('d-flex');

            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/guardar_subsidios_masivo.php',
                type: 'POST',
                data: $.param(dataForm),
                dataType: 'json',
                success: function(res) {
                    $('#loaderSubsidios').removeClass('d-flex').addClass('d-none');
                    if (res.success) {
                        // Flash green indicators en todos
                        $('.save-indicator').removeClass('d-none fa-spin fa-sync-alt text-warning fa-times-circle text-danger').addClass('fa-check-circle text-success');
                        setTimeout(() => $('.save-indicator').addClass('d-none'), 2000);
                    }
                },
                error: function() {
                    $('#loaderSubsidios').removeClass('d-flex').addClass('d-none');
                    Swal.fire({
                        toast: true,
                        position: 'bottom-end',
                        icon: 'error',
                        title: 'Error al aplicar a todos',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
        }

    });
</script>