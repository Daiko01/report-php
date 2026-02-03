<?php
// buses/resumen_diario.php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
require_once dirname(__DIR__) . '/app/includes/session_check.php';
require_once dirname(__DIR__) . '/app/includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Resumen Diario de Guías (Cuadratura)</h1>
    <a href="ingreso_guia.php" class="btn btn-secondary btn-sm shadow-sm">
        <i class="fas fa-arrow-left me-1"></i> Volver a Ingreso
    </a>
</div>

<!-- FILTROS -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-gradient-light d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-day me-1"></i> Selección de Día</h6>

        <!-- Botones de Acción Rápida -->
        <div>
            <button class="btn btn-sm btn-success shadow-sm me-1" onclick="exportData('excel')"><i class="fas fa-file-excel me-1"></i> Excel</button>
            <button class="btn btn-sm btn-dark shadow-sm" onclick="openPrintWindow()"><i class="fas fa-print me-1"></i> Imprimir</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Fecha del Resumen</label>
                <input type="date" class="form-control" id="filtroFecha" value="<?= $_GET['fecha'] ?? date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100 fw-bold" id="btnBuscar">
                    <i class="fas fa-sync-alt me-1"></i> Actualizar
                </button>
            </div>
            <div class="col-md-7 text-end d-none d-md-block">
                <div class="small text-muted fst-italic">
                    Mostrando guías ingresadas para el día seleccionado.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABLA -->
<div class="card shadow mb-4" id="printableArea">
    <div class="card-header py-3 bg-white d-block d-print-none">
        <h6 class="m-0 font-weight-bold text-dark">Detalle de Recaudación</h6>
    </div>

    <!-- Header para Impresión (Solo visible al imprimir) -->
    <div class="d-none d-print-block ticket-header">
        <h4 class="text-uppercase">Resumen Diario</h4>
        <p>Fecha: <strong id="printDate"></strong></p>
        <p><small><?= date('d/m/Y H:i') ?></small></p>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover w-100" id="tablaResumen">
                <thead class="bg-light text-center">
                    <tr>
                        <th width="10%">Nro Guía</th>
                        <th width="15%">Bus</th>
                        <th>Conductor</th>
                        <th width="12%">Boletos</th>
                        <th width="12%">Admin.</th>
                        <th width="12%">Imposiciones</th>
                        <th width="15%" class="table-primary">Total</th>
                    </tr>
                </thead>
                <tbody class="text-center align-middle">
                    <!-- JS Loaded -->
                </tbody>
                <tfoot class="bg-light fw-bold text-center">
                    <tr>
                        <td colspan="3" class="text-end pe-3">TOTALES DEL DÍA:</td>
                        <td id="footBoletos">$ 0</td>
                        <td id="footAdmin">$ 0</td>
                        <td id="footImp">$ 0</td>
                        <td id="footTotal" class="bg-primary text-white">$ 0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>

