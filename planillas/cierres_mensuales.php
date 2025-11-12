<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
if (!in_array($_SESSION['user_role'], ['admin', 'contador'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 2. Obtener la lista de todos los períodos con planillas
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

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Cierre Mensual</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Períodos Generados</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" width="100%">
                    <thead>
                        <tr>
                            <th>Empleador</th>
                            <th>Período</th>
                            <th>Estado</th>
                            <th>Acción Cierre</th>
                            <th>Acción Planilla</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periodos as $p):
                            $esta_cerrado = ($p['esta_cerrado'] == 1);
                            $texto_accion = $esta_cerrado ? 'Reabrir' : 'Cerrar';
                            $icono_accion = $esta_cerrado ? 'fa-lock-open' : 'fa-lock';
                            $color_accion = $esta_cerrado ? 'btn-success' : 'btn-danger';
                            $nuevo_estado = $esta_cerrado ? 0 : 1;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['empleador_nombre']); ?></td>
                                <td><?php echo $p['mes'] . ' / ' . $p['ano']; ?></td>
                                <td>
                                    <?php if ($esta_cerrado): ?>
                                        <span class="badge bg-danger">Cerrado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Abierto</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn <?php echo $color_accion; ?> btn-sm btn-toggle-cierre"
                                        data-empleador-id="<?php echo $p['empleador_id']; ?>"
                                        data-mes="<?php echo $p['mes']; ?>"
                                        data-ano="<?php echo $p['ano']; ?>"
                                        data-nuevo-estado="<?php echo $nuevo_estado; ?>"
                                        data-accion-texto="<?php echo $texto_accion; ?>">
                                        <i class="fas <?php echo $icono_accion; ?>"></i> <?php echo $texto_accion; ?>
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-outline-danger btn-sm btn-eliminar-planilla"
                                        data-empleador-id="<?php echo $p['empleador_id']; ?>"
                                        data-mes="<?php echo $p['mes']; ?>"
                                        data-ano="<?php echo $p['ano']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($p['empleador_nombre']); ?>">
                                        <i class="fas fa-trash"></i> Eliminar Planilla
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

        // 1. Botón de Cerrar/Reabrir (Sin cambios)
        $('.datatable-es').on('click', '.btn-toggle-cierre', function() {
            var $btn = $(this);
            var accion = $btn.data('accion-texto');

            Swal.fire({
                title: `¿Estás seguro?`,
                text: `Vas a ${accion} este período.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: (accion === 'Cerrar') ? '#d33' : '#28a745',
                confirmButtonText: `Sí, ${accion}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('<?php echo BASE_URL; ?>/ajax/gestionar_cierre.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                accion: 'actualizar',
                                empleador_id: $btn.data('empleador-id'),
                                mes: $btn.data('mes'),
                                ano: $btn.data('ano'),
                                nuevo_estado: $btn.data('nuevo-estado')
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        });

        // 2. Botón de Eliminar Planilla (NUEVA LÓGICA)
        $('.datatable-es').on('click', '.btn-eliminar-planilla', function() {
            var $btn = $(this);
            var nombre = $btn.data('nombre');
            var mes = $btn.data('mes');
            var ano = $btn.data('ano');

            Swal.fire({
                title: '¿ELIMINAR PLANILLA?',
                text: `¡CUIDADO! Vas a eliminar TODOS los datos de la planilla para ${nombre} del período ${mes}/${ano}. Esta acción no se puede deshacer.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // CAMBIO: Llamar al nuevo script
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_planilla_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                empleador_id: $btn.data('empleador-id'),
                                mes: mes,
                                ano: ano
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload(); // Recargar para que la fila desaparezca
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        });

    });
</script>