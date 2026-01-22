<?php
// planillas/generacion_masiva.php
// 1. Cargar el núcleo
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// 2. Obtener Parametros (Mes/Año)
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// 3. Obtener Empleadores que tengan contratos activos en el mes/año
try {
    $inicio_mes = sprintf('%04d-%02d-01', $ano, $mes);
    $fin_mes = date('Y-m-t', strtotime($inicio_mes));

    $sql = "SELECT DISTINCT e.id, e.nombre, e.rut 
                FROM empleadores e
                INNER JOIN contratos c ON e.id = c.empleador_id
                WHERE c.fecha_inicio <= :fin_mes 
                AND (c.fecha_termino IS NULL OR c.fecha_termino >= :inicio_mes)
                ORDER BY e.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':inicio_mes' => $inicio_mes, ':fin_mes' => $fin_mes]);
    $empleadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Verificar cuáles ya tienen planilla generada para este mes/año
    $stmt_existentes = $pdo->prepare("SELECT DISTINCT empleador_id FROM planillas_mensuales WHERE mes = :mes AND ano = :ano");
    $stmt_existentes->execute(['mes' => $mes, 'ano' => $ano]);
    $existentes = $stmt_existentes->fetchAll(PDO::FETCH_COLUMN); // Array simple de IDs
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Generación Masiva de Planillas</h1>
        <a href="cargar_selector.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <!-- Filtro de Periodo -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">1. Seleccionar Período</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="mes" class="form-label">Mes</label>
                    <select class="form-select" id="mes" name="mes" onchange="this.form.submit()">
                        <?php
                        $meses_nombres = [
                            1 => 'Enero',
                            2 => 'Febrero',
                            3 => 'Marzo',
                            4 => 'Abril',
                            5 => 'Mayo',
                            6 => 'Junio',
                            7 => 'Julio',
                            8 => 'Agosto',
                            9 => 'Septiembre',
                            10 => 'Octubre',
                            11 => 'Noviembre',
                            12 => 'Diciembre'
                        ];
                        foreach ($meses_nombres as $num => $nombre): ?>
                            <option value="<?= $num ?>" <?= ($num == $mes) ? 'selected' : '' ?>><?= $nombre ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ano" class="form-label">Año</label>
                    <select class="form-select" id="ano" name="ano" onchange="this.form.submit()">
                        <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= ($y == $ano) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Botón removido por solicitud de actualización automática -->
            </form>
        </div>
    </div>

    <!-- Lista de Empleadores -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">2. Seleccionar Empleadores</h6>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleCheckboxes(true)">Marcar Todos</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleCheckboxes(false)">Desmarcar Todos</button>
            </div>
        </div>
        <div class="card-body">
            <form id="form-generacion-masiva">
                <input type="hidden" name="mes" value="<?= $mes ?>">
                <input type="hidden" name="ano" value="<?= $ano ?>">

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" width="5%"><input type="checkbox" id="check-all" onclick="toggleMasivo(this)"></th>
                                <th>Empleador</th>
                                <th>RUT</th>
                                <th class="text-center">Estado Periodo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empleadores)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay empleadores registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($empleadores as $emp):
                                    $ya_tiene = in_array($emp['id'], $existentes);
                                ?>
                                    <tr class="<?= $ya_tiene ? 'table-secondary text-muted' : '' ?>">
                                        <td class="text-center">
                                            <?php if (!$ya_tiene): ?>
                                                <input type="checkbox" name="empleadores[]" value="<?= $emp['id'] ?>" class="form-check-input check-empleador">
                                            <?php else: ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($emp['nombre']) ?></td>
                                        <td><?= htmlspecialchars($emp['rut']) ?></td>
                                        <td class="text-center">
                                            <?php if ($ya_tiene): ?>
                                                <span class="badge bg-success">Generado</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 border-top pt-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-success btn-lg" id="btn-procesar">
                        <i class="fas fa-cogs me-2"></i> Generar Planillas Seleccionadas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function toggleMasivo(source) {
        document.querySelectorAll('.check-empleador').forEach(cb => cb.checked = source.checked);
    }

    function toggleCheckboxes(state) {
        document.querySelectorAll('.check-empleador').forEach(cb => cb.checked = state);
        if (document.getElementById('check-all')) document.getElementById('check-all').checked = state;
    }

    document.getElementById('btn-procesar').addEventListener('click', function() {
        // Validar selección
        const selected = document.querySelectorAll('.check-empleador:checked');
        if (selected.length === 0) {
            Swal.fire('Atención', 'Seleccione al menos un empleador para generar la planilla.', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Confirmar Generación?',
            text: `Se generarán las planillas para ${selected.length} empleadores seleccionados. Esto asumirá 30 días trabajados por defecto.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, Generar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviar datos
                const formData = new FormData(document.getElementById('form-generacion-masiva'));

                // Mostrar Loading
                Swal.fire({
                    title: 'Procesando...',
                    html: 'Generando planillas, por favor espere.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('../ajax/procesar_generacion_masiva.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Éxito', data.message, 'success').then(() => {
                                // Redirigir al Dashboard
                                window.location.href = 'dashboard_mensual.php?mes=' + formData.get('mes') + '&ano=' + formData.get('ano');
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'Ocurrió un error inesperado en el servidor.', 'error');
                    });
            }
        });
    });
</script>

<?php
require_once dirname(__DIR__) . '/app/includes/footer.php';
?>