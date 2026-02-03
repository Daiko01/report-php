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

<div class="container-fluid">
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
                    <form action="generar_pdf_liquidacion.php" method="POST" target="_blank" id="formReporte">

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
                                    <select name="bus_id" class="form-select select2 w-100">
                                        <?php foreach ($buses as $b): ?>
                                            <option value="<?= $b['id'] ?>">Nº <?= $b['numero_maquina'] ?> - <?= $b['patente'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="row_empleador" style="display:none;">
                                    <label class="small fw-bold text-primary mb-1">Seleccione Empleador</label>
                                    <select name="empleador_id" class="form-select select2 w-100">
                                        <?php foreach ($empleadores as $e): ?>
                                            <option value="<?= $e['id'] ?>"><?= $e['nombre'] ?> (<?= $e['rut'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
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

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Inicializar Select2 para filtro tipo si no es global
        // $('#filtro_tipo').select2({ theme: 'bootstrap-5' }); 
        // Usamos clase standard de bootstrap para el filtro principal para que se vea mas grande

        $('#filtro_tipo').on('change', function() {
            let val = $(this).val();
            $('#dynamic-options').hide();
            $('#row_bus, #row_empleador').hide();

            if (val === 'bus') {
                $('#dynamic-options').slideDown();
                $('#row_bus').show();
            }
            if (val === 'empleador') {
                $('#dynamic-options').slideDown();
                $('#row_empleador').show();
            }
        });

        function verificarDatos(callback) {
            Swal.showLoading();
            $.ajax({
                url: 'check_reporte_info.php',
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
                form.attr('action', 'exportar_excel_resumen.php');
                form[0].submit(); // Enviar formulario

                // Restaurar acción original después de un breve delay
                setTimeout(function() {
                    form.attr('action', originalAction);
                }, 500);
            });
        });
    });
</script>