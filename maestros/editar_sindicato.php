<?php

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

require_once dirname(__DIR__) . '/app/includes/session_check.php';



if (!isset($_GET['id'])) {

    header('Location: ' . BASE_URL . '/sindicatos');

    exit;
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM sindicatos WHERE id = ?");
$stmt->execute([$id]);
$sindicato = $stmt->fetch();



require_once dirname(__DIR__) . '/app/includes/header.php';

?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800">Editar Sindicato</h1>

    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($sindicato['nombre']); ?></h6>

        </div>

        <div class="card-body">

            <form action="<?php echo BASE_URL; ?>/maestros/editar_sindicato_process.php" method="POST">

                <input type="hidden" name="id" value="<?php echo $sindicato['id']; ?>">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">Nombre Sindicato</label>

                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($sindicato['nombre']); ?>" readonly disabled>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="descuento" class="form-label">Descuento (Monto Fijo en Pesos)</label>

                        <div class="input-group">

                            <span class="input-group-text">$</span>

                            <input type="text" class="form-control currency-input" id="descuento" name="descuento" value="<?php echo number_format($sindicato['descuento'], 0, ',', '.'); ?>" required>

                        </div>

                    </div>

                </div>

                <hr>

                <a href="<?php echo BASE_URL; ?>/sindicatos" class="btn btn-secondary">Cancelar</a>

                <button type="submit" class="btn btn-primary">Guardar Cambios</button>

            </form>

        </div>

    </div>

</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Helper to format number with dots
        function formatNumber(n) {
            n = n.replace(/\D/g, "");
            return n === "" ? "" : Number(n).toLocaleString("es-CL");
        }

        // Helper to unformat number (remove dots)
        function unformatNumber(n) {
            return n.replace(/\./g, "");
        }

        // Apply formatting to currency inputs
        const discountInput = document.querySelector('.currency-input');
        if (discountInput) {
            discountInput.addEventListener('input', function() {
                const rawValue = unformatNumber(this.value);
                this.value = formatNumber(rawValue);
            });
        }

        // Unformat values before submitting
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                document.querySelectorAll('.currency-input').forEach(input => {
                    input.value = unformatNumber(input.value);
                });
            });
        }
    });
</script>