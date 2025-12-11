<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// --- LÓGICA DE PROCESAMIENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. ELIMINAR
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM buses WHERE id = ?")->execute([$id]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Bus eliminado correctamente.'];
        header('Location: gestionar_buses.php'); exit;
    }

    // 2. CREAR / EDITAR
    if (isset($_POST['action']) && $_POST['action'] == 'save') {
        try {
            $empleador_id = $_POST['empleador_id'];
            $unidad_id = $_POST['unidad_id'];
            $terminal_id = !empty($_POST['terminal_id']) ? $_POST['terminal_id'] : null;
            
            // Lógica de Edición (Un solo bus)
            if (!empty($_POST['bus_id'])) {
                $patente = trim($_POST['patentes_input']);
                $nro_maq = trim($_POST['maquinas_input']);
                
                $stmt = $pdo->prepare("UPDATE buses SET empleador_id=?, unidad_id=?, terminal_id=?, numero_maquina=?, patente=? WHERE id=?");
                $stmt->execute([$empleador_id, $unidad_id, $terminal_id, $nro_maq, $patente, $_POST['bus_id']]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Bus actualizado.'];
            } 
            // Lógica de Creación Masiva
            else {
                $maquinas_raw = explode(',', $_POST['maquinas_input']);
                $patentes_raw = explode(',', $_POST['patentes_input']);
                
                $count = 0;
                $stmt = $pdo->prepare("INSERT INTO buses (numero_maquina, empleador_id, unidad_id, terminal_id, patente) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($maquinas_raw as $index => $nro) {
                    $nro = trim($nro);
                    if (empty($nro)) continue;
                    
                    $patente = isset($patentes_raw[$index]) ? trim($patentes_raw[$index]) : null;

                    // Validar duplicados
                    $check = $pdo->prepare("SELECT id FROM buses WHERE numero_maquina = ?");
                    $check->execute([$nro]);
                    if ($check->fetch()) continue;

                    $stmt->execute([$nro, $empleador_id, $unidad_id, $terminal_id, $patente]);
                    $count++;
                }
                
                if ($count > 0) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Se registraron $count máquinas exitosamente."];
                } else {
                    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => "No se registraron máquinas (quizás ya existían)."];
                }
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }
        header('Location: gestionar_buses.php'); exit;
    }
}

// --- CARGA DE DATOS (ACTUALIZADO A FASE 4: USO DE IDs) ---

// 1. Empleadores: Traemos el ID de la empresa sistema nueva
$sql_emp = "SELECT e.id, e.nombre, e.rut, e.empresa_sistema_id, es.nombre as nombre_empresa_madre 
            FROM empleadores e 
            LEFT JOIN empresas_sistema es ON e.empresa_sistema_id = es.id 
            ORDER BY e.nombre";
$empleadores = $pdo->query($sql_emp)->fetchAll();

// 2. Unidades: Traemos el ID de asociación
$unidades = $pdo->query("SELECT id, numero, empresa_asociada_id FROM unidades")->fetchAll();

// 3. Terminales
$terminales = $pdo->query("SELECT id, nombre, unidad_id FROM terminales")->fetchAll();

// 4. Lista de Buses (Visualización)
$sql_buses = "SELECT 
                b.*, 
                e.nombre as nombre_empleador, 
                u.numero as numero_unidad, 
                t.nombre as nombre_terminal 
              FROM buses b 
              JOIN empleadores e ON b.empleador_id = e.id 
              JOIN unidades u ON b.unidad_id = u.id 
              LEFT JOIN terminales t ON b.terminal_id = t.id 
              ORDER BY b.numero_maquina ASC";
$buses = $pdo->query($sql_buses)->fetchAll();

