<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) { header('Location: gestionar_contratos.php'); exit; }
$contrato_id = (int)$_GET['id'];

try {
    // Cargar datos del contrato
    $sql = "SELECT c.*, t.nombre as trabajador_nombre, e.nombre as empleador_nombre
            FROM contratos c
            JOIN trabajadores t ON c.trabajador_id = t.id
            JOIN empleadores e ON c.empleador_id = e.id
            WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contrato_id]);
    $contrato = $stmt->fetch();

    if (!$contrato) throw new Exception("Contrato no encontrado");

    // Cargar Anexos
    $stmt_anexos = $pdo->prepare("SELECT * FROM anexos_contrato WHERE contrato_id = ? ORDER BY fecha_anexo DESC");
    $stmt_anexos->execute([$contrato_id]);
    $anexos = $stmt_anexos->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: gestionar_contratos.php'); exit;
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Detalle del Contrato</h1>
    <h5 class="mb-4 text-dark">
        <i class="fas fa-user-tie me-2"></i><?php echo htmlspecialchars($contrato['trabajador_nombre']); ?> 
        <small class="text-muted">en</small> 
        <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($contrato['empleador_nombre']); ?>
    </h5>
    
    <?php if($contrato['esta_finiquitado']): ?>
    <div class="alert alert-danger shadow-sm">
        <i class="fas fa-lock me-2"></i> <strong>CONTRATO FINIQUITADO</strong> el <?php echo date('d-m-Y', strtotime($contrato['fecha_finiquito'])); ?>.
        <?php if(!empty($contrato['motivo_finiquito'])): ?>
            <br><strong>Motivo:</strong> <?php echo htmlspecialchars($contrato['motivo_finiquito']); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs" id="contratoTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#datos" type="button">
                        <i class="fas fa-eye me-2"></i>Datos Vigentes
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#anexos" type="button" <?php if($contrato['esta_finiquitado']) echo 'disabled'; ?>>
                        <i class="fas fa-file-contract me-2"></i>Gestión de Anexos <span class="badge bg-secondary"><?php echo count($anexos); ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link text-danger" data-bs-toggle="tab" data-bs-target="#finiquito" type="button" <?php if($contrato['esta_finiquitado']) echo 'disabled'; ?>>
                        <i class="fas fa-user-slash me-2"></i>Finiquito
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">

                <div class="tab-pane fade show active" id="datos">
                    <div class="alert alert-info small mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Modo Solo Lectura:</strong> Estos son los datos vigentes. Para modificar, use la pestaña <strong>"Anexos"</strong>.
                    </div>
                    <form> 
                        <h6 class="text-secondary fw-bold mb-3 border-bottom pb-2">Vigencia y Tipo</h6>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Tipo Contrato</label>
                                <input type="text" class="form-control" value="<?php echo $contrato['tipo_contrato']; ?>" readonly disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Fecha Inicio</label>
                                <input type="date" class="form-control" value="<?php echo $contrato['fecha_inicio']; ?>" readonly disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Fecha Término</label>
                                <input type="text" class="form-control" value="<?php echo $contrato['fecha_termino'] ? $contrato['fecha_termino'] : 'Indefinido'; ?>" readonly disabled>
                            </div>
                            <div class="col-md-3 d-flex align-items-end pb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" <?php echo $contrato['es_part_time']?'checked':''; ?> disabled>
                                    <label class="form-check-label fw-bold">Es Part-Time</label>
                                </div>
                            </div>
                        </div>

                        <h6 class="text-secondary fw-bold mb-3 border-bottom pb-2">Remuneraciones</h6>
                        <div class="row bg-light p-3 rounded border mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Sueldo Base</label>
                                <div class="input-group"><span class="input-group-text bg-white text-muted">$</span><input type="text" class="form-control bg-white" value="<?php echo number_format($contrato['sueldo_imponible'], 0, ',', '.'); ?>" readonly disabled></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Asig. Colación</label>
                                <div class="input-group"><span class="input-group-text bg-white text-muted">$</span><input type="text" class="form-control bg-white" value="<?php echo number_format($contrato['pacto_colacion'], 0, ',', '.'); ?>" readonly disabled></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Asig. Movilización</label>
                                <div class="input-group"><span class="input-group-text bg-white text-muted">$</span><input type="text" class="form-control bg-white" value="<?php echo number_format($contrato['pacto_movilizacion'], 0, ',', '.'); ?>" readonly disabled></div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="anexos">
                    
                    <div class="card mb-4 border-left-success shadow-sm">
                        <div class="card-body">
                            <h6 class="text-success fw-bold mb-3"><i class="fas fa-plus-circle"></i> Crear Nuevo Anexo</h6>
                            <form action="agregar_anexo_process.php" method="POST">
                                <input type="hidden" name="contrato_id" value="<?php echo $contrato_id; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Descripción del Cambio</label>
                                    <input type="text" class="form-control" name="descripcion" placeholder="Ej: Extensión de contrato a indefinido" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-primary fw-bold">Cambiar Tipo Contrato</label>
                                        <select class="form-select" name="nuevo_tipo_contrato" id="nuevo_tipo_contrato">
                                            <option value="">-- Mantener Actual (<?php echo $contrato['tipo_contrato']; ?>) --</option>
                                            <option value="Indefinido">Cambiar a Indefinido</option>
                                            <option value="Fijo">Cambiar a Plazo Fijo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Fecha Vigencia Anexo</label>
                                        <input type="date" class="form-control" name="fecha_anexo" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nuevo Sueldo Base</label>
                                        <input type="number" class="form-control" name="nuevo_sueldo" placeholder="Actual: $<?php echo number_format($contrato['sueldo_imponible'],0,',','.'); ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3" id="wrapper-nueva-fecha">
                                        <label class="form-label">Nueva Fecha Término</label>
                                        <input type="date" class="form-control" name="nueva_fecha_termino">
                                        <div class="form-text text-muted">Dejar vacío si no cambia.</div>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cambiar Jornada</label>
                                        <select class="form-select" name="nuevo_es_part_time">
                                            <option value="">-- Mantener (<?php echo $contrato['es_part_time']?'Part-Time':'Full'; ?>) --</option>
                                            <option value="0">Full-Time</option>
                                            <option value="1">Part-Time</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-success">Guardar Anexo</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary mt-4">Historial de Cambios</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha Vigencia</th>
                                    <th>Descripción</th>
                                    <th>Detalles del Cambio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anexos as $a): ?>
                                <tr>
                                    <td style="width: 120px;"><?php echo date('d-m-Y', strtotime($a['fecha_anexo'])); ?></td>
                                    <td><?php echo htmlspecialchars($a['descripcion']); ?></td>
                                    <td class="small">
                                        <ul class="mb-0 pl-3">
                                        <?php 
                                            $cambios = [];
                                            if($a['nuevo_sueldo']) $cambios[] = "Sueldo: $".number_format($a['nuevo_sueldo'],0,',','.')."";
                                            if($a['nueva_fecha_termino']) $cambios[] = "Término: ".date('d-m-Y', strtotime($a['nueva_fecha_termino']))."";
                                            if($a['nuevo_tipo_contrato']) $cambios[] = "Tipo: ".$a['nuevo_tipo_contrato'];
                                            if($a['nuevo_es_part_time'] !== null) $cambios[] = "Jornada: ".($a['nuevo_es_part_time']?'Part-Time':'Full-Time');
                                            foreach($cambios as $c) { echo "<li>$c</li>"; }
                                        ?>
                                        </ul>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($anexos)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No existen anexos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="finiquito">
                    <div class="alert alert-warning shadow-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atención:</strong> Finiquitar el contrato es una acción definitiva.
                    </div>
                    <form action="finiquitar_contrato_process.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $contrato_id; ?>">
                        <div class="row">
                            <div class="mb-3 col-md-4">
                                <label class="form-label fw-bold">Fecha de Finiquito</label>
                                <input type="date" class="form-control" name="fecha_finiquito" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3 col-md-8">
                                <label class="form-label fw-bold">Motivo (Opcional)</label>
                                <input type="text" class="form-control" name="motivo" placeholder="Ej: Renuncia, Necesidad de la empresa...">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-danger">Confirmar Finiquito</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Obtenemos el tipo de contrato ACTUAL desde PHP
    const contratoActualTipo = "<?php echo $contrato['tipo_contrato']; ?>";
    
    const $nuevoTipo = $('#nuevo_tipo_contrato');
    const $wrapperNuevaFecha = $('#wrapper-nueva-fecha');
    const $inputNuevaFecha = $('input[name="nueva_fecha_termino"]');

    function toggleAnexoFields() {
        let seleccion = $nuevoTipo.val();
        
        // Si la selección es vacía ("Mantener Actual"), usamos el tipo actual
        if (seleccion === "") {
            seleccion = contratoActualTipo;
        }

        // Lógica:
        // 1. Si el tipo resultante es "Indefinido", ocultamos la fecha.
        // 2. Si el tipo resultante es "Fijo", mostramos la fecha.
        
        if (seleccion === 'Indefinido') {
            $wrapperNuevaFecha.hide();
            $inputNuevaFecha.val(''); // Limpiar valor para que no se envíe
        } else {
            $wrapperNuevaFecha.show();
        }
    }

    $nuevoTipo.on('change', toggleAnexoFields);
    
    // Ejecutar al cargar para establecer el estado inicial correcto
    toggleAnexoFields();
});
</script>