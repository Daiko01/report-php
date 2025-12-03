<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

try {
    $sql = "SELECT 
                c.*, 
                t.nombre as trabajador_nombre, t.rut as trabajador_rut,
                e.nombre as empleador_nombre
            FROM contratos c
            JOIN trabajadores t ON c.trabajador_id = t.id
            JOIN empleadores e ON c.empleador_id = e.id
            ORDER BY c.fecha_inicio DESC";
    $contratos = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $contratos = [];
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Contratos</h1>
        <a href="crear_contrato.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Crear Nuevo Contrato
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable-es" width="100%">
                    <thead>
                        <tr>
                            <th>Trabajador</th>
                            <th>Empleador</th>
                            <th>Tipo</th>
                            <th>Inicio</th>
                            <th>Término</th>
                            <th>Sueldo Base</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratos as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['trabajador_nombre']); ?> (<?php echo htmlspecialchars($c['trabajador_rut']); ?>)</td>
                                <td><?php echo htmlspecialchars($c['empleador_nombre']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($c['tipo_contrato']); ?>
                                    <?php if ($c['es_part_time']): ?><span class="badge bg-info">Part-Time</span><?php endif; ?>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($c['fecha_inicio'])); ?></td>
                                <td><?php echo $c['fecha_termino'] ? date('d-m-Y', strtotime($c['fecha_termino'])) : 'Indefinido'; ?></td>
                                <td>$ <?php echo number_format($c['sueldo_imponible'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php if ($c['esta_finiquitado']): ?>
                                        <span class="badge bg-danger">Finiquitado (<?php echo date('d-m-Y', strtotime($c['fecha_finiquito'])); ?>)</span>
                                    <?php elseif ($c['fecha_termino'] && $c['fecha_termino'] < date('Y-m-d')): ?>
                                        <span class="badge bg-warning">Vencido</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Vigente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar_contrato.php?id=<?php echo $c['id']; ?>" class="btn btn-warning btn-sm" title="Editar / Finiquitar / Anexar">
                                        <i class="fas fa-pencil-alt"></i>
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