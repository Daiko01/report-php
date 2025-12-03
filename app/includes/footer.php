</main>
</div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>

<script src="<?php echo BASE_URL; ?>/public/assets/js/main.js"></script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/rut_validator.js"></script>


<script>
    $(document).ready(function() {
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('active');
            $(this).toggleClass('active');
        });

        // ... (El resto del script de inicializaci¨®n de DataTables, Select2, etc.)
        // (Este c¨®digo no necesita cambios)
        $('select:not(.no-select2)').select2({
            theme: 'bootstrap-5'
        });

        $('.datatable-es:not(#tablaEmpleadores, #tablaTrabajadores, #tablaContratos, #tablaCierres)').DataTable({
            language: {
                url: '<?php echo BASE_URL; ?>/public/assets/vendor/datatables/Spanish.json'
            }
        });
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        <?php
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            $type = $flash['type'];
            $message = addslashes($flash['message']);

            unset($_SESSION['flash_message']);

            if ($type == 'success') {
                echo "Toast.fire({ icon: 'success', title: '{$message}' });";
            } else {
                echo "Swal.fire({ icon: '{$type}', title: 'Error', text: '{$message}' });";
            }
        }
        ?>
    });
</script>

</body>

</html>