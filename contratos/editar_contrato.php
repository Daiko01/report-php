<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}
$contrato_id = (int)$_GET['id'];

try {
    // Cargar datos del contrato, trabajador y empleador
    $sql = "SELECT c.*, t.nombre as trabajador_nombre, e.nombre as empleador_nombre
            FROM contratos c
            JOIN trabajadores t ON c.trabajador_id = t.id
            JOIN empleadores e ON c.empleador_id = e.id
            WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contrato_id]);
    $contrato = $stmt->fetch();

    if (!$contrato) throw new Exception("Contrato no encontrado");

    // Cargar anexos
    $sql_anexos = "SELECT * FROM anexos_contrato WHERE contrato_id = ? ORDER BY fecha_anexo DESC";
    $stmt_anexos = $pdo->prepare($sql_anexos);
    $stmt_anexos->execute([$contrato_id]);
    $anexos = $stmt_anexos->fetchAll();
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: ' . BASE_URL . '/contratos/gestionar_contratos.php');
    exit;
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Contrato</h1>
    <h5><?php echo htmlspecialchars($contrato['trabajador_nombre']); ?> en <?php echo htmlspecialchars($contrato['empleador_nombre']); ?></h5>

    <?php if ($contrato['esta_finiquitado']): ?>
        <div class="alert alert-danger">
            <strong>CONTRATO FINIQUITADO:</strong> Este contrato se cerró el <?php echo date('d-m-Y', strtotime($contrato['fecha_finiquito'])); ?>. No se puede editar, anexar o generar en planillas.
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs" id="contratoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">Datos Principales</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="anexos-tab" data-bs-toggle="tab" data-bs-target="#anexos" type="button" role="tab" <?php if ($contrato['esta_finiquitado']) echo 'disabled'; ?>>Anexos (<?php echo count($anexos); ?>)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="finiquito-tab" data-bs-toggle="tab" data-bs-target="#finiquito" type="button" role="tab" <?php if ($contrato['esta_finiquitado']) echo 'disabled'; ?>>Finiquito</button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="contratoTabsContent">

                <div class="tab-pane fade show active" id="datos" role="tabpanel">
                    <form action="editar_contrato_process.php" method="POST" id="form-contrato-edit">
                        <input type="hidden" name="id" value="<?php echo $contrato_id; ?>">
                        <p><strong>Trabajador:</strong> <?php echo htmlspecialchars($contrato['trabajador_nombre']); ?></p>
                        <p><strong>Empleador:</strong> <?php echo htmlspecialchars($contrato['empleador_nombre']); ?></p>
                        <p><em>Nota: La edición de contratos vigentes debe hacerse vía "Anexos" para mantener el historial.</em></p>
                    </form>
                </div>

                <div class="tab-pane fade" id="anexos" role="tabpanel">
                    <h5>Agregar Anexo</h5>
                    <form action="agregar_anexo_process.php" method="POST" class="border p-3 mb-4">
                        <input type="hidden" name="contrato_id" value="<?php echo $contrato_id; ?>">
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción del Anexo</label>
                            <textarea class="form-control" name="descripcion" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="fecha_anexo" class="form-label">Fecha del Anexo</label>
                                <input type="date" class="form-control" name="fecha_anexo" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="nuevo_sueldo" class="form-label">Nuevo Sueldo Base (Opcional)</label>
                                <input type="number" class="form-control" name="nuevo_sueldo" placeholder="<?php echo $contrato['sueldo_imponible']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="nueva_fecha_termino" class="form-label">Nueva Fecha Término (Opcional)</label>
                                <input type="date" class="form-control" name="nueva_fecha_termino">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-3">Guardar Anexo</button>
                    </form>

                    <h5>Historial de Anexos</h5>
                    <table class="table">
                        <tbody>
                            <?php foreach ($anexos as $anexo): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($anexo['fecha_anexo'])); ?></td>
                                    <td><?php echo htmlspecialchars($anexo['descripcion']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="finiquito" role="tabpanel">
                    <h5>Finiquitar Contrato</h5>
                    <p>Esta acción marcará el contrato como terminado y no se incluirá en futuras planillas.</p>
                    <form action="finiquitar_contrato_process.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $contrato_id; ?>">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_finiquito" class="form-label">Fecha de Finiquito</label>
                            <input type="date" class="form-control" id="fecha_finiquito" name="fecha_finiquito" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Confirmar Finiquito</button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>