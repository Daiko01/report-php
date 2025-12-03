/**
 * Formatea y valida un RUT Chileno en el frontend
 */
var RutValidator = {
    // Función para limpiar y formatear el RUT mientras se escribe
    format: function (rut) {
        // Limpiar
        var rutLimpio = rut.replace(/[^0-9kK]/g, '');

        // Aislar DV
        var dv = rutLimpio.slice(-1).toUpperCase();
        var cuerpo = rutLimpio.slice(0, -1);

        // Prevenir formato si no hay cuerpo
        if (cuerpo.length === 0) {
            return dv; // Devuelve solo el DV si es lo único que hay
        }
        if (rutLimpio.length <= 1) {
            return rutLimpio;
        }

        // Formatear cuerpo
        var cuerpoFormateado = "";
        while (cuerpo.length > 3) {
            cuerpoFormateado = "." + cuerpo.slice(-3) + cuerpoFormateado;
            cuerpo = cuerpo.slice(0, -3);
        }
        cuerpoFormateado = cuerpo + cuerpoFormateado;

        return cuerpoFormateado + "-" + dv;
    },

    // Aplicar el formateo a un input
    init: function (selector) {
        // Usamos 'input' para que se actualice en tiempo real
        $(document).on('input', selector, function () {
            var $this = $(this);
            var valorActual = $this.val();
            // Formatear
            var valorFormateado = RutValidator.format(valorActual);
            // Reemplazar el valor en el input
            $this.val(valorFormateado);
        });
    }
};

// Inicializar para todos los inputs con la clase 'rut-input'
$(document).ready(function () {
    RutValidator.init('.rut-input');
});