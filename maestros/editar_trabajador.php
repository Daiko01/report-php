<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';

if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
    exit;
}
$id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM trabajadores WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $trabajador = $stmt->fetch();
    if (!$trabajador) {
        header('Location: ' . BASE_URL . '/maestros/gestionar_trabajadores.php');
        exit;
    }

    $afps = $pdo->query("SELECT id, nombre FROM afps ORDER BY nombre")->fetchAll();
    $sindicatos = $pdo->query("SELECT id, nombre FROM sindicatos ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
    die("Error.");
}

require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Trabajador</h1>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="editar_trabajador_process.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $trabajador['id']; ?>">

                <h5 class="mb-3">Información Personal</h5>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($trabajador['nombre']); ?>" readonly disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">RUT</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($trabajador['rut']); ?>" readonly disabled>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Información Previsional</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sistema_previsional" class="form-label">Sistema Previsional</label>
                        <select class="form-select" id="sistema_previsional" name="sistema_previsional" required>
                            <option value="AFP" <?php echo ($trabajador['sistema_previsional'] == 'AFP') ? 'selected' : ''; ?>>AFP</option>
                            <option value="INP" <?php echo ($trabajador['sistema_previsional'] == 'INP') ? 'selected' : ''; ?>>INP (Ex-Caja)</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3" id="afp_wrapper">
                        <label for="afp_id" class="form-label">AFP</label>
                        <select class="form-select" id="afp_id" name="afp_id">
                            <option value="">Seleccione...</option>
                            <?php foreach ($afps as $afp): ?>
                                <option value="<?php echo $afp['id']; ?>" <?php echo ($afp['id'] == $trabajador['afp_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($afp['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3" id="inp_wrapper" style="display: none;">
                        <label for="tasa_inp" class="form-label">Tasa INP (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="tasa_inp" name="tasa_inp" value="<?php echo ($trabajador['tasa_inp_decimal'] * 100); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="estado_previsional" class="form-label">Estado</label>
                        <select class="form-select" id="estado_previsional" name="estado_previsional" required>
                            <option value="Activo" <?php echo ($trabajador['estado_previsional'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Pensionado" <?php echo ($trabajador['estado_previsional'] == 'Pensionado') ? 'selected' : ''; ?>>Pensionado</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="sindicato_id" class="form-label">Sindicato</label>
                        <select class="form-select" id="sindicato_id" name="sindicato_id">
                            <option value="">Ninguno</option>
                            <?php foreach ($sindicatos as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $trabajador['sindicato_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch form-check-lg mt-4">
                            <input class="form-check-input" type="checkbox" id="tiene_cargas" name="tiene_cargas" value="1" <?php echo $trabajador['tiene_cargas'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tiene_cargas">¿Tiene Cargas?</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3" id="numero_cargas_wrapper" style="display: none;">
                        <label for="numero_cargas" class="form-label">N° de Cargas</label>
                        <input type="number" class="form-control" id="numero_cargas" name="numero_cargas" value="<?php echo $trabajador['numero_cargas']; ?>" min="0">
                    </div>
                </div>

                <hr>
                <a href="gestionar_trabajadores.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        const $sistemaSelect = $('#sistema_previsional');
        const $afpWrapper = $('#afp_wrapper');
        const $inpWrapper = $('#inp_wrapper');
        const $afpSelect = $('#afp_id');
        const $inpInput = $('#tasa_inp');
        const $estadoPrevisional = $('#estado_previsional');
        const $tieneCargas = $('#tiene_cargas');
        const $numeroCargasWrapper = $('#numero_cargas_wrapper');
        const $numeroCargasInput = $('#numero_cargas');

        function toggleSistema() {
            if ($sistemaSelect.val() === 'INP') {
                $afpWrapper.hide();
                $inpWrapper.show();
                $afpSelect.prop('required', false);
                $inpInput.prop('required', true);
            } else {
                $afpWrapper.show();
                $inpWrapper.hide();
                $afpSelect.prop('required', true);
                $inpInput.prop('required', false);
            }
        }

        function togglePensionado() {
            if ($estadoPrevisional.val() === 'Pensionado') {
                $afpSelect.prop('disabled', true).prop('required', false);
                $inpInput.prop('disabled', true).prop('required', false);
            } else {
                $afpSelect.prop('disabled', false);
                $inpInput.prop('disabled', false);
                toggleSistema(); // Re-aplicar reglas de sistema
            }
        }

        function toggleCargas() {
            if ($tieneCargas.is(':checked')) {
                $numeroCargasWrapper.show();
                $numeroCargasInput.prop('disabled', false);
            } else {
                $numeroCargasWrapper.hide();
                $numeroCargasInput.prop('disabled', true);
            }
        }

        $sistemaSelect.on('change', toggleSistema);
        $estadoPrevisional.on('change', togglePensionado);
        $tieneCargas.on('change', toggleCargas);

        toggleSistema();
        togglePensionado();
        toggleCargas();

        // Validación Bootstrap... (igual al create)
    });
</script>