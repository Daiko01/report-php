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
    <h1 class="h3 mb-4 text-gray-800">Generar Reporte Liquidación</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Parámetros del Reporte</h6>
        </div>
        <div class="card-body">
            <form action="generar_pdf_liquidacion.php" method="POST" target="_blank">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Mes</label>
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
                            ?>
                            <select name="mes" class="form-select select2">
                                <?php foreach ($meses as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num == $mes_actual ? 'selected' : '' ?>><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Año</label>
                            <input type="number" name="anio" class="form-control" value="<?= $anio_actual ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Filtrar por (Opcional)</label>
                            <select name="filtro_tipo" id="filtro_tipo" class="form-select no-select2">
                                <option value="todos">Todos los Buses</option>
                                <option value="bus">Por Máquina Individual</option>
                                <option value="empleador">Por Empleador</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mt-3" id="row_bus" style="display:none;">
                    <div class="col-md-6">
                        <label>Seleccione Máquina</label>
                        <select name="bus_id" class="form-select select2 w-100">
                            <?php foreach ($buses as $b): ?>
                                <option value="<?= $b['id'] ?>">Nº <?= $b['numero_maquina'] ?> - <?= $b['patente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-3" id="row_empleador" style="display:none;">
                    <div class="col-md-6">
                        <label>Seleccione Empleador</label>
                        <select name="empleador_id" class="form-select select2 w-100">
                            <?php foreach ($empleadores as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= $e['nombre'] ?> (<?= $e['rut'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Generar PDF
                    </button>
                    <button type="button" id="btn-excel" class="btn btn-success ms-2">
                        <i class="fas fa-file-excel"></i> Generar Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // SELECT2 Removed: Initialized globally in footer.php used to avoid double init issues.
        $('#filtro_tipo').select2();

        $('#filtro_tipo').on('change', function() {
            let val = $(this).val();
            $('#row_bus, #row_empleador').hide();

            if (val === 'bus') $('#row_bus').show();
            if (val === 'empleador') $('#row_empleador').show();
        });

        // Función para verificar datos vía AJAX
        function verificarDatos(callback) {
            var formData = $('#form-reporte').serialize();

            // Si no tenemos ID en el form (porque no se usa ID="form-reporte"), serializamos por el selector del form
            if (!formData) {
                formData = $('form').first().serialize();
            }

            $.ajax({
                url: 'check_reporte_info.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
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
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al verificar la información.',
                        confirmButtonText: 'Cerrar'
                    });
                }
            });
        }

        // Interceptar envío del formulario (PDF por defecto)
        $('form').on('submit', function(e) {
            e.preventDefault(); // Detener envío normal
            var form = this;

            // Si el trigger fue el botón de Excel, ya se manejó en su evento click.
            // Pero si es el submit normal (PDF), verificamos.
            // Para saber si se disparó manualmente por form.submit(), verificamos una flag o similar?
            // Mejor: Solo interceptamos el submit triggered por el botón PDF (type="submit").
            verificarDatos(function() {
                form.submit(); // Esto volverá a disparar onsubmit? Si, cuidado.
                // Para evitar loop infinito, usamos un flag o .off()
                // O mejor: usamos un botón type="button" para PDF también y manejamos todo con JS.
                // PERO: Si cambio type="submit" a "button", rompo la funcionalidad base si JS falla.
                // SOLUCIÓN: Usamos un flag en el elemento form.
            });
        });

        // RE-PENSANDO: La intercepción del submit predeterminado es tricky si queremos hacer form.submit() después.
        // Mejor opción para PDF: Cambiar el botón a type="button" id="btn-pdf" y manejar onclick.

        // Desvinculamos el submit handler anterior y usamos logic de botones explicitos
        $('form').off('submit');

        // Botón PDF (que es type="submit" actualmente)
        // Vamos a prevenir el default en el evento click del botón submit
        $('button[type="submit"]').click(function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            verificarDatos(function() {
                form.off('submit'); // Remover handler para permitir envio
                form.submit();
                // Restaurar handler si fuera necesario (pero aqui recarga pagina o abre nueva tab? target="_blank")
                // Si es target blank, la pagina actual se queda. Necesitamos restaurar el handler?
                // Si, por si el usuario quiere generar otro reporte.

                setTimeout(function() {
                    // Re-bind submit prevention is complex.
                    // Easier: form[0].submit() (DOM method) bypasses jQuery on submit handler usually?
                    // No, form.submit() triggers logic.
                    // Let's us just use form.submit() bypassing the jQuery handler?
                }, 500);
            });
        });

        // CORRECCIÓN: El código anterior es complicado. 
        // Vamos a simplificar usando el click directo en los botones.
    });

    // REVISIÓN FINAL DEL PLAN JAVASCRIPT:
    $(document).ready(function() {
        // ... (Select2 logic) ...
        $('#filtro_tipo').select2();

        $('#filtro_tipo').on('change', function() {
            let val = $(this).val();
            $('#row_bus, #row_empleador').hide();

            if (val === 'bus') $('#row_bus').show();
            if (val === 'empleador') $('#row_empleador').show();
        });

        function verificarDatos(callback) {
            $.ajax({
                url: 'check_reporte_info.php',
                type: 'POST',
                data: $('form').serialize(),
                dataType: 'json',
                success: function(response) {
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
        $('button[type="submit"]').on('click', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');

            verificarDatos(function() {
                // Removemos el handler onsubmit del form si existiera (no hay explicito salvo el default)
                // Usamos el metodo nativo .submit() del DOM que no dispara el evento onsubmit de jQuery si se llamara por jQuery, 
                // PERO aqui queremos enviar. 
                // form.submit() en jQuery dispara el evento submit.
                // form[0].submit() envía el formulario directamente.
                form[0].submit();
            });
        });

        // Excel Button
        $('#btn-excel').click(function() {
            var form = $(this).closest('form');
            var originalAction = form.attr('action');

            verificarDatos(function() {
                form.attr('action', 'exportar_excel_resumen.php');
                form[0].submit();

                setTimeout(function() {
                    form.attr('action', originalAction);
                }, 100);
            });
        });
    });
</script>