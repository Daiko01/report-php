<?php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Validar POST
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['mes']) || !isset($_POST['ano'])) {
    header('Location: ' . BASE_URL . '/reportes/reportes_selector.php');
    exit;
}
$mes = (int)$_POST['mes'];
$ano = (int)$_POST['ano'];

try {
    // 3. Buscar reportes (planillas guardadas)
    // Agrupamos por empleador para obtener una lista única de reportes.
    $sql = "SELECT DISTINCT p.empleador_id, e.nombre, e.rut
            FROM planillas_mensuales p
            JOIN empleadores e ON p.empleador_id = e.id
            WHERE p.mes = :mes AND p.ano = :ano";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['mes' => $mes, 'ano' => $ano]);
    $reportes_encontrados = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al buscar reportes: " . $e->getMessage());
}

// 4. Cargar el Header
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Reportes para <?php echo "$mes / $ano"; ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo count($reportes_encontrados); ?> Reportes Encontrados
            </h6>
            <button class="btn btn-success" id="btn-descargar-zip" <?php echo count($reportes_encontrados) == 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-file-archive me-2"></i> Descargar Seleccionados (ZIP)
            </button>
        </div>
        <div class="card-body">

            <?php if (count($reportes_encontrados) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tabla-reportes">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select-all-reports">
                                    </div>
                                </th>
                                <th>Empleador</th>
                                <th>RUT</th>
                                <th style="width: 100px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportes_encontrados as $r): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input report-checkbox" type="checkbox"
                                                value="<?php echo $r['empleador_id']; ?>">
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($r['rut']); ?></td>

                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/reportes/ver_pdf.php?id=<?php echo $r['empleador_id']; ?>&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>"
                                            class="btn btn-primary btn-sm"
                                            target="_blank"
                                            title="Ver PDF">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">No se encontraron planillas guardadas para este período.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// 5. Cargar el Footer
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>

<script>
    $(document).ready(function() {

        // Checkbox "Seleccionar Todos"
        $('#select-all-reports').on('click', function() {
            $('.report-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Botón "Descargar Seleccionados"
        $('#btn-descargar-zip').on('click', function() {
            const idsSeleccionados = [];
            // Enviamos un objeto {id, mes, ano} por cada reporte
            const mes = <?php echo $mes; ?>;
            const ano = <?php echo $ano; ?>;

            $('.report-checkbox:checked').each(function() {
                idsSeleccionados.push({
                    empleador_id: $(this).val(),
                    mes: mes,
                    ano: ano
                });
            });

            if (idsSeleccionados.length === 0) {
                Swal.fire('Error', 'Debes seleccionar al menos un reporte para descargar.', 'error');
                return;
            }

            // 1. Mostrar SweetAlert de Carga
            Swal.fire({
                title: 'Generando ZIP...',
                text: 'Esto puede tardar unos segundos, por favor espera.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // 2. Enviar IDs a descargar_zip.php usando Fetch
            fetch('<?php echo BASE_URL; ?>/ajax/descargar_zip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        reportes: idsSeleccionados
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        // Si hay error, intentar leer el JSON de error
                        return response.json().then(err => {
                            throw new Error(err.message || 'Error en el servidor');
                        });
                    }
                    // Obtener el nombre del archivo del header
                    const filename = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
                    return response.blob().then(blob => ({
                        blob,
                        filename
                    }));
                })
                .then(({
                    blob,
                    filename
                }) => {
                    // 3. Forzar descarga del ZIP
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename || `Reportes_${mes}-${ano}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);

                    // 4. Alerta de Éxito
                    Swal.fire('¡Éxito!', 'Tu archivo ZIP se ha descargado.', 'success');
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo generar el archivo ZIP. ' + error.message, 'error');
                });
        });
    });
</script>