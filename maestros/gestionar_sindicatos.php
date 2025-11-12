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
    <h1 class="h3 mb-4 text-gray-800">Gestión de Sindicatos</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Sindicatos</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" width="100%">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descuento (Monto Fijo)</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sindicatos as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['nombre']); ?></td>
                                <td>$ <?php echo number_format($s['descuento'], 0, ',', '.'); ?></td>
                                <td>
                                    <a href="editar_sindicato.php?id=<?php echo $s['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-pencil-alt"></i> Editar Descuento
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