<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/maestros/gestionar_sindicatos.php');
    exit;
}
$id = (int)$_GET['id'];
$sindicato = $pdo->query("SELECT * FROM sindicatos WHERE id = $id")->fetch();

require_once dirname(__DIR__) . '/app/includes/header.php';
?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Sindicato</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($sindicato['nombre']); ?></h6>
        </div>
        <div class="card-body">
            <form action="editar_sindicato_process.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $sindicato['id']; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Sindicato</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($sindicato['nombre']); ?>" readonly disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="descuento" class="form-label">Descuento (Monto Fijo en Pesos)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="descuento" name="descuento" value="<?php echo $sindicato['descuento']; ?>" required>
                        </div>
                    </div>
                </div>
                <hr>
                <a href="gestionar_sindicatos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>