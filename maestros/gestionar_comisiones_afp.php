<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/maestros/gestionar_afps.php');
    exit;
}
$afp_id = (int)$_GET['id'];
$afp = $pdo->query("SELECT * FROM afps WHERE id = $afp_id")->fetch();

try {
    $comisiones = $pdo->query("SELECT * FROM afp_comisiones_historicas WHERE afp_id = $afp_id ORDER BY ano_inicio DESC, mes_inicio DESC")->fetchAll();
} catch (PDOException $e) {
    $comisiones = [];
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Historial de Comisiones: <?php echo htmlspecialchars($afp['nombre']); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Agregar Nueva Comisión Vigente</h6>
        </div>
        <div class="card-body">
            <form action="agregar_comision_process.php" method="POST" class="row g-3">
                <input type="hidden" name="afp_id" value="<?php echo $afp_id; ?>">
                <div class="col-md-4">
                    <label class="form-label">Mes de Inicio</label>
                    <select name="mes_inicio" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>>
                                <?php echo strftime('%B', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Año de Inicio</label>
                    <input type="number" name="ano_inicio" class="form-control" value="<?php echo date('Y'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nueva Tasa (Ej: 1.44)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="tasa_porcentaje" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Agregar al Historial
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Historial</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" width="100%">
                    <thead>
                        <tr>
                            <th>Vigente desde</th>
                            <th>Tasa (Decimal)</th>
                            <th>Tasa (%)</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comisiones as $c): ?>
                            <tr>
                                <td><?php echo $c['mes_inicio'] . ' / ' . $c['ano_inicio']; ?></td>
                                <td><?php echo $c['comision_decimal']; ?></td>
                                <td><?php echo (float)$c['comision_decimal'] * 100; ?>%</td>
                                <td>
                                    <button class="btn btn-danger btn-sm btn-eliminar-comision" data-id="<?php echo $c['id']; ?>">
                                        <i class="fas fa-trash"></i> Eliminar
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
        $('.btn-eliminar-comision').on('click', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Vas a eliminar este registro histórico. Esto podría afectar cálculos pasados.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar por AJAX
                    fetch('<?php echo BASE_URL; ?>/ajax/eliminar_comision_afp.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: id
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
    });
</script>