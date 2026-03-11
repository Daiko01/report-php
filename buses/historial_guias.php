<?php
// buses/historial_guias.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// Cargar listas para filtros
$empleadores = $pdo->query("SELECT id, nombre FROM empleadores WHERE empresa_sistema_id = " . ID_EMPRESA_SISTEMA . " ORDER BY nombre")->fetchAll();

?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-history text-primary me-2"></i>Historial de Guías</h1>
    <div>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <button id="btnEliminarMasivo" class="btn btn-danger shadow-sm rounded-pill px-3 me-2" style="display: none;">
                <i class="fas fa-trash-alt me-2"></i>Eliminar Seleccionados (<span id="countSeleccionados">0</span>)
            </button>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/ingreso-guia" class="btn btn-secondary shadow-sm rounded-pill px-3">
            <i class="fas fa-plus me-2"></i>Nueva Guía
        </a>
    </div>
</div>

<!-- FILTROS -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-gradient-light">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-1"></i> Filtros de Búsqueda</h6>
    </div>
    <div class="card-body">
        <form id="formFiltros">
            <div class="row g-3 items-center">
                <div class="col-md-3">
                    <label class="small fw-bold">Rango de Fechas</label>
                    <div class="input-group input-group-sm">
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-01') ?>">
                        <span class="input-group-text">a</span>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= date('Y-m-t') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Empleador</label>
                    <select class="form-select form-select-sm select2" id="empleador_id" name="empleador_id">
                        <option value="">Todos</option>
                        <?php foreach ($empleadores as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Bus</label>
                    <select class="form-select form-select-sm select2" id="bus_id" name="bus_id" disabled>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Conductor</label>
                    <select class="form-select form-select-sm select2" id="conductor_id" name="conductor_id">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold shadow-sm">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- TABLA DE RESULTADOS -->
<div class="card shadow mb-4">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table class="table table-sm table-striped table-hover small" id="tablaHistorial" width="100%">
                <thead class="bg-primary text-white">
                    <tr>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <th class="text-center" style="width: 40px;">
                                <input type="checkbox" id="cbSelectAll" class="form-check-input">
                            </th>
                        <?php endif; ?>
                        <th>Fecha</th>
                        <th>Folio</th>
                        <th>Bus</th>
                        <th>Patente</th>
                        <th>Empleador</th>
                        <th>Conductor</th>
                        <th class="text-end">Ingreso</th>
                        <th class="text-end">Líquido</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JS Loaded -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Init Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Cargar Buses Dinámicamente
        $('#empleador_id').on('change', function() {
            const empId = $(this).val();
            const $bus = $('#bus_id');
            $bus.html('<option value="">Todos</option>').prop('disabled', true);

            if (empId) {
                fetch(`<?php echo BASE_URL; ?>/ajax/get_buses_por_empleador.php?empleador_id=${empId}`)
                    .then(r => r.json())
                    .then(data => {
                        let opts = '<option value="">Todos</option>';
                        data.forEach(b => {
                            opts += `<option value="${b.id}">${b.numero_maquina}</option>`;
                        });
                        $bus.html(opts).prop('disabled', false);
                    });
            } else {
                // Si no hay empleador, ¿cargamos todos? Mejor dejar deshabilitado para no saturar.
            }
        });

        // Cargar Conductores (All)
        fetch('<?php echo BASE_URL; ?>/ajax/get_todos_conductores.php')
            .then(r => r.json())
            .then(data => {
                let opts = '<option value="">Todos</option>';
                data.forEach(d => {
                    opts += `<option value="${d.id}">${d.nombre} (${d.rut})</option>`;
                });
                $('#conductor_id').html(opts);
            });

        // INIT DATATABLE
        const isAdminUser = <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ? 'true' : 'false'; ?>;
        const datatableColumns = [];

        if (isAdminUser) {
            datatableColumns.push({
                data: null,
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    if (row.is_admin) {
                        return `<input type="checkbox" class="form-check-input cb-guia" value="${row.id}">`;
                    }
                    return '';
                }
            });
        }

        datatableColumns.push({
            data: 'fecha'
        }, {
            data: 'folio',
            className: 'fw-bold'
        }, {
            data: 'bus'
        }, {
            data: 'patente'
        }, {
            data: 'empleador'
        }, {
            data: 'conductor'
        }, {
            data: 'ingreso',
            className: 'text-end text-success fw-bold'
        }, {
            data: 'liquido',
            className: 'text-end fw-bold'
        }, {
            data: 'estado',
            className: 'text-center',
            render: function(data, type, row) {
                if (row.mes_cerrado) return '<span class="badge bg-danger shadow-sm"><i class="fas fa-lock"></i> Mes Cerrado</span>';
                if (data === 'Cerrada') return '<span class="badge bg-secondary"><i class="fas fa-lock"></i> Cerrada</span>';
                return '<span class="badge bg-success"><i class="fas fa-unlock"></i> Abierta</span>';
            }
        }, {
            data: null,
            className: 'text-center',
            render: function(data, type, row) {
                let btns = `<button class="btn btn-xs btn-outline-info me-1" onclick="window.open('<?php echo BASE_URL; ?>/imprimir-voucher/${row.id}', '_blank')"><i class="fas fa-print"></i></button>`;

                if (row.can_edit) {
                    btns += `<a href="<?php echo BASE_URL; ?>/editar-guia/${row.id}" class="btn btn-xs btn-outline-warning me-1"><i class="fas fa-pen"></i></a>`;
                    btns += `<button class="btn btn-xs btn-outline-secondary me-1" onclick="cerrarGuia(${row.id})"><i class="fas fa-lock"></i></button>`;
                } else if (row.mes_cerrado) {
                    // No actions on month closed
                } else if (row.estado === 'Cerrada' && row.can_reopen) {
                    btns += `<button class="btn btn-xs btn-outline-warning me-1" onclick="reabrirGuia(${row.id})"><i class="fas fa-unlock"></i></button>`;
                }

                if (row.is_admin) {
                    btns += `<button class="btn btn-xs btn-outline-danger" onclick="eliminarGuia(${row.id})" title="Eliminar registro"><i class="fas fa-trash"></i></button>`;
                }

                return btns;
            }
        });

        const table = $('#tablaHistorial').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            columns: datatableColumns,
            order: [
                [isAdminUser ? 1 : 0, 'desc'],
                [isAdminUser ? 2 : 1, 'desc']
            ],
            deferRender: true
        });

        // LÓGICA DE CHECKBOXES MASIVOS
        function updateBulkDeleteButton() {
            let count = $('.cb-guia:checked').length;
            if (count > 0) {
                $('#countSeleccionados').text(count);
                $('#btnEliminarMasivo').fadeIn();
            } else {
                $('#btnEliminarMasivo').fadeOut();
            }
        }

        $('#cbSelectAll').on('change', function() {
            let isChecked = $(this).is(':checked');
            $('.cb-guia').prop('checked', isChecked);
            updateBulkDeleteButton();
        });

        $('#tablaHistorial tbody').on('change', '.cb-guia', function() {
            let total = $('.cb-guia').length;
            let checked = $('.cb-guia:checked').length;
            $('#cbSelectAll').prop('checked', total === checked && total > 0);
            updateBulkDeleteButton();
        });

        $('#btnEliminarMasivo').on('click', function() {
            let selectedIds = [];
            $('.cb-guia:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) return;

            Swal.fire({
                title: `¿Eliminar ${selectedIds.length} guías?`,
                text: "Esta acción es IRREVERSIBLE. Se eliminarán permanentemente de la base de datos.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Sí, eliminar todas',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((res) => {
                if (res.isConfirmed) {
                    Swal.showLoading();
                    const formData = new FormData();
                    formData.append('ids', JSON.stringify(selectedIds));

                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_guia_masivo.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                Swal.fire('Eliminadas', res.message, 'success');
                                $('#cbSelectAll').prop('checked', false);
                                $('#btnEliminarMasivo').hide();
                                loadData();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
                }
            });
        });

        // BUSCAR
        $('#formFiltros').on('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            // Mostrar loading
            Swal.showLoading();

            const params = new URLSearchParams(new FormData(document.getElementById('formFiltros')));

            fetch(`<?php echo BASE_URL; ?>/ajax/buscar_guias.php?${params.toString()}`)
                .then(r => r.json())
                .then(res => {
                    Swal.close();
                    if (res.success) {
                        table.clear().rows.add(res.data).draw();
                        $('#cbSelectAll').prop('checked', false);
                        updateBulkDeleteButton();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }

        // Carga inicial
        loadData();

        // ACCIONES GLOBALES
        window.cerrarGuia = function(id) {
            Swal.fire({
                title: '¿Cerrar Guía?',
                text: "No se podrá editar hasta ser reabierta.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, Cerrar'
            }).then((res) => {
                if (res.isConfirmed) procesarAccion(id, 'cerrar');
            });
        };

        window.reabrirGuia = function(id) {
            Swal.fire({
                title: '¿Reabrir Guía?',
                text: "Quedará habilitada para edición.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, Reabrir'
            }).then((res) => {
                if (res.isConfirmed) procesarAccion(id, 'reabrir');
            });
        };

        window.eliminarGuia = function(id) {
            Swal.fire({
                title: '¿Eliminar Guía?',
                text: "Esta acción es IRREVERSIBLE. El registro será eliminado permanentemente de la base de datos.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((res) => {
                if (res.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', id);
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_guia.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                Swal.fire('Eliminada', res.message, 'success');
                                loadData();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
                }
            });
        };

        function procesarAccion(id, action) {
            const formData = new FormData();
            formData.append('ids[]', id);

            const endpoint = (action === 'cerrar') ? '<?php echo BASE_URL; ?>/ajax/procesar_cierre_guia.php' : '<?php echo BASE_URL; ?>/ajax/procesar_reabrir_guia.php';

            fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Éxito', res.message, 'success');
                        loadData();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
        }
    });
</script>