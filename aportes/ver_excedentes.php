<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// Filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
$empresa = isset($_GET['empresa']) ? $_GET['empresa'] : '';

// Construir Query
$sql = "SELECT * FROM excedentes_aportes WHERE mes = :mes AND ano = :ano";
$params = [':mes' => $mes, ':ano' => $ano];

if ($empresa) {
    $sql .= " AND empresa_detectada = :empresa";
    $params[':empresa'] = $empresa;
}
$sql .= " ORDER BY empresa_detectada, nombre_conductor";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $excedentes = $stmt->fetchAll();
} catch (Exception $e) { $excedentes = []; }

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Excedentes</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Mes</label>
                    <select class="form-select" name="mes">
                        <?php 
                        $meses_arr = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                        foreach ($meses_arr as $num => $nombre) {
                            $selected = ($num == $mes) ? 'selected' : '';
                            echo "<option value='$num' $selected>$nombre</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año</label>
                    <select class="form-select" name="ano">
                        <?php for($y=date('Y')+1; $y>=date('Y')-1; $y--) {
                            $selected = ($y == $ano) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Empresa Detectada</label>
                    <select class="form-select" name="empresa">
                        <option value="">Todas</option>
                        <option value="BUSES BP" <?= $empresa=='BUSES BP'?'selected':'' ?>>BUSES BP</option>
                        <option value="SOC. INV. SOL DEL PACIFICO" <?= $empresa=='SOC. INV. SOL DEL PACIFICO'?'selected':'' ?>>SOL DEL PACIFICO</option>
                        <option value="DESCONOCIDA" <?= $empresa=='DESCONOCIDA'?'selected':'' ?>>DESCONOCIDA</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Listado de Rechazos / Excedentes</h6>
            
            <?php if (!empty($excedentes)): ?>
                <a href="generar_pdf_excedentes_db.php?mes=<?= $mes ?>&ano=<?= $ano ?>&empresa=<?= urlencode($empresa) ?>" target="_blank" class="btn btn-danger">
                    <i class="fas fa-file-pdf me-2"></i> Imprimir Reporte
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable-basic">
                    <thead class="table-light">
                        <tr>
                            <th>Empresa</th>
                            <th>N° Máquina</th>
                            <th>RUT</th>
                            <th>Nombre</th>
                            <th>Monto</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($excedentes as $ex): ?>
                        <tr>
                            <td>
                                <?php if($ex['empresa_detectada'] == 'BUSES BP') echo '<span class="badge bg-primary">BP</span>';
                                      elseif($ex['empresa_detectada'] == 'DESCONOCIDA') echo '<span class="badge bg-secondary">???</span>';
                                      else echo '<span class="badge bg-warning text-dark">SOL</span>'; ?>
                            </td>
                            <td><?= htmlspecialchars($ex['nro_maquina']) ?></td>
                            <td><?= htmlspecialchars($ex['rut_conductor']) ?></td>
                            <td><?= htmlspecialchars($ex['nombre_conductor']) ?></td>
                            <td>$ <?= number_format($ex['monto'], 0, ',', '.') ?></td>
                            <td class="text-danger small"><?= htmlspecialchars($ex['motivo']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>