// JSON para JS
$json_unidades = json_encode($unidades);
$json_terminales = json_encode($terminales);

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Flota (Buses)</h1>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 fw-bold"><i class="fas fa-bus me-2"></i>Registrar Máquina(s)</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="formBus">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="bus_id" id="bus_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">1. Empleador (Dueño)</label>
                            <select class="form-select select2" name="empleador_id" id="empleador_id" required>
                                <option value="" disabled selected>Seleccione...</option>
                                <?php foreach ($empleadores as $e): 
                                    // Usamos el ID numérico para la lógica
                                    $empresa_id = $e['empresa_sistema_id'] ?? 0;
                                    // Usamos el nombre para mostrarlo bonito
                                    $nombre_empresa = $e['nombre_empresa_madre'] ?? 'Sin Asignar';
                                ?>
                                    <option value="<?= $e['id'] ?>" data-empresa-id="<?= $empresa_id ?>">
                                        <?= htmlspecialchars($e['nombre']) ?> (<?= htmlspecialchars($nombre_empresa) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Seleccione para cargar unidades.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">2. Unidad Operativa</label>
                            <select class="form-select no-select2" name="unidad_id" id="unidad_id" required disabled>
                                <option value="">Seleccione Empleador primero...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">3. Terminal (Opcional)</label>
                            <select class="form-select no-select2" name="terminal_id" id="terminal_id" disabled>
                                <option value="">Sin Terminal / No Aplica</option>
                            </select>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-success">N° de Máquina(s)</label>
                            <input type="text" class="form-control form-control-lg" name="maquinas_input" id="maquinas_input" placeholder="Ej: 1040, 1041" required>
                            <div class="form-text">Separe con comas para ingresar varias.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Patente(s)</label>
                            <input type="text" class="form-control" name="patentes_input" id="patentes_input" placeholder="Ej: ABCD-12">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">Guardar Registro</button>
                            <button type="button" class="btn btn-secondary" id="btnCancelar" style="display:none;">Cancelar Edición</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Flota Registrada</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover datatable-basic" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Máquina</th>
                                    <th>Unidad</th>
                                    <th>Terminal</th>
                                    <th>Empleador</th>
                                    <th>Patente</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($buses as $b): ?>
                                <tr>
                                    <td class="fw-bold text-center" style="font-size: 1.1em;"><?= $b['numero_maquina'] ?></td>
                                    <td>Unidad <?= htmlspecialchars($b['numero_unidad']) ?></td>
                                    <td><?= $b['nombre_terminal'] ? htmlspecialchars($b['nombre_terminal']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= htmlspecialchars($b['nombre_empleador']) ?></td>
                                    <td><?= htmlspecialchars($b['patente']) ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-warning btn-sm btn-editar" 
                                                data-id="<?= $b['id'] ?>"
                                                data-emp="<?= $b['empleador_id'] ?>"
                                                data-uni="<?= $b['unidad_id'] ?>"
                                                data-ter="<?= $b['terminal_id'] ?>"
                                                data-num="<?= $b['numero_maquina'] ?>"
                                                data-pat="<?= $b['patente'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Borrar máquina <?= $b['numero_maquina'] ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
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

<script>
const unidadesData = <?php echo $json_unidades; ?>;
const terminalesData = <?php echo $json_terminales; ?>;

$(document).ready(function() {
    
    $('.select2').select2({ theme: 'bootstrap-5' });

    $('#formBus').on('submit', function() {
        $('#unidad_id').prop('disabled', false);
        $('#terminal_id').prop('disabled', false);
    });

    // 1. Filtrado de Unidades (USANDO IDs)
    $('#empleador_id').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const empresaId = parseInt(selectedOption.data('empresa-id')) || 0; // Obtener ID numérico
        
        const $unidadSelect = $('#unidad_id');
        const $terminalSelect = $('#terminal_id');
        
        $unidadSelect.empty().append('<option value="">Seleccione...</option>');
        $terminalSelect.empty().append('<option value="">Sin Terminal / No Aplica</option>').prop('disabled', true);

        if (empresaId === 0) {
            $unidadSelect.prop('disabled', true);
            alert("El empleador seleccionado no tiene asignada una Empresa Madre en la base de datos.");
            return;
        }

        // Filtrar usando comparación numérica estricta (ID == ID)
        const unidadesFiltradas = unidadesData.filter(u => parseInt(u.empresa_asociada_id) === empresaId);
        
        if (unidadesFiltradas.length > 0) {
            unidadesFiltradas.forEach(u => {
                $unidadSelect.append(`<option value="${u.id}">Unidad ${u.numero}</option>`);
            });
            $unidadSelect.prop('disabled', false);
        } else {
             $unidadSelect.prop('disabled', true);
             // Solo advertir si es un usuario real, para evitar spam si se carga por defecto
             console.warn("No hay unidades configuradas para la empresa ID " + empresaId);
        }
    });

    // 2. Filtrado de Terminales (ID == ID)
    $('#unidad_id').on('change', function() {
        const unidadId = parseInt($(this).val()) || 0;
        const $terminalSelect = $('#terminal_id');
        
        $terminalSelect.empty().append('<option value="">Sin Terminal / No Aplica</option>');

        if (unidadId === 0) {
            $terminalSelect.prop('disabled', true);
            return;
        }

        const terminalesFiltrados = terminalesData.filter(t => parseInt(t.unidad_id) === unidadId);
        
        terminalesFiltrados.forEach(t => {
            $terminalSelect.append(`<option value="${t.id}">${t.nombre}</option>`);
        });

        $terminalSelect.prop('disabled', false);
    });

    // 3. Edición
    $('.btn-editar').on('click', function() {
        const d = $(this).data();
        $('#bus_id').val(d.id);
        
        $('#empleador_id').val(d.emp).trigger('change');
        
        setTimeout(() => {
            $('#unidad_id').val(d.uni).trigger('change');
            setTimeout(() => { 
                if(d.ter) $('#terminal_id').val(d.ter); 
            }, 100);
        }, 100);

        $('#maquinas_input').val(d.num);
        $('#patentes_input').val(d.pat); 
        $('.card-header').removeClass('bg-primary').addClass('bg-warning');
        $('.card-header h6').text('Editando Máquina ' + d.num);
        $('#btnCancelar').show();
        window.scrollTo(0,0);
    });

    $('#btnCancelar').on('click', function() {
        $('#formBus')[0].reset();
        $('#bus_id').val('');
        $('#empleador_id').val('').trigger('change');
        $('.card-header').removeClass('bg-warning').addClass('bg-primary');
        $('.card-header h6').html('<i class="fas fa-bus me-2"></i>Registrar Máquina(s)');
        $(this).hide();
        $('#unidad_id').empty().prop('disabled', true);
        $('#terminal_id').empty().prop('disabled', true);
    });
});
</script>