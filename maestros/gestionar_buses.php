<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

// --- 1. LÓGICA DE PROCESAMIENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_token();

    // A. ELIMINAR (Individual o Masivo)
    if (isset($_POST['action']) && ($_POST['action'] == 'delete' || $_POST['action'] == 'bulk_delete')) {
        $ids = [];
        if ($_POST['action'] == 'delete') {
            $ids[] = (int)$_POST['id'];
        } elseif (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
        }

        if (!empty($ids)) {
            // Seguridad: Borrar si el bus (via terminal) pertenece a una unidad de este sistema
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE b FROM buses b 
                    JOIN terminales t ON b.terminal_id = t.id
                    JOIN unidades u ON t.unidad_id = u.id 
                    WHERE b.id IN ($placeholders) AND u.empresa_asociada_id = ?";

            $params = $ids;
            $params[] = ID_EMPRESA_SISTEMA;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $count = $stmt->rowCount();
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "$count bus(es) eliminado(s) correctamente."];
        }
        header('Location: ' . BASE_URL . '/listado-buses');
        exit;
    }

    // B. GUARDAR (CREAR / EDITAR)
    if (isset($_POST['action']) && $_POST['action'] == 'save') {
        try {
            $empleador_id = $_POST['empleador_id'];
            $unidad_id = $_POST['unidad_id'];
            $terminal_id = !empty($_POST['terminal_id']) ? $_POST['terminal_id'] : null;

            // Seguridad IDOR
            $stmt_idor = $pdo->prepare("SELECT COUNT(*) FROM empleadores WHERE id = ? AND empresa_sistema_id = ?");
            $stmt_idor->execute([$empleador_id, ID_EMPRESA_SISTEMA]);
            if ($stmt_idor->fetchColumn() == 0) {
                die("Error de Seguridad: El empleador seleccionado no pertenece a su sistema.");
            }

            if (!empty($_POST['bus_id'])) {
                // --- MODO EDICIÓN ---
                $nro_maq = trim($_POST['maquinas_input']);
                $patente = strtoupper(trim($_POST['patentes_input']));

                // 1. Validar duplicado de NÚMERO DE MÁQUINA
                $chkMaq = $pdo->prepare("
                    SELECT b.id FROM buses b 
                    JOIN terminales t ON b.terminal_id = t.id 
                    JOIN unidades u ON t.unidad_id = u.id 
                    WHERE b.numero_maquina = ? AND u.empresa_asociada_id = ? AND b.id != ?
                ");
                $chkMaq->execute([$nro_maq, ID_EMPRESA_SISTEMA, $_POST['bus_id']]);
                if ($chkMaq->fetch()) {
                    throw new Exception("El Número de Máquina '$nro_maq' ya se encuentra registrado en otra unidad del sistema.");
                }

                // 2. VALIDACIÓN Y NORMALIZACIÓN DE PATENTE
                if (!empty($patente)) {
                    if (!preg_match('/^[A-Z]{4}-?[0-9]{2}$/', $patente)) {
                        throw new Exception("La patente '$patente' no es válida. Formato correcto: 4 Letras y 2 Números (Ej: BBBB-12).");
                    }
                    $patente_norm = preg_replace('/^([A-Z]{4})-?([0-9]{2})$/', '$1-$2', $patente); // Con guion
                    $patente_raw = str_replace('-', '', $patente_norm); // Sin guion

                    // Chequear duplicado patente
                    $chkPat = $pdo->prepare("SELECT id FROM buses WHERE (patente = ? OR patente = ?) AND id != ?");
                    $chkPat->execute([$patente_norm, $patente_raw, $_POST['bus_id']]);
                    if ($chkPat->fetch()) throw new Exception("La patente '$patente_norm' ya pertenece a otro bus registrado.");

                    $patente = $patente_norm;
                }

                $stmt = $pdo->prepare("UPDATE buses SET empleador_id=?, terminal_id=?, numero_maquina=?, patente=? WHERE id=?");
                $stmt->execute([$empleador_id, $terminal_id, $nro_maq, $patente, $_POST['bus_id']]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Bus actualizado con éxito.'];
            } else {
                // --- MODO CREACIÓN --- (Permite comas para ingreso múltiple)
                $maquinas_raw = explode(',', $_POST['maquinas_input']);
                $patentes_raw = explode(',', $_POST['patentes_input']);
                $stmt = $pdo->prepare("INSERT INTO buses (numero_maquina, empleador_id, terminal_id, patente) VALUES (?, ?, ?, ?)");

                foreach ($maquinas_raw as $index => $nro) {
                    $nro = trim($nro);
                    if (empty($nro)) continue;

                    $patente = isset($patentes_raw[$index]) ? strtoupper(trim($patentes_raw[$index])) : null;

                    // 1. Validar duplicado de NÚMERO DE MÁQUINA
                    $chkMaq = $pdo->prepare("
                        SELECT b.id FROM buses b 
                        JOIN terminales t ON b.terminal_id = t.id 
                        JOIN unidades u ON t.unidad_id = u.id 
                        WHERE b.numero_maquina = ? AND u.empresa_asociada_id = ?
                    ");
                    $chkMaq->execute([$nro, ID_EMPRESA_SISTEMA]);
                    if ($chkMaq->fetch()) {
                        throw new Exception("El Número de Máquina '$nro' ya se encuentra registrado en el sistema. Ingrese uno diferente.");
                    }

                    // 2. VALIDACIÓN Y NORMALIZACIÓN DE PATENTE
                    if (!empty($patente)) {
                        if (!preg_match('/^[A-Z]{4}-?[0-9]{2}$/', $patente)) {
                            throw new Exception("La patente '$patente' no es válida. Formato correcto: 4 Letras y 2 Números (Ej: BBBB-12).");
                        }
                        $patente_norm = preg_replace('/^([A-Z]{4})-?([0-9]{2})$/', '$1-$2', $patente); // Con guion
                        $patente_raw = str_replace('-', '', $patente_norm); // Sin guion

                        // Chequear duplicado patente
                        $chkPat = $pdo->prepare("SELECT id FROM buses WHERE (patente = ? OR patente = ?)");
                        $chkPat->execute([$patente_norm, $patente_raw]);
                        if ($chkPat->fetch()) throw new Exception("La patente '$patente_norm' ya se encuentra registrada en el sistema.");

                        $patente = $patente_norm;
                    }

                    $stmt->execute([$nro, $empleador_id, $terminal_id, $patente]);
                }
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Registros procesados correctamente.'];
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
        }
        header('Location: ' . BASE_URL . '/listado-buses');
        exit;
    }

    // C. CARGA MASIVA CSV
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        $file = $_FILES['archivo_csv']['tmp_name'];
        $handle = fopen($file, "r");
        fgetcsv($handle, 1000, ";"); // Saltar cabecera

        // Contadores y seguimiento
        $procesados = 0;
        $exitosos = 0;
        $errores = [];
        $missingOwners = [];

        try {
            $pdo->beginTransaction();
            $linea = 1;

            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $linea++;
                $procesados++;

                // Nuevo Formato (6 columnas)
                $nro = trim($data[0] ?? '');
                $patente = strtoupper(trim($data[1] ?? ''));
                $nom_emp_ref = trim($data[2] ?? '');
                $rut_emp = trim($data[3] ?? '');
                $num_uni = trim($data[4] ?? '');
                $nom_ter = trim($data[5] ?? '');

                // Normalizar número de unidad
                $num_uni_norm = preg_replace('/^[^0-9]+/', '', $num_uni);

                if (empty($nro)) {
                    $errores[] = "Línea $linea: Falta Número de Máquina.";
                    continue;
                }

                // VALIDACIÓN Y NORMALIZACIÓN DE PATENTE CSV
                if (!empty($patente)) {
                    if (!preg_match('/^[A-Z]{4}-?[0-9]{2}$/', $patente)) {
                        $errores[] = "Línea $linea: Patente '$patente' tiene formato inválido. Use 4 letras y 2 números (Ej: BBBB-12).";
                        continue;
                    }
                    $patente_norm = preg_replace('/^([A-Z]{4})-?([0-9]{2})$/', '$1-$2', $patente);
                    $patente_raw = str_replace('-', '', $patente_norm);

                    // Verificar que la patente no le pertenezca a OTRA máquina distinta
                    $chkPat = $pdo->prepare("SELECT numero_maquina FROM buses WHERE (patente = ? OR patente = ?) AND numero_maquina != ?");
                    $chkPat->execute([$patente_norm, $patente_raw, $nro]);
                    if ($chkPat->fetch()) {
                        $errores[] = "Línea $linea: La patente '$patente_norm' ya pertenece a otro bus registrado.";
                        continue;
                    }
                    $patente = $patente_norm;
                }

                // 1. Buscar Empleador por RUT
                $emp_id = null;
                if (!empty($rut_emp)) {
                    $stmt_e = $pdo->prepare("SELECT id FROM empleadores WHERE rut = ? AND empresa_sistema_id = ? LIMIT 1");
                    $stmt_e->execute([$rut_emp, ID_EMPRESA_SISTEMA]);
                    $emp_id = $stmt_e->fetchColumn();
                }

                if (!$emp_id) {
                    $label = !empty($rut_emp) ? "$nom_emp_ref ($rut_emp)" : ($nom_emp_ref ?: "Sin Info");
                    if (!isset($missingOwners[$label])) {
                        $missingOwners[$label] = true;
                    }
                    $emp_id = null;
                }

                // 2. Buscar Unidad
                $uni = $pdo->prepare("SELECT id FROM unidades WHERE numero = ? AND empresa_asociada_id = ? LIMIT 1");
                $uni->execute([$num_uni_norm, ID_EMPRESA_SISTEMA]);
                $uni_id = $uni->fetchColumn();

                if (!$uni_id) {
                    $errores[] = "Línea $linea: Unidad '$num_uni' (buscada como '$num_uni_norm') no encontrada en el sistema.";
                    continue;
                }

                // 3. Buscar Terminal
                $ter_id = null;
                if ($uni_id && !empty($nom_ter)) {
                    $ter = $pdo->prepare("SELECT id FROM terminales WHERE nombre LIKE ? AND unidad_id = ? LIMIT 1");
                    $ter->execute(["%$nom_ter%", $uni_id]);
                    $ter_id = $ter->fetchColumn() ?: null;
                }

                if (!$ter_id) {
                    $errores[] = "Línea $linea: Terminal '$nom_ter' no encontrado en Unidad $num_uni. (Requerido)";
                    continue;
                }

                // 4. Insertar / Actualizar
                try {
                    // Si el numero de máquina ya existe, lo actualiza (Ideal para cargas masivas)
                    $ins = $pdo->prepare("INSERT INTO buses (numero_maquina, patente, empleador_id, terminal_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE patente=VALUES(patente), empleador_id=VALUES(empleador_id), terminal_id=VALUES(terminal_id)");
                    $ins->execute([$nro, $patente, $emp_id, $ter_id]);
                    $exitosos++;
                } catch (Exception $ex) {
                    $errores[] = "Línea $linea: Error DB para bus $nro - " . $ex->getMessage();
                }
            }
            $pdo->commit();

            // Mensaje final
            $msg_missing = '';
            if (!empty($missingOwners)) {
                $lista = implode("</li><li>", array_keys($missingOwners));
                $msg_missing = "<div class='mt-2 alert alert-warning'><i class='fas fa-exclamation-triangle'></i> <b>Atención:</b> Se importaron buses sin dueño asignado.<br><ul class='mb-0 mt-1 small'><li>$lista</li></ul></div>";
            }

            if (count($errores) > 0) {
                $max_show = 5;
                $restantes = count($errores) - $max_show;
                $msg_detalles = implode("<br>", array_slice($errores, 0, $max_show));
                if ($restantes > 0) $msg_detalles .= "<br>... y $restantes errores más.";

                $_SESSION['flash_message'] = [
                    'type' => 'warning',
                    'message' => "Proceso con observaciones.<br><b>Procesados: $procesados | Exitosos: $exitosos | Fallidos: " . count($errores) . "</b>$msg_missing<br><hr class='my-1'>Errores bloqueantes:<br>$msg_detalles"
                ];
            } else {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => "Carga masiva finalizada.<br><b>$exitosos registros procesados.</b>$msg_missing"
                ];
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error Crítico CSV: ' . $e->getMessage()];
        }
        header('Location: ' . BASE_URL . '/listado-buses');
        exit;
    }
}

// --- 2. CARGA DE DATOS (FILTRADOS POR SISTEMA) ---
$empleadores = $pdo->query("SELECT id, nombre, empresa_sistema_id FROM empleadores WHERE empresa_sistema_id = " . ID_EMPRESA_SISTEMA . " ORDER BY nombre")->fetchAll();
$unidades = $pdo->query("SELECT id, numero, empresa_asociada_id FROM unidades WHERE empresa_asociada_id = " . ID_EMPRESA_SISTEMA)->fetchAll();
$terminales = $pdo->query("SELECT t.id, t.nombre, t.unidad_id FROM terminales t JOIN unidades u ON t.unidad_id = u.id WHERE u.empresa_asociada_id = " . ID_EMPRESA_SISTEMA)->fetchAll();

// 1. Obtener registros
$sql_buses = "SELECT b.*, 
              e.nombre as nombre_empleador, 
              e.empresa_sistema_id,
              u.id as unidad_id, 
              u.numero as numero_unidad, 
              t.nombre as nombre_terminal 
              FROM buses b 
              LEFT JOIN empleadores e ON b.empleador_id = e.id 
              LEFT JOIN terminales t ON b.terminal_id = t.id 
              LEFT JOIN unidades u ON t.unidad_id = u.id 
              WHERE u.empresa_asociada_id = " . ID_EMPRESA_SISTEMA . "
              ORDER BY CAST(b.numero_maquina AS UNSIGNED) ASC";
$buses = $pdo->query($sql_buses)->fetchAll();

$json_unidades = json_encode($unidades, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$json_terminales = json_encode($terminales, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-bus text-primary me-2"></i>Directorio de Flota - <?php echo NOMBRE_SISTEMA; ?></h1>
        <div class="d-flex align-items-center">
            <a href="<?php echo BASE_URL; ?>/buses/exportar_buses.php" class="btn btn-outline-primary shadow-sm me-2">
                <i class="fas fa-file-excel me-1"></i> Exportar Flota (.xlsx)
            </a>
            <button class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#csvModal">
                <i class="fas fa-file-csv me-1"></i> Carga Masiva
            </button>
            <button class="btn btn-primary shadow-sm ms-2" data-bs-toggle="collapse" data-bs-target="#collapseForm">
                <i class="fas fa-plus me-1"></i> Registrar Nueva Máquina
            </button>
        </div>
    </div>

    <div class="collapse mb-4" id="collapseForm">
        <div class="card shadow border-left-primary">
            <div class="card-body">
                <form method="POST" id="formBus" class="row g-3">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="bus_id" id="bus_id">

                    <div class="col-md-4">
                        <label class="form-label fw-bold">1. Empleador (Dueño)</label>
                        <select class="form-select select2" name="empleador_id" id="empleador_id" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach ($empleadores as $e): ?>
                                <option value="<?= $e['id'] ?>" data-empresa-id="<?= $e['empresa_sistema_id'] ?>">
                                    <?= htmlspecialchars($e['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">2. Unidad Operativa</label>
                        <select class="form-select" name="unidad_id" id="unidad_id" required disabled>
                            <option value="">Seleccione Empleador...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">3. Terminal (Requerido)</label>
                        <select class="form-select" name="terminal_id" id="terminal_id" disabled required>
                            <option value="">Seleccione Terminal...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-primary">N° de Máquina</label>
                        <input type="text" class="form-control" name="maquinas_input" id="maquinas_input" placeholder="Ej: 1040" required maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-primary">Patente</label>
                        <input type="text" class="form-control" name="patentes_input" id="patentes_input" placeholder="Ej: BBBB-12" maxlength="7">
                        <small class="text-muted" style="font-size: 11px;">4 Letras y 2 Números (Ej: BBBB-12)</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm">GUARDAR REGISTRO</button>
                        <button type="button" class="btn btn-secondary ms-2" id="btnCancelar" style="display:none;">CANCELAR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-list me-1"></i> Flota Registrada (<?= count($buses) ?>)</h6>

                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                        <i class="fas fa-filter"></i> Filtros
                    </button>
                </div>
            </div>

            <div class="collapse border-top bg-light p-3" id="filterPanel">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filtrar por Unidad:</label>
                        <select class="form-select form-select-sm" id="filtroUnidad">
                            <option value="">Todas</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="Unidad <?= $u['numero'] ?>">Unidad <?= $u['numero'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filtrar por Empleador:</label>
                        <select class="form-select form-select-sm" id="filtroEmpleador">
                            <option value="">Todos</option>
                            <?php foreach ($empleadores as $e): ?>
                                <option value="<?= htmlspecialchars($e['nombre']) ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filtrar por Terminal:</label>
                        <select class="form-select form-select-sm" id="filtroTerminal">
                            <option value="">Todos</option>
                            <?php foreach ($terminales as $t): ?>
                                <option value="<?= htmlspecialchars($t['nombre']) ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-sm btn-outline-secondary w-100" id="btnLimpiarFiltros">
                            <i class="fas fa-times me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <div id="bulkToolbar" class="mt-2 text-end bg-warning bg-opacity-10 p-2 border-top border-warning" style="display:none;">
                <span class="text-dark small fw-bold me-2"><span id="selectedCount">0</span> seleccionados</span>
                <button type="button" class="btn btn-sm btn-danger shadow-sm" onclick="confirmBulkDelete()">
                    <i class="fas fa-trash-alt me-1"></i> Eliminar Selección
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <form id="formBulkDelete" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_delete">
                <div class="table-responsive p-2">
                    <table class="table table-hover align-middle mb-0" id="busTable">
                        <thead class="bg-light text-uppercase small fw-bold">
                            <tr>
                                <th class="ps-3 no-sort" style="width: 40px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="checkAll">
                                    </div>
                                </th>
                                <th>Nº</th>
                                <th>Unidad</th>
                                <th>Terminal</th>
                                <th>Dueño</th>
                                <th>Patente</th>
                                <th class="text-end pe-4 no-sort">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buses as $b): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="form-check">
                                            <input class="form-check-input check-item" type="checkbox" name="ids[]" value="<?= $b['id'] ?>">
                                        </div>
                                    </td>
                                    <td class="fw-bold text-dark fs-5"><?= $b['numero_maquina'] ?></td>
                                    <td><span class="badge bg-light text-dark border">Unidad <?= $b['numero_unidad'] ?></span></td>
                                    <td><span class="small"><?= $b['nombre_terminal'] ?: '<i class="text-muted">No asignado</i>' ?></span></td>
                                    <td class="small fw-bold"><?= $b['nombre_empleador'] ? htmlspecialchars($b['nombre_empleador']) : '<span class="text-danger fst-italic"><i class="fas fa-exclamation-circle"></i> Sin Asignar</span>' ?></td>
                                    <td><span class="badge bg-secondary font-monospace fs-6"><?= $b['patente'] ?></span></td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-editar"
                                            data-id="<?= $b['id'] ?>"
                                            data-nro="<?= $b['numero_maquina'] ?>"
                                            data-patente="<?= $b['patente'] ?>"
                                            data-emp="<?= $b['empleador_id'] ?>"
                                            data-uni="<?= $b['unidad_id'] ?>"
                                            data-ter="<?= $b['terminal_id'] ?>"
                                            data-empresa-id="<?= $b['empresa_sistema_id'] ?? ID_EMPRESA_SISTEMA ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" data-id="<?= $b['id'] ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="csvModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <?php csrf_field(); ?>
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Carga Masiva desde CSV</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Suba un archivo con formato: <code>Numero_Maquina; Patente; Nombre_Dueño; RUT_Dueño; Num_Unidad; Nombre_Terminal</code></p>
                <div class="alert alert-info py-2 small">
                    <i class="fas fa-info-circle"></i> La columna de <b>Patente</b> debe tener el formato de 4 letras y 2 números (Ej: BBBB-12).
                </div>
                <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success fw-bold">PROCESAR ARCHIVO</button></div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    const unidadesData = <?= $json_unidades ?>;
    const terminalesData = <?= $json_terminales ?>;

    $(document).ready(function() {

        // --- DATATABLES SETUP ---
        const table = $('#busTable').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            },
            responsive: true,
            order: [
                [1, 'asc']
            ],
            columnDefs: [{
                    orderable: false,
                    targets: [0, 6]
                },
                {
                    className: "text-center",
                    targets: [1]
                }
            ],
            dom: '<"d-flex justify-content-between align-items-center m-2"lf>rt<"d-flex justify-content-between align-items-center m-2"ip>',
            pageLength: 25
        });

        // --- EXTERNAL FILTERS ---
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                const fUnidad = $('#filtroUnidad').val();
                const fEmp = $('#filtroEmpleador').val();
                const fTerm = $('#filtroTerminal').val();

                const dbUnidad = data[2] || "";
                const dbTerm = data[3] || "";
                const dbEmp = data[4] || "";

                if (fUnidad && !dbUnidad.includes(fUnidad)) return false;
                if (fEmp && !dbEmp.includes(fEmp)) return false;
                if (fTerm && !dbTerm.includes(fTerm)) return false;

                return true;
            }
        );

        $('#filtroUnidad, #filtroEmpleador, #filtroTerminal').on('change', function() {
            table.draw();
        });

        $('#btnLimpiarFiltros').click(function() {
            $('#filtroUnidad').val('');
            $('#filtroEmpleador').val('');
            $('#filtroTerminal').val('');
            table.draw();
        });

        // 1. Filtrado de Unidades
        $('#empleador_id').on('change', function() {
            const $u = $('#unidad_id');
            const $t = $('#terminal_id');

            $u.html('<option value="">Seleccione Unidad...</option>').prop('disabled', true).trigger('change');
            $t.html('<option value="">Sin Terminal / No Aplica</option>').prop('disabled', true).trigger('change');

            if ($(this).val()) {
                if (unidadesData.length > 0) {
                    let opts = '<option value="">Seleccione Unidad...</option>';
                    unidadesData.forEach(u => {
                        opts += `<option value="${u.id}">Unidad ${u.numero}</option>`;
                    });
                    $u.html(opts).prop('disabled', false).trigger('change');
                } else {
                    $u.html('<option disabled>No hay unidades registradas en el sistema</option>').trigger('change');
                }
            }
        });

        // 2. Filtrado de Terminales
        $('#unidad_id').on('change', function() {
            const unidadId = parseInt($(this).val()) || 0;
            const $t = $('#terminal_id');
            $t.html('<option value="">Sin Terminal / No Aplica</option>').trigger('change');

            if (unidadId > 0) {
                const terms = terminalesData.filter(t => t.unidad_id == unidadId);
                if (terms.length > 0) {
                    let opts = '<option value="">Sin Terminal / No Aplica</option>';
                    terms.forEach(t => {
                        opts += `<option value="${t.id}">${t.nombre}</option>`;
                    });
                    $t.html(opts).prop('disabled', false).trigger('change');
                } else {
                    $t.prop('disabled', true);
                }
            } else {
                $t.prop('disabled', true);
            }
        });

        // 3. Edición (Cargar Formulario)
        $(document).on('click', '.btn-editar', function() {
            const btn = $(this);
            $('#bus_id').val(btn.data('id'));
            $('#maquinas_input').val(btn.data('nro'));
            $('#patentes_input').val(btn.data('patente'));

            $('#empleador_id').val(btn.data('emp')).trigger('change');

            setTimeout(() => {
                $('#unidad_id').val(btn.data('uni')).trigger('change');
                setTimeout(() => {
                    $('#terminal_id').val(btn.data('ter')).trigger('change');
                }, 50);
            }, 50);

            $('#collapseForm').collapse('show');
            $('#btnCancelar').show();
            $('html, body').animate({
                scrollTop: $("#collapseForm").offset().top - 100
            }, 500);
        });

        $('#btnCancelar').click(function() {
            $('#formBus')[0].reset();
            $('#bus_id').val('');
            $('#empleador_id').val('').trigger('change');
            $('#collapseForm').collapse('hide');
            $(this).hide();
        });

        // 4. Checkbox & Bulk Actions Logic
        const $checkAll = $('#checkAll');
        const $bulkToolbar = $('#bulkToolbar');
        const $selectedCount = $('#selectedCount');

        function updateToolbar() {
            const count = $('.check-item:checked').length;
            $selectedCount.text(count);
            if (count > 0) {
                $bulkToolbar.fadeIn();
            } else {
                $bulkToolbar.fadeOut();
            }
        }

        $checkAll.on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.check-item').prop('checked', isChecked);
            updateToolbar();
        });

        $(document).on('change', '.check-item', function() {
            const total = $('.check-item').length;
            const checked = $('.check-item:checked').length;

            $checkAll.prop('checked', total > 0 && total === checked);
            updateToolbar();
        });

        table.on('draw', function() {
            $checkAll.prop('checked', false);
            updateToolbar();
        });

        window.confirmBulkDelete = function() {
            const count = $('.check-item:checked').length;
            if (count === 0) return;

            if (confirm(`¿Está seguro de eliminar ${count} buses seleccionados? Esta acción no se puede deshacer.`)) {
                $('#formBulkDelete').submit();
            }
        };

        // 5. Single Delete Handler
        $(document).on('click', '.btn-eliminar', function() {
            const id = $(this).data('id');
            if (confirm('¿Está seguro de eliminar este bus?')) {
                const form = $('<form method="POST" action="<?php echo BASE_URL; ?>/listado-buses">' +
                    '<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">' +
                    '<input type="hidden" name="action" value="delete">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>');
                $('body').append(form);
                form.submit();
            }
        });

        // --- MÁSCARA INTELIGENTE DE PATENTE ---
        $('#patentes_input').on('input', function() {
            // 1. Quitamos todo lo que no sea letra o número
            let val = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');
            let result = '';

            // 2. Armamos la patente letra por letra con las reglas
            for (let i = 0; i < val.length; i++) {
                if (i < 4) {
                    // Posición 1 a 4: SOLO LETRAS
                    if (/[A-Z]/.test(val[i])) {
                        result += val[i];
                    }
                } else if (i < 6) {
                    // Agregamos el guion automáticamente al llegar al 5to caracter válido
                    if (i === 4 && result.length === 4) result += '-';

                    // Posición 5 y 6: SOLO NÚMEROS
                    if (/[0-9]/.test(val[i])) {
                        result += val[i];
                    }
                }
            }
            // 3. Devolvemos el texto formateado a la caja
            $(this).val(result);
        });

    });
</script>