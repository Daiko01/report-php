<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SESSION['user_role'] != 'admin') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    $historial_sis = $pdo->query("SELECT * FROM sis_historico ORDER BY ano_inicio DESC, mes_inicio DESC")->fetchAll();
} catch (PDOException $e) {
    $historial_sis = [];
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Registro Histórico SIS</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Registrar Nueva Tasa SIS</h6>
        </div>
        <div class="card-body">
            <form action="../ajax/guardar_sis.php" method="POST" class="row g-3">
                <?php csrf_field(); ?>
                <input type="hidden" name="accion" value="crear">
                <div class="col-md-4">
                    <label class="form-label">Mes de Vigencia</label>
                    <select name="mes" class="form-select" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Año de Vigencia</label>
                    <input type="number" name="ano" class="form-control" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tasa (%) (Ej: 1.49)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="tasa" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Tasa</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Historial de Tasas</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" width="100%">
                    <thead>
                        <tr>
                            <th>Vigencia</th>
                            <th>Tasa Decimal</th>
                            <th>Tasa Porcentual</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial_sis as $s): ?>
                            <tr>
                                <td><?php echo $s['mes_inicio'] . ' / ' . $s['ano_inicio']; ?></td>
                                <td><?php echo $s['tasa_sis_decimal']; ?></td>
                                <td><strong><?php echo number_format($s['tasa_sis_decimal'] * 100, 2); ?>%</strong></td>
                                <td>
                                    <button class="btn btn-danger btn-sm btn-eliminar-sis" data-id="<?php echo $s['id']; ?>">
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
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.btn-eliminar-sis').on('click', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar tasa?',
                text: "Esto afectará los cálculos de reportes históricos si se regeneran.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Obtener token CSRF del formulario que ya lo tiene
                    const csrfToken = $('input[name="csrf_token"]').val();
                    
                    fetch('../ajax/guardar_sis.php', { 
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            accion: 'eliminar',
                            id: id,
                            csrf_token: csrfToken
                        })
                    }).then(r => r.json()).then(data => {
                        if (data.success) location.reload();
                        else Swal.fire('Error', data.message, 'error');
                    });
                }
            });
        });
    });
</script>