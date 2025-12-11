<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

$mes = $_GET['mes'] ?? date('n');
$ano = $_GET['ano'] ?? date('Y');
$empleador_id = $_GET['empleador'] ?? '';

$sql = "SELECT l.*, t.nombre as trab_nom, t.rut as trab_rut, e.nombre as emp_nom 
        FROM liquidaciones l
        JOIN trabajadores t ON l.trabajador_id = t.id
        JOIN empleadores e ON l.empleador_id = e.id
        WHERE l.mes = :mes AND l.ano = :ano";
$params = [':mes' => $mes, ':ano' => $ano];

if ($empleador_id) {
    $sql .= " AND l.empleador_id = :eid";
    $params[':eid'] = $empleador_id;
}
$sql .= " ORDER BY e.nombre, t.nombre";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $liquidaciones = $stmt->fetchAll();
} catch (Exception $e) { $liquidaciones = []; }

$empleadores = $pdo->query("SELECT id, nombre FROM empleadores ORDER BY nombre")->fetchAll();
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Liquidaciones Generadas</h1>
        <div>
            <button id="btn-descargar-masivo" class="btn btn-success shadow-sm me-2">
                <i class="fas fa-file-archive fa-sm text-white-50"></i> Descargar Seleccionadas (ZIP)
            </button>
            <a href="generar_liquidacion.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Generar Nuevas
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label class="form-label">Mes</label>
                    <select class="form-select" name="mes">
                        <?php 
                        $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                        foreach ($meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo ($num == $mes) ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año</label>
                    <select class="form-select" name="ano">
                        <?php for($y=date('Y')+1; $y>=2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y == $ano) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Empleador</label>
                    <select class="form-select" name="empleador">
                        <option value="">Todos</option>
                        <?php foreach ($empleadores as $e): ?>
                            <option value="<?php echo $e['id']; ?>" <?php echo ($e['id'] == $empleador_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fas fa-search"></i> Buscar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable-basic" width="100%">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="check-all"></th>
                            <th>Empleador</th>
                            <th>Trabajador</th>
                            <th>RUT</th>
                            <th>Líquido</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liquidaciones as $l): ?>
                        <tr>
                            <td><input type="checkbox" class="check-item" value="<?php echo $l['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($l['emp_nom']); ?></td>
                            <td><?php echo htmlspecialchars($l['trab_nom']); ?></td>
                            <td><?php echo htmlspecialchars($l['trab_rut']); ?></td>
                            <td class="text-right text-success font-weight-bold">$<?php echo number_format($l['sueldo_liquido'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <a href="ver_pdf_liquidacion.php?id=<?php echo $l['id']; ?>" target="_blank" class="btn btn-info btn-sm" title="Ver PDF">
                                    <i class="fas fa-file-invoice-dollar"></i>
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

<script>
$(document).ready(function() {
    // Seleccionar todos
    $('#check-all').on('change', function() {
        $('.check-item').prop('checked', $(this).prop('checked'));
    });

    // Descargar Masivo
    $('#btn-descargar-masivo').on('click', function() {
        var ids = [];
        $('.check-item:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            Swal.fire('Atención', 'Selecciona al menos una liquidación.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Generando ZIP...',
            text: 'Por favor espera.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch('<?php echo BASE_URL; ?>/ajax/descargar_liquidaciones_zip.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en servidor');
            const filename = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
            return response.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            Swal.close();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            Swal.fire('Error', 'No se pudo generar el ZIP.', 'error');
        });
    });
});
</script>