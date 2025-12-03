<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Lógica de Guardado (Simple, en el mismo archivo)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'crear') {
    try {
        $stmt = $pdo->prepare("INSERT INTO buses (empleador_id, numero_maquina, patente) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['empleador_id'], $_POST['numero_maquina'], $_POST['patente']]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Bus agregado correctamente.'];
    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: La máquina ya existe para este empleador.'];
    }
    header('Location: gestionar_buses.php'); exit;
}

// Lógica de Eliminación
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM buses WHERE id = ?")->execute([$_GET['delete_id']]);
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Bus eliminado.'];
    header('Location: gestionar_buses.php'); exit;
}

// Obtener datos
$buses = $pdo->query("SELECT b.*, e.nombre as empresa FROM buses b JOIN empleadores e ON b.empleador_id = e.id ORDER BY e.nombre, b.numero_maquina")->fetchAll();
$empleadores = $pdo->query("SELECT id, nombre FROM empleadores ORDER BY nombre")->fetchAll();

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Buses</h1>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Nuevo Bus</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="crear">
                        <div class="mb-3">
                            <label class="form-label">Empleador</label>
                            <select class="form-select" name="empleador_id" required>
                                <?php foreach ($empleadores as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">N° Máquina</label>
                            <input type="text" class="form-control" name="numero_maquina" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Patente (Opcional)</label>
                            <input type="text" class="form-control" name="patente">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Guardar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Listado de Buses</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered datatable-basic" width="100%">
                            <thead><tr><th>Empresa</th><th>N° Máquina</th><th>Patente</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($buses as $b): ?>
                                <tr>
                                    <td><?= htmlspecialchars($b['empresa']) ?></td>
                                    <td><?= htmlspecialchars($b['numero_maquina']) ?></td>
                                    <td><?= htmlspecialchars($b['patente']) ?></td>
                                    <td><a href="?delete_id=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar?')"><i class="fas fa-trash"></i></a></td>
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