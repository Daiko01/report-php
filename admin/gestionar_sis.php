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
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Registro Histórico SIS</h1>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Formulario de Registro -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-0">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-plus-circle me-2"></i>Registrar Nueva Tasa</h6>
                </div>
                <div class="card-body bg-light">
                    <form action="../ajax/guardar_sis.php" method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label class="form-label font-weight-bold text-gray-700">Período de Vigencia</label>
                            <div class="row g-2">
                                <div class="col-7">
                                    <select name="mes" class="form-select" required>
                                        <?php
                                        $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                                        for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>>
                                                <?php echo $meses[$m - 1]; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <input type="number" name="ano" class="form-control" value="<?php echo date('Y'); ?>" placeholder="Año" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label font-weight-bold text-gray-700">Tasa SIS (%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-percentage text-primary"></i></span>
                                <input type="number" step="0.01" class="form-control" name="tasa" placeholder="Ej: 1.49" required>
                                <span class="input-group-text">Dec.</span>
                            </div>
                            <div class="form-text small">Ingrese el valor porcentual (Ej: 1.49).</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary shadow-sm">
                                <i class="fas fa-save me-2"></i>Guardar Tasa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Historial -->
        <div class="col-lg-8">
            <div class="card shadow border-0">
                <div class="card-header py-3 bg-white d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-2"></i>Historial de Tasas</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <!-- Added order-disabled class or purely managed by JS logic below -->
                        <table class="table table-hover align-middle" id="tabla_sis" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Vigencia</th>
                                    <th class="text-center">Tasa Decimal</th>
                                    <th class="text-center">Tasa %</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                                foreach ($historial_sis as $s):
                                    $nombre_mes = $meses[$s['mes_inicio'] - 1];
                                    // Hidden sort data: YYYYMM
                                    $sort_val = sprintf("%04d%02d", $s['ano_inicio'], $s['mes_inicio']);
                                ?>
                                    <tr>
                                        <!-- Data-order allows DataTables to sort correctly if it tries to -->
                                        <td data-order="<?php echo $sort_val; ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light text-primary me-3 text-xs">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-gray-800"><?php echo $nombre_mes . ' ' . $s['ano_inicio']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center font-monospace small"><?php echo $s['tasa_sis_decimal']; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-success text-white rounded-pill px-3">
                                                <?php echo number_format($s['tasa_sis_decimal'] * 100, 2); ?>%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-outline-danger btn-sm btn-eliminar-sis rounded-circle" data-id="<?php echo $s['id']; ?>" title="Eliminar">
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
    </div>
</div>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<style>
    .icon-circle {
        height: 2.5rem;
        width: 2.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<script>
    $(document).ready(function() {
        // Initialize DataTables with specific config
        $('#tabla_sis').DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
            },
            order: [
                [0, "desc"]
            ], // Sort by first column (Vigencia) DESC by default
            columnDefs: [{
                    target: 3,
                    orderable: false
                } // Disable sorting on Action column
            ]
        });

        // Delete Logic
        $('.btn-eliminar-sis').on('click', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar tasa?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
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
                        if (data.success) {
                            Swal.fire('Eliminado', 'La tasa ha sido eliminada.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        });
    });
</script>