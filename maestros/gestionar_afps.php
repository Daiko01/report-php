<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';

try {
    $afps = $pdo->query("SELECT * FROM afps ORDER BY nombre ASC")->fetchAll();
} catch (PDOException $e) {
    $afps = [];
}
?>
<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Comisiones de AFP</h1>
        <!-- Espacio para botón de Crear AFP si fuese necesario -->
    </div>

    <div class="card shadow border-0 mb-4">
        <div class="card-header py-3 bg-white d-flex align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building me-2"></i>Lista de AFPs</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable-es" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre AFP</th>
                            <th class="text-center" style="width: 250px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($afps as $afp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="icon-circle bg-light text-primary me-3 text-xs" style="height: 2.5rem; width: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="fw-bold text-gray-800"><?php echo htmlspecialchars($afp['nombre']); ?></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="gestionar_comisiones_afp.php?id=<?php echo $afp['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="fas fa-history me-1"></i> Ver Historial de Comisiones
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