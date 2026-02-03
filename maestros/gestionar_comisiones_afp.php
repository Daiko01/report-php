<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/maestros/gestionar_afps.php');
    exit;
}
$afp_id = (int)$_GET['id'];
$stmt_afp = $pdo->prepare("SELECT * FROM afps WHERE id = ?");
$stmt_afp->execute([$afp_id]);
$afp = $stmt_afp->fetch();

try {
    $stmt_c = $pdo->prepare("SELECT * FROM afp_comisiones_historicas WHERE afp_id = ? ORDER BY ano_inicio DESC, mes_inicio DESC");
    $stmt_c->execute([$afp_id]);
    $comisiones = $stmt_c->fetchAll();
} catch (PDOException $e) {
    $comisiones = [];
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>
<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Historial de Comisiones</h1>
        <a href="gestionar_afps.php" class="btn btn-secondary shadow-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-2"></i>Volver a Lista de AFPs
        </a>
    </div>

    <div class="row">
        <!-- Card Nombre AFP -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">AFP Seleccionada</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($afp['nombre']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario Agregar -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header py-3 bg-white d-flex align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle me-2"></i>Agregar Nueva Comisión Vigente</h6>
        </div>
        <div class="card-body">
            <form action="agregar_comision_process.php" method="POST" class="row g-3">
                <?php csrf_field(); ?>
                <input type="hidden" name="afp_id" value="<?php echo $afp_id; ?>">

                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-muted">Mes de Inicio</label>
                    <select class="form-select" id="mes" name="mes_inicio" required>
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
                            <option value="<?php echo $num; ?>" <?php echo ($num == date('n')) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-muted">Año de Inicio</label>
                    <input type="number" name="ano_inicio" class="form-control" value="<?php echo date('Y'); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label small font-weight-bold text-muted">Nueva Tasa (%)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="tasa_porcentaje" placeholder="Ej: 1.44" required>
                        <span class="input-group-text bg-light fw-bold">%</span>
                    </div>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">
                        <i class="fas fa-save me-2"></i>Guardar Comisión
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Historial -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header py-3 bg-white d-flex align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-2"></i>Historial Registrado</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable-es" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>Vigente desde</th>
                            <th class="text-center">Tasa (Decimal)</th>
                            <th class="text-center">Tasa (%)</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comisiones as $c): ?>
                            <tr>
                                <td class="font-weight-bold text-secondary">
                                    <i class="far fa-calendar-alt me-2"></i>
                                    <?php
                                    $nombre_mes = $meses[$c['mes_inicio']] ?? $c['mes_inicio'];
                                    echo $nombre_mes . ' ' . $c['ano_inicio'];
                                    ?>
                                </td>
                                <td class="text-center font-monospace small">
                                    <?php echo $c['comision_decimal']; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success text-white rounded-pill px-3">
                                        <?php echo (float)$c['comision_decimal'] * 100; ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-outline-danger btn-sm rounded-circle btn-eliminar-comision shadow-sm" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;" data-id="<?php echo $c['id']; ?>" title="Eliminar Registro">
                                        <i class="fas fa-trash-alt fa-xs"></i>
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
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Inicializar DataTable si no lo hace el footer automáticamente
        if (!$.fn.DataTable.isDataTable('.datatable-es')) {
            $('.datatable-es').DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
                },
                columnDefs: [{
                    target: 3,
                    orderable: false
                }],
                order: [
                    [0, "desc"]
                ] // Ordenar por fecha (Vigencia) podría requerir lógica extra, pero por defecto ordena por la columna 0 texto.
            });
        }

        // Lógica de eliminación
        $(document).on('click', '.btn-eliminar-comision', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar tasa?',
                text: "Esta acción no se puede deshacer y afectará el historial.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_comision_afp.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: id,
                                csrf_token: $('input[name="csrf_token"]').val()
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('¡Eliminado!', 'El registro ha sido eliminado.', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message || 'Error al eliminar', 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
                }
            });
        });
    });
</script>