<script>
    $(document).ready(function() {

        // Configuración DataTable
        const table = $('#tablaResumen').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            paging: false, // Mostrar todo para cuadrar caja
            ordering: true, // Permitir ordenar por folio
            info: false,
            searching: true, // Buscador rápido
            dom: 'tr', // Solo tabla (sin paginación ni buscador default, usaremos custom si es necesario o default 'f' si preferimos)
        });

        // Load Data Logic
        function loadResumen() {
            const fecha = $('#filtroFecha').val();
            $('#printDate').text(fecha);

            Swal.showLoading();

            fetch(`../ajax/get_resumen_diario.php?fecha=${fecha}`)
                .then(r => r.json())
                .then(res => {
                    Swal.close();
                    if (res.success) {
                        renderTable(res.data, res.totales);

                        // Auto Print Logic
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.get('auto_print') === 'true') {
                            setTimeout(() => {
                                window.print();
                            }, 500);
                        }
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }

        function renderTable(rows, totalData) {
            // 1. Limpiar Tabla
            table.clear();

            // 2. Agregar Filas
            // Note: AJAX now returns "bus" as raw number. We prepend "Nº " here for display.
            const newRows = rows.map(r => [
                r.folio,
                "Nº " + r.bus,
                r.conductor,
                formatMoney(r.boletos),
                formatMoney(r.administracion),
                formatMoney(r.imposiciones),
                formatMoney(r.total)
            ]);

            table.rows.add(newRows).draw();

            // 3. Actualizar Footer
            $('#footBoletos').text('$' + formatMoney(totalData.boletos));
            $('#footAdmin').text('$' + formatMoney(totalData.admin));
            $('#footImp').text('$' + formatMoney(totalData.imposiciones));
            $('#footTotal').text('$' + formatMoney(totalData.ingreso));
        }

        // Helper Money
        function formatMoney(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Listeners
        $('#btnBuscar').click(loadResumen);

        // Initial Load
        loadResumen();

        // Open Print in New Tab (Voucher dedicated file)
        window.openPrintWindow = function() {
            const fecha = $('#filtroFecha').val();
            // Open clean voucher file in new tab
            const url = `imprimir_resumen_diario.php?fecha=${fecha}`;
            window.open(url, '_blank');
        }

        // Export Function
        window.exportData = function() {
            // ... (Export logic remains same) ...
            // 1. Add BOM for Excel UTF-8 recognition
            let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
            csvContent += "Nro Guía;Bus;Conductor;Boletos;Administracion;Imposiciones;Total\n";

            // Get data from current table API
            const data = table.rows().data().toArray();

            // Init Totals
            let tBol = 0,
                tAdm = 0,
                tImp = 0,
                tTot = 0;

            data.forEach(function(rowArray) {
                // rowArray: [Nro Guía, Bus, Conductor, Bol, Adm, Imp, Tot]

                // Parse Amounts (Remove dots to sum)
                const boletos = parseInt(rowArray[3].toString().replace(/\./g, '')) || 0;
                const admin = parseInt(rowArray[4].toString().replace(/\./g, '')) || 0;
                const imp = parseInt(rowArray[5].toString().replace(/\./g, '')) || 0;
                const total = parseInt(rowArray[6].toString().replace(/\./g, '')) || 0;

                tBol += boletos;
                tAdm += admin;
                tImp += imp;
                tTot += total;

                // Clean Row for CSV export
                // Remove visual "Nº " from bus number (Index 1)
                // Remove dots from money columns (Index 3+)
                const cleanRow = rowArray.map((cell, index) => {
                    if (index === 1) return cell.toString().replace("Nº ", "").trim();
                    if (index >= 3) return cell.toString().replace(/\./g, '');
                    return cell;
                });
                csvContent += cleanRow.join(";") + "\n";
            });

            // Append Totals Row
            csvContent += `;;TOTAL;${tBol};${tAdm};${tImp};${tTot}\n`;

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `Resumen_Diario_${$('#filtroFecha').val()}.csv`);
            document.body.appendChild(link); // Required for FF
            link.click();
        }
    });
</script>

<style media="print">
    @page {
        size: 80mm auto;
        margin: 0;
    }

    body {
        margin: 0;
        padding: 5px;
        font-family: 'Courier New', monospace;
        font-size: 10px;
        color: #000;
        background: #fff;
    }

    /* Ocultar elementos de navegación/interfaz */
    body * {
        visibility: hidden;
    }

    #printableArea,
    #printableArea * {
        visibility: visible;
    }

    #printableArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none !important;
    }

    /* Header del Ticket */
    .ticket-header {
        text-align: center;
        margin-bottom: 5px;
        padding-bottom: 5px;
        border-bottom: 1px dashed #000;
    }

    .ticket-header h4 {
        font-size: 12px;
        margin: 0;
        text-transform: uppercase;
    }

    .ticket-header p {
        margin: 2px 0;
        font-size: 10px;
    }

    /* Tabla Ticket */
    .table-responsive {
        overflow: visible !important;
    }

    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 5px !important;
    }

    /* Celdas */
    .table th {
        font-size: 9px;
        border-bottom: 1px solid #000 !important;
        padding: 2px 0 !important;
        text-align: center;
    }

    .table td {
        font-size: 9px;
        border-bottom: 1px dashed #ccc !important;
        padding: 2px 0 !important;
        vertical-align: top;
        word-wrap: break-word;
    }

    /* Totales Footer */
    tfoot {
        border-top: 2px solid #000 !important;
        font-weight: bold;
    }

    tfoot td {
        font-size: 10px;
        padding-top: 5px !important;
    }

    /* Ocultar bordes innecesarios */
    .table-bordered th,
    .table-bordered td {
        border: none !important;
    }

    /* Utility */
    .d-print-none {
        display: none !important;
    }

    .d-print-block {
        display: block !important;
    }
</style>