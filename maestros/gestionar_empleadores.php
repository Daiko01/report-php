<?php
// 1. Cargar el núcleo (Sesión y BD)
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

// 2. Obtener todos los empleadores (con JOIN para ver nombres de Caja/Mutual)
try {
    $sql = "SELECT e.*, c.nombre as nombre_caja, m.nombre as nombre_mutual 
            FROM empleadores e
            LEFT JOIN cajas_compensacion c ON e.caja_compensacion_id = c.id
            LEFT JOIN mutuales_seguridad m ON e.mutual_seguridad_id = m.id
            ORDER BY e.nombre ASC";
    $stmt = $pdo->query($sql);
    $empleadores = $stmt->fetchAll();
} catch (PDOException $e) {
    $empleadores = [];
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Empleadores</h1>
        <a href="crear_empleador.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Crear Nuevo Empleador
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped datatable-es" id="tablaEmpleadores" width="100%">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Empresa</th> <th>RUT</th>
                            <th>Caja</th>
                            <th>Mutual</th>
                            <th>Tasa</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleadores as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                            
                            <td>
                                <?php 
                                    if($e['empresa_sistema'] == 'BUSES BP') {
                                        echo '<span class="badge bg-primary">BUSES BP</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">SOL DEL PACIFICO</span>';
                                    }
                                ?>
                            </td>

                            <td><?php echo htmlspecialchars($e['rut']); ?></td>
                            <td><?php echo $e['nombre_caja'] ? htmlspecialchars($e['nombre_caja']) : 'Sin Caja'; ?></td>
                            <td><?php echo htmlspecialchars($e['nombre_mutual']); ?></td>
                            <td><?php echo number_format($e['tasa_mutual_decimal'] * 100, 2, ',', '.'); ?>%</td>
                            <td>
                                <a href="editar_empleador.php?id=<?php echo $e['id']; ?>" class="btn btn-warning btn-circle btn-sm" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                
                                <button class="btn btn-danger btn-circle btn-sm btn-eliminar-empleador" 
                                        data-id="<?php echo $e['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($e['nombre']); ?>"
                                        title="Eliminar">
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
// 3. Cargar el Footer (Layout y JS)
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
$(document).ready(function() {
    
    // --- 1. LÓGICA DE FILTROS EN ENCABEZADO ---
    
    // Clonar la fila del encabezado para crear la fila de filtros
    $('#tablaEmpleadores thead tr').clone(true).addClass('filters').appendTo('#tablaEmpleadores thead');

    var table = $('#tablaEmpleadores').DataTable({
        language: {
            url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
        },
        responsive: true,    // Activar extensión responsive
        orderCellsTop: true, // Indicar que los filtros están arriba
        fixedHeader: true,
        
        initComplete: function () {
            var api = this.api();
            
            // Iterar sobre columnas: 0(Nombre), 1(Empresa), 2(RUT), 3(Caja), 4(Mutual)
            api.columns([0, 1, 2, 3, 4]).every(function () { 
                var column = this;
                var cell = $('.filters th').eq($(column.header()).index()); // Obtener la celda del filtro
                var title = $(column.header()).text().trim(); // Título de la columna

                // Columnas con Filtro de Texto: Nombre(0), RUT(2)
                if ([0, 2].includes(column.index())) {
                    $(cell).html('<input type="text" class="form-control form-control-sm" placeholder="Buscar..." />');
                    $('input', cell).on('keyup change clear', function () {
                        if (column.search() !== this.value) {
                            column.search(this.value).draw();
                        }
                    });
                } 
                // Columnas con Filtro de Select: Empresa(1), Caja(3), Mutual(4)
                else if ([1, 3, 4].includes(column.index())) {
                    var select = $('<select class="form-select form-select-sm"><option value="">Todos</option></select>')
                        .appendTo(cell.empty())
                        .on('change', function () {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            // Buscar coincidencia exacta para selects
                            column.search(val ? '^' + val + '$' : '', true, false).draw();
                        });

                    // Llenar el select con datos únicos de la columna (limpiando HTML)
                    column.data().unique().sort().each(function (d, j) {
                        var cleanData = $(d).text().trim() || d; // .text() saca el texto dentro del span
                        if(cleanData && !select.find('option[value="' + cleanData + '"]').length){
                           select.append('<option value="' + cleanData + '">' + cleanData + '</option>');
                        }
                    });
                }
            });
            
            // Limpiar la celda de filtro para "Tasa"(5) y "Acciones"(6)
            $('.filters th').eq(5).html('');
            $('.filters th').eq(6).html('');
        }
    });
    
    // --- 2. LÓGICA DE SWEETALERT PARA ELIMINAR ---
    
    $('#tablaEmpleadores').on('click', '.btn-eliminar-empleador', function() {
        var $btn = $(this);
        var empleadorId = $btn.data('id');
        var nombre = $btn.data('nombre');

        Swal.fire({
            title: `¿Estás seguro?`,
            text: `Vas a eliminar al empleador ${nombre}. ¡Esta acción no se puede deshacer!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('<?php echo BASE_URL; ?>/ajax/eliminar_empleador_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: empleadorId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            '¡Eliminado!',
                            `El empleador ${nombre} ha sido eliminado.`,
                            'success'
                        ).then(() => {
                            location.reload(); 
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
                });
            }
        });
    });
});
</script>