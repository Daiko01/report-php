<?php

// 1. Cargar el núcleo (Sesión y BD)

require_once dirname(__DIR__) . '/app/core/bootstrap.php';



// 2. Verificar Sesión y Rol de Admin

require_once dirname(__DIR__) . '/app/includes/session_check.php';

if ($_SESSION['user_role'] != 'admin') {

    header('Location: ../index.php');

    exit;

}



// 3. Cargar el Header (Layout)

require_once dirname(__DIR__) . '/app/includes/header.php';

?>



<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Usuario</h1>



    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">Datos del Usuario</h6>

        </div>

        <div class="card-body">

            

            <form action="crear_usuario_process.php" method="POST" class="needs-validation" novalidate>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="nombre_completo" class="form-label">Nombre Completo</label>

                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>

                        <div class="invalid-feedback">

                            El nombre completo es obligatorio.

                        </div>

                    </div>

                    

                    <div class="col-md-6 mb-3">

                        <label for="email" class="form-label">Email</label>

                        <input type="email" class="form-control" id="email" name="email" required>

                        <div class="invalid-feedback">

                            Por favor, ingresa un email válido.

                        </div>

                    </div>

                </div>



                <hr>



                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label for="username" class="form-label">Usuario (RUT)</label>

                        <input type="text" class="form-control rut-input" id="username" name="username" maxlength="12" required>

                        <div class="invalid-feedback">

                            El RUT es obligatorio.

                        </div>

                    </div>



                    <div class="col-md-4 mb-3">

                        <label for="password" class="form-label">Contraseña</label>

                        <input type="password" class="form-control" id="password" name="password" required>

                         <div class="invalid-feedback">

                            La contraseña es obligatoria.

                        </div>

                    </div>



                    <div class="col-md-4 mb-3">

                        <label for="role" class="form-label">Rol</label>

                        <select class="form-select" id="role" name="role" required>

                            <option value="contador">Contador (contador)</option>

                            <option value="admin">Administrador (admin)</option>

                        </select>

                    </div>

                </div>



                <hr>

                <a href="gestionar_usuarios.php" class="btn btn-secondary">Cancelar</a>

                <button type="submit" class="btn btn-primary">Guardar Usuario</button>

            </form>



        </div>

    </div>

</div>



<?php

// 4. Cargar el Footer (Layout y JS)

require_once dirname(__DIR__) . '/app/includes/footer.php';

?>



<script>

// Example starter JavaScript for disabling form submissions if there are invalid fields

(function () {

  'use strict'

  var forms = document.querySelectorAll('.needs-validation')

  Array.prototype.slice.call(forms)

    .forEach(function (form) {

      form.addEventListener('submit', function (event) {

        if (!form.checkValidity()) {

          event.preventDefault()

          event.stopPropagation()

        }

        form.classList.add('was-validated')

      }, false)

    })

})()

</script>