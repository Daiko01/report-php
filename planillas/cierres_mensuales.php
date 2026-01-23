<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
if (!in_array($_SESSION['user_role'], ['admin', 'contador'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 2. Obtener lista de empleadores para filtro
try {
    $stmt_emp = $pdo->prepare("SELECT id, nombre FROM empleadores ORDER BY nombre ASC");
    $stmt_emp->execute();
    $empleadores_filtro = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

    // Obtener años disponibles
    // Obtener años disponibles
    $stmt_ano = $pdo->query("SELECT DISTINCT ano FROM planillas_mensuales ORDER BY ano DESC");
    $anos_filtro = $stmt_ano->fetchAll(PDO::FETCH_COLUMN);

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
} catch (PDOException $e) {
    $empleadores_filtro = [];
    $anos_filtro = [date('Y')];
}


// 2.1 Obtener la lista de todos los períodos con planillas (para la tabla)
try {
    $sql = "SELECT 
                p.empleador_id, p.mes, p.ano, 
                e.nombre as empleador_nombre, 
                c.esta_cerrado
            FROM planillas_mensuales p
            JOIN empleadores e ON p.empleador_id = e.id
            LEFT JOIN cierres_mensuales c ON p.empleador_id = c.empleador_id 
                                          AND p.mes = c.mes 
                                          AND p.ano = c.ano
            GROUP BY p.empleador_id, p.mes, p.ano, e.nombre, c.esta_cerrado
            ORDER BY p.ano DESC, p.mes DESC, e.nombre";
    $periodos = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $periodos = [];
}

// 3. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<style>
    /* Estilos personalizados para la barra de acciones */
    #massActionsToolbar {
        display: none;
        /* Hidden from layout by default */
        background-color: #e3e6f0;
        border-bottom: 2px solid #4e73df;
        padding: 10px 20px;
        position: sticky;
        top: 0;
        z-index: 10;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        animation: slideDown 0.3s ease-out;
    }

    #massActionsToolbar.visible {
        display: flex;
        /* Show layout when visible */
    }

    @keyframes slideDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .filter-section {
        background-color: #f8f9fc;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #e3e6f0;
    }
</style>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Cierre Mensual</h1>

    <!-- SECTION: Filtros Externos -->
    <!-- SECTION: Filtros Externos (NUEVO) -->
    <div class="card shadow mb-3">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-calendar-check me-1"></i> Filtros de Planillas</h6>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>

        <div class="collapse border-top bg-light p-3" id="filterPanel">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Empleador:</label>
                    <select id="filterEmpleador" class="form-select form-select-sm">
                        <option value="">Todos los Empleadores</option>
                        <?php foreach ($empleadores_filtro as $emp): ?>
                            <option value="<?= htmlspecialchars($emp['nombre']) ?>"><?= htmlspecialchars($emp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Mes:</label>
                    <select id="filterMes" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($meses_nombres as $num => $nombre): ?>
                            <option value="<?= sprintf("%02d", $num) ?>"><?= $nombre ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Año:</label>
                    <select id="filterAno" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($anos_filtro as $y): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Estado:</label>
                    <select id="filterEstado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Abierto">Abierto</option>
                        <option value="Cerrado">Cerrado</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary w-100" id="btnClearFilters">
                        <i class="fas fa-times me-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: Barra de Acciones Masivas -->
    <div id="massActionsToolbar" class="shadow-sm">
        <span class="font-weight-bold text-dark me-2">Seleccionados: <span id="selectedCount">0</span></span>
        <div class="vr mx-2"></div>
        <button class="btn btn-sm btn-success" id="btnMassClose"><i class="fas fa-lock"></i> Cerrar Masivamente</button>
        <button class="btn btn-sm btn-warning" id="btnMassOpen"><i class="fas fa-lock-open"></i> Reabrir Masivamente</button>
        <button class="btn btn-sm btn-danger ms-auto" id="btnMassDelete"><i class="fas fa-trash"></i> Eliminar Masivamente</button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="tablaCierres" width="100%">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 30px;">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Empleador</th>
                            <th>Período</th>
                            <th>Año</th> <!-- Hidden column for filtering -->
                            <th>Estado</th>
                            <th>Acción Individual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periodos as $p):
                            $esta_cerrado = ($p['esta_cerrado'] == 1);
                            $estado_texto = $esta_cerrado ? 'Cerrado' : 'Abierto';
                            $badge = $esta_cerrado ? 'bg-danger' : 'bg-success';
                            $texto_accion = $esta_cerrado ? 'Reabrir' : 'Cerrar';
                            $icono_accion = $esta_cerrado ? 'fa-lock-open' : 'fa-lock';
                            $color_accion = $esta_cerrado ? 'btn-success' : 'btn-danger'; // Botón inverso al estado
                            $nuevo_estado = $esta_cerrado ? 0 : 1;
                        ?>
                            <tr data-empleador-id="<?= $p['empleador_id'] ?>" data-mes="<?= $p['mes'] ?>" data-ano="<?= $p['ano'] ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="row-checkbox" value="1">
                                </td>
                                <td><?php echo htmlspecialchars($p['empleador_nombre']); ?></td>
                                <td><?php echo sprintf("%02d", $p['mes']) . ' / ' . $p['ano']; ?></td>
                                <td><?= $p['ano'] ?></td>
                                <td><span class="badge <?php echo $badge; ?>"><?php echo $estado_texto; ?></span></td>
                                <td>
                                    <button class="btn <?php echo $color_accion; ?> btn-sm btn-toggle-cierre"
                                        title="<?= $texto_accion ?>">
                                        <i class="fas <?php echo $icono_accion; ?>"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btn-eliminar-planilla ms-1"
                                        title="Eliminar Planilla">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// 4. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
    $(document).ready(function() {
        // --- 1. DATATABLES INIT ---
        var table = $('#tablaCierres').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            order: [
                [2, 'desc']
            ], // Ordenar por periodo
            // Updated DOM
            dom: '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex gap-2"l>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            columnDefs: [{
                    orderable: false,
                    targets: [0, 5]
                }, // No ordenar por checks ni acciones
                {
                    visible: false,
                    targets: [3]
                } // Ocultar columna Año (usada para filtro)
            ],
            initComplete: function() {
                // Styling Search
                $('.dataTables_filter input').addClass('form-control shadow-sm').attr('placeholder', 'Buscar planilla...');
                $('.dataTables_length select').addClass('form-select shadow-sm');
            }
        });

        // --- 2. LOGICA DE FILTROS EXTERNOS ---
        $('#filterEmpleador').on('change', function() {
            var val = $.fn.dataTable.util.escapeRegex($(this).val());
            table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
        });

        $('#filterEmpleador').on('change', function() {
            var val = $.fn.dataTable.util.escapeRegex($(this).val());
            table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
        });

        $('#filterMes').on('change', function() {
            var val = $(this).val();
            // Search in column 2 (Periodo: "MM / YYYY")
            // Search for val at the start.
            table.column(2).search(val ? '^' + val + ' /' : '', true, false).draw();
        });

        $('#filterAno').on('change', function() {
            var val = $.fn.dataTable.util.escapeRegex($(this).val());
            table.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
        });

        $('#filterEstado').on('change', function() {
            var val = $.fn.dataTable.util.escapeRegex($(this).val());
            table.column(4).search(val ? '^' + val + '$' : '', true, false).draw();
        });

        $('#btnClearFilters').click(function() {
            $('#filterEmpleador').val('').trigger('change');
            $('#filterMes').val('').trigger('change');
            $('#filterAno').val('').trigger('change');
            $('#filterEstado').val('').trigger('change');

            // Clear Global Search Input
            $('.dataTables_filter input').val('');

            // Reset DataTable Search
            table.search('').columns().search('').draw();
        });

        // --- 3. SELECCIÓN MASIVA ---
        function updateToolbar() {
            var count = $('.row-checkbox:checked').length;
            $('#selectedCount').text(count);
            if (count > 0) {
                $('#massActionsToolbar').addClass('visible');
            } else {
                $('#massActionsToolbar').removeClass('visible');
                $('#selectAll').prop('checked', false);
            }
        }

        $('#selectAll').on('change', function() {
            var checked = $(this).is(':checked');
            // Seleccionar checks visibles en la pagina actual (o todos si se prefiere, pero datatables paginacion afecta)
            // Para seleccionar globalmente, mejor usar API rows().
            // Por simplicidad, seleccionamos los visibles del DOM
            $('.row-checkbox').prop('checked', checked);
            updateToolbar();
        });

        $('#tablaCierres').on('change', '.row-checkbox', function() {
            updateToolbar();
            // Si deselecciono uno, deseleccionar 'Todos'
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            }
        });

        // Helper para obtener items seleccionados
        function getSelectedItems() {
            var items = [];
            $('.row-checkbox:checked').each(function() {
                var $tr = $(this).closest('tr');
                items.push({
                    empleador_id: $tr.data('empleador-id'),
                    mes: $tr.data('mes'),
                    ano: $tr.data('ano')
                });
            });
            return items;
        }

        // --- 4. ACCIONES MASIVAS ---
        function performMassUpdate(nuevo_estado, accionNombre) {
            var items = getSelectedItems();
            if (items.length === 0) return;

            // Prepare items with new state
            var itemsToSend = items.map(function(item) {
                return {
                    ...item,
                    nuevo_estado: nuevo_estado
                };
            });

            Swal.fire({
                title: '¿Confirmar Acción Masiva?',
                text: `Vas a ${accionNombre} ${items.length} planilla(s).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4e73df',
                confirmButtonText: 'Sí, Ejecutar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('<?php echo BASE_URL; ?>/ajax/gestionar_cierre.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                accion: 'actualizar_masivo',
                                items: itemsToSend
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message);
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Falló: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Éxito!', 'Operación completada.', 'success').then(() => location.reload());
                }
            });
        }

        $('#btnMassClose').click(function() {
            performMassUpdate(1, 'CERRAR');
        });
        $('#btnMassOpen').click(function() {
            performMassUpdate(0, 'REABRIR');
        });

        $('#btnMassDelete').click(function() {
            var items = getSelectedItems();
            if (items.length === 0) return;

            Swal.fire({
                title: '¿ELIMINAR MASIVAMENTE?',
                text: `Vas a ELIMINAR PERMANENTEMENTE ${items.length} planillas. ¡Esta acción es irreversible!`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, ELIMINAR TODO',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('<?php echo BASE_URL; ?>/ajax/eliminar_planilla_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                items: items
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message);
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Falló: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Eliminado!', 'Las planillas seleccionadas han sido eliminadas.', 'success').then(() => location.reload());
                }
            });
        });

        // --- 5. ACCIONES INDIVIDUALES (Legacy Wrapper) ---
        $('#tablaCierres').on('click', '.btn-toggle-cierre', function() {
            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var item = {
                empleador_id: $tr.data('empleador-id'),
                mes: $tr.data('mes'),
                ano: $tr.data('ano')
            };
            // Determine current state visually or logic. 
            // Better to assume we just want to toggle. But our mass logic maps `nuevo_estado`.
            // Let's reuse legacy single logic or build a single-item mass array.
            // Using backend legacy 'actualizar' is fine too, but let's be consistent.
            // The button logic in HTML: closed -> Reopen(0), open -> Close(1).
            var isClosed = $btn.find('i').hasClass('fa-lock-open'); // Reopen has lock-open icon
            var newState = isClosed ? 0 : 1;
            var actionText = isClosed ? 'REABRIR' : 'CERRAR';

            Swal.fire({
                title: `¿${actionText}?`,
                text: `Vas a cambiar el estado de esta planilla.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí',
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch('<?php echo BASE_URL; ?>/ajax/gestionar_cierre.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            accion: 'actualizar',
                            empleador_id: item.empleador_id,
                            mes: item.mes,
                            ano: item.ano,
                            nuevo_estado: newState
                        })
                    }).then(r => r.json()).then(d => {
                        if (d.success) location.reload();
                        else Swal.fire('Error', d.message, 'error');
                    });
                }
            });
        });

        $('#tablaCierres').on('click', '.btn-eliminar-planilla', function() {
            var $tr = $(this).closest('tr');
            var item = {
                empleador_id: $tr.data('empleador-id'),
                mes: $tr.data('mes'),
                ano: $tr.data('ano')
            };
            Swal.fire({
                title: '¿Eliminar Planilla?',
                text: 'Se borrarán todos los datos permanentemente.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Eliminar'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_planilla_ajax.php', {
                        method: 'POST',
                        body: JSON.stringify(item)
                    }).then(r => r.json()).then(d => {
                        if (d.success) location.reload();
                        else Swal.fire('Error', d.message, 'error');
                    });
                }
            });
        });

    });
</script>