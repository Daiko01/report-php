<?php
// importar_produccion.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-import text-primary me-2"></i>Importar Producción - <?php echo NOMBRE_SISTEMA; ?>
        </h1>
        <span class="badge p-2 shadow-sm" style="background-color: <?php echo COLOR_SISTEMA; ?>; color: white;">
            Instancia: <?php echo NOMBRE_SISTEMA; ?>
        </span>
    </div>

    <div class="card shadow mb-4 border-left-primary">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-primary">1. Selección de Archivo CSV</h6>
        </div>
        <div class="card-body">
            <form id="formImportar" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">MES DE LAS GUÍAS</label>
                        <select name="mes" id="mes" class="form-select" required>
                            <?php
                            $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
                            $currentMonth = date('n');
                            foreach ($meses as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == $currentMonth ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">AÑO</label>
                        <input type="number" name="anio" id="anio" class="form-control" value="<?= date('Y') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">ARCHIVO CSV (DELIMITADOR ;)</label>
                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnPreview">
                    <i class="fas fa-search me-2"></i> ANALIZAR Y VALIDAR
                </button>
            </form>

            <div id="loading" class="mt-4 text-center" style="display:none;">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 fw-bold text-primary">Procesando datos para <?php echo NOMBRE_SISTEMA; ?>...</p>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4" id="cardPreview" style="display:none;">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
            <h6 class="m-0 font-weight-bold text-dark small text-uppercase">2. Resultados del Análisis</h6>
            <button class="btn btn-success fw-bold px-4 shadow-sm" id="btnConfirmar" style="display:none;">
                <i class="fas fa-check-double me-2"></i> CONFIRMAR IMPORTACIÓN
            </button>
        </div>
        <div class="card-body">
            <div class="alert alert-info border-0 shadow-sm mb-4" id="alertResumen">
                <i class="fas fa-info-circle me-2"></i>
                <span id="resumenInfo">Esperando datos...</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm small" id="tablaPreview" width="100%">
                    <thead class="bg-gray-100 fw-bold">
                        <tr>
                            <th>Fila</th>
                            <th>Fecha</th>
                            <th>N° Bus</th>
                            <th>Dueño</th>
                            <th>Guía</th>
                            <th>Ingreso $</th>
                            <th>Estado / Alerta</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        let tempFileName = '';
        let hasWarnings = false;

        $('#formImportar').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'validate');

            $('#loading').show();
            $('#btnPreview').prop('disabled', true);
            $('#cardPreview').hide();
            $('#btnConfirmar').hide();
            hasWarnings = false;

            fetch('validar_importacion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    $('#loading').hide();
                    $('#btnPreview').prop('disabled', false);

                    if (data.error) {
                        Swal.fire('Error', data.message, 'error');
                        return;
                    }

                    $('#cardPreview').show();
                    tempFileName = data.temp_file;
                    let tbody = '';
                    let errorCount = 0;
                    let warningCount = 0;

                    data.rows.forEach(function(row, index) {
                        let statusClass = 'text-success';
                        let rowClass = '';

                        if (row.status === 'error') {
                            statusClass = 'text-danger fw-bold';
                            rowClass = 'table-danger';
                            errorCount++;
                        } else if (row.status === 'warning') {
                            statusClass = 'text-warning fw-bold';
                            rowClass = 'table-warning';
                            warningCount++;
                            hasWarnings = true;
                        }

                        tbody += `<tr class="${rowClass}">
                    <td class="text-center">${index + 1}</td>
                    <td>${row.fecha}</td>
                    <td class="fw-bold">${row.bus_csv}</td>
                    <td>${row.empleador_nombre || '<span class="badge bg-danger">NO ENCONTRADO</span>'}</td>
                    <td>${row.guia}</td>
                    <td class="text-end fw-bold">$ ${row.ingreso}</td>
                    <td class="${statusClass}">${row.message}</td>
                </tr>`;
                    });

                    $('#tablaPreview tbody').html(tbody);
                    $('#resumenInfo').html(`<strong>Total:</strong> ${data.rows.length} | <strong>Correctos:</strong> ${data.rows.length - errorCount - warningCount} | <strong>Existentes:</strong> ${warningCount} | <strong>Errores:</strong> ${errorCount}`);

                    if (errorCount === 0 && data.rows.length > 0) {
                        $('#btnConfirmar').show();
                        if (hasWarnings) $('#alertResumen').addClass('alert-warning').removeClass('alert-info');
                    } else if (errorCount > 0) {
                        $('#alertResumen').addClass('alert-danger').removeClass('alert-info alert-warning');
                        Swal.fire('Atención', 'Se detectaron máquinas que no pertenecen a este sistema. Corrija el archivo.', 'error');
                    }
                });
        });

        $('#btnConfirmar').on('click', function() {
            let title = '¿Confirmar Importación?';
            let text = "Se guardarán los datos en la base de datos de " + '<?php echo NOMBRE_SISTEMA; ?>';
            let icon = 'question';

            if (hasWarnings) {
                title = '¡Atención! Datos existentes detectados';
                text = "Hay guías que ya fueron cargadas anteriormente. Si continúas, la información antigua será SOBRESCRITA con los datos de este nuevo archivo. ¿Deseas proceder?";
                icon = 'warning';
            }

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: 'Sí, importar y actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: hasWarnings ? '#f6c23e' : '#1cc88a'
            }).then((result) => {
                if (result.isConfirmed) {
                    let formData = new FormData();
                    formData.append('action', 'save');
                    formData.append('temp_file', tempFileName);
                    formData.append('mes', $('#mes').val());
                    formData.append('anio', $('#anio').val());

                    fetch('validar_importacion.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('¡Éxito!', 'Producción cargada y actualizada correctamente.', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        });
    });
</script>