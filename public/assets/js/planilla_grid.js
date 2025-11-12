$(document).ready(function () {

    // --- Selectores Globales ---
    const $gridBody = $('#grid-body');
    const $plantillaFila = $('#plantilla-fila-trabajador');

    // Selectores de columnas condicionales (afecta <th> y <td>)
    const $colFechaTermino = $('.col-fecha-termino');
    const $colCotizaCesantia = $('.col-cotiza-cesantia');

    // --- Lógica de UI Condicional ---

    /**
     * Revisa todas las filas y decide si las COLUMNAS condicionales
     * deben mostrarse u ocultarse.
     */
    function actualizarColumnasVisibles() {
        let mostrarFechaTermino = false;
        let mostrarCotizaCesantia = false;

        // 1. Revisar cada fila para ver si *alguna* cumple la condición
        $gridBody.find('.fila-trabajador').each(function () {
            const $fila = $(this);
            const tipoContrato = $fila.find('.tipo-contrato').val();
            const estadoPrevisional = $fila.data('estado-previsional');

            // --- Lógica para Fecha Término (col 6) ---
            if (tipoContrato === 'Fijo') {
                mostrarFechaTermino = true;
                $fila.find('.fecha-termino').prop('disabled', false); // Habilitar input
            } else {
                $fila.find('.fecha-termino').prop('disabled', true).val(''); // Deshabilitar y limpiar
            }

            // --- Lógica para Cotiza Cesantía (col 10) ---
            // CAMBIO: Apuntar al '.toggle-wrapper'
            const $toggleWrapper = $fila.find('.toggle-wrapper');
            const $hiddenInput = $fila.find('.cotiza-cesantia-hidden');

            if (estadoPrevisional === 'Pensionado') {
                mostrarCotizaCesantia = true;
                $toggleWrapper.show(); // MOSTRAR el toggle
                // (no deshabilitamos el input, ya que es visible y usable)
            } else {
                $toggleWrapper.hide(); // OCULTAR el toggle
                $hiddenInput.val('0'); // Asegurarse que el valor sea 0
                $toggleWrapper.find('.form-check-input').prop('checked', false); // Desmarcar por si acaso
            }
        });

        // 2. Ocultar/Mostrar COLUMNAS COMPLETAS (<th> y <td>)
        $colFechaTermino.toggle(mostrarFechaTermino);
        $colCotizaCesantia.toggle(mostrarCotizaCesantia);
    }


    // --- Inicialización al Cargar Página ---

    // 1. Select2 para AGREGAR trabajador
    $('#select-agregar-trabajador').select2({
        theme: 'bootstrap-5',
        placeholder: $(this).data('placeholder')
    });

    // 2. Select2 para selects DE TIPO CONTRATO ya cargados
    $('#grid-body .tipo-contrato').select2({
        theme: 'bootstrap-5',
        minimumResultsForSearch: Infinity
    });

    // 3. Ejecutar la lógica de columnas al cargar
    actualizarColumnasVisibles();


    // --- 1. Evento: Agregar Trabajador ---
    $('#btn-agregar-trabajador').on('click', function () {
        const $select = $('#select-agregar-trabajador');
        const selectedOption = $select.find('option:selected');

        if (!selectedOption.val()) {
            Swal.fire('Error', 'Debes seleccionar un trabajador para agregar.', 'error');
            return;
        }

        const id = selectedOption.val();
        const nombre = selectedOption.data('nombre');
        const rut = selectedOption.data('rut');
        const estado = selectedOption.data('estado');

        if ($gridBody.find(`.fila-trabajador[data-id-trabajador="${id}"]`).length > 0) {
            Swal.fire('Error', `El trabajador ${nombre} ya está en la grilla.`, 'warning');
            return;
        }

        let $nuevaFila = $plantillaFila.clone();
        $nuevaFila.removeAttr('id').addClass('fila-trabajador').attr('data-id-trabajador', id);
        $nuevaFila.attr('data-estado-previsional', estado);

        let htmlFila = $nuevaFila.html().replace(/{ID}/g, id)
            .replace(/{NOMBRE}/g, nombre)
            .replace(/{RUT}/g, rut);

        $nuevaFila.html(htmlFila);
        $gridBody.append($nuevaFila);

        // Inicializar Select2 en la NUEVA fila
        $nuevaFila.find('.tipo-contrato').select2({
            theme: 'bootstrap-5',
            minimumResultsForSearch: Infinity
        });

        $select.val(null).trigger('change');

        // Re-evaluar columnas
        actualizarColumnasVisibles();
    });


    // --- 2. Eventos Delegados (Dentro de la Grilla) ---

    // Quitar Fila
    $gridBody.on('click', '.btn-quitar-fila', function () {
        const $fila = $(this).closest('tr');
        const nombre = $fila.find('td:first').text().trim().split('\n')[0];

        Swal.fire({
            title: '¿Quitar Trabajador?',
            text: `Vas a quitar a ${nombre} de esta planilla. Los cambios se guardarán al presionar "Guardar Planilla".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $fila.remove();
                actualizarColumnasVisibles(); // Re-evaluar al quitar
            }
        });
    });

    // Cuando cambia "Tipo Contrato"
    $gridBody.on('change', '.tipo-contrato', function () {
        actualizarColumnasVisibles(); // Re-evaluar todo
    });

    // Validar Días Trabajados
    $gridBody.on('input', '.dias-trabajados', function () {
        if (parseInt($(this).val()) > 30) $(this).val(30);
        if (parseInt($(this).val()) < 0) $(this).val(0);
    });

    // Checkbox de cotización de cesantía
    $gridBody.on('change', '.col-cotiza-cesantia .form-check-input', function () {
        const $checkbox = $(this);
        const $hiddenInput = $checkbox.closest('td').find('input[name="cotiza_cesantia_pensionado_hidden[]"]');
        $hiddenInput.val($checkbox.is(':checked') ? '1' : '0');
    });


    // --- 3. Evento: Guardar Planilla (Sin cambios) ---
    $('#btn-guardar-planilla').on('click', function () {

        let planillaData = {
            empleador_id: $('#empleador_id').val(),
            mes: $('#mes').val(),
            ano: $('#ano').val(),
            trabajadores: []
        };

        // --- CAMBIO: Iterar por fila (no por dataMap) ---
        $gridBody.find('.fila-trabajador').each(function () {
            const $fila = $(this);

            // Leer el valor del input de fecha (puede estar disabled)
            const $inputFechaTermino = $fila.find('.fecha-termino');
            let fechaTerminoValor = null;
            if (!$inputFechaTermino.prop('disabled')) {
                fechaTerminoValor = $inputFechaTermino.val() || null;
            }

            // Leer el valor del hidden field del toggle
            const cotizaCesantiaValor = $fila.find('.cotiza-cesantia-hidden').val() || '0';

            // Construir el objeto para este trabajador
            let trabajador = {
                trabajador_id: $fila.find('input[name="trabajador_id[]"]').val(),
                sueldo_imponible: $fila.find('input[name="sueldo_imponible[]"]').val() || 0,
                dias_trabajados: $fila.find('input[name="dias_trabajados[]"]').val() || 30,
                tipo_contrato: $fila.find('select[name="tipo_contrato[]"]').val(),
                fecha_inicio: $fila.find('input[name="fecha_inicio[]"]').val(),
                fecha_termino: fechaTerminoValor,
                aportes: $fila.find('input[name="aportes[]"]').val() || 0,
                adicional_salud_apv: $fila.find('input[name="adicional_salud_apv[]"]').val() || 0,
                cesantia_licencia_medica: $fila.find('input[name="cesantia_licencia_medica[]"]').val() || 0,
                cotiza_cesantia_pensionado: cotizaCesantiaValor
            };

            planillaData.trabajadores.push(trabajador);
        });
        Swal.fire({
            title: 'Guardando Planilla...',
            text: 'Calculando cotizaciones y guardando datos.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch(GUARDAR_PLANILLA_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(planillaData)
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || 'Error del servidor'); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.close();
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: data.message
                    });
                } else {
                    Swal.fire('Error', data.message || 'No se pudo guardar la planilla.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Hubo un problema de conexión: ' + error.message, 'error');
            });
    });
});