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
    <h1 class="h3 mb-4 text-gray-800">Gestión de Comisiones de AFP</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de AFPs</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" width="100%">
                    <thead>
                        <tr>
                            <th>Nombre AFP</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($afps as $afp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($afp['nombre']); ?></td>
                                <td>
                                    <a href="gestionar_comisiones_afp.php?id=<?php echo $afp['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-history"></i> Ver/Editar Historial de Comisiones
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