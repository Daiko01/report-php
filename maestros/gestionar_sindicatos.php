<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

try {
    $sindicatos = $pdo->query("SELECT * FROM sindicatos ORDER BY nombre ASC")->fetchAll();
} catch (PDOException $e) {
    $sindicatos = [];
}
?>
<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Sindicatos</h1>
        <!-- Espacio para futuro botón de Crear Sindicato si se requiere -->
    </div>

    <div class="card shadow border-0 mb-4">
        <div class="card-header py-3 bg-white d-flex align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users me-2"></i>Lista de Sindicatos</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable-es" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Descuento (Monto Fijo)</th>
                            <th class="text-center" style="width: 150px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sindicatos as $s): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="icon-circle bg-light text-primary me-3 text-xs" style="height: 2.5rem; width: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-handshake"></i>
                                        </div>
                                        <div class="fw-bold text-gray-800"><?php echo htmlspecialchars($s['nombre']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">
                                        $ <?php echo number_format($s['descuento'], 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="editar_sindicato.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle" title="Editar Descuento" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-pencil-alt fa-sm"></i>
                                    </a>
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