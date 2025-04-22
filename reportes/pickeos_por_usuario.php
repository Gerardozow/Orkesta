<?php
// reportes/pickeos_por_usuario.php - Reporte de WOs Pickeadas (COMPLETO) por Usuario
// Fecha de última revisión: 22 de Abril de 2025

require_once('../includes/load.php');
requerir_login();

// --- Permisos (Coincidir con AJAX endpoint) ---
$roles_permitidos = ['Admin', 'Supervisor Almacen'];
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol($roles_permitidos)) {
    if (function_exists('mensaje_sesion')) {
         mensaje_sesion("No tienes permiso para acceder a esta sección.", "danger");
    }
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Variables de Plantilla ---
$page_title = 'Reporte de Pickeos Completados por Usuario';
$active_page = 'reportes';
$active_subpage = 'pickeos_por_usuario'; // Para el menú lateral

// --- Incluir Layout ---
include_once('../layouts/header.php'); // Necesita Chart.js y DataTables CSS
?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <?php // --- Fila para Gráfica --- ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card flex-fill w-100">
                        <div class="card-header">
                             <h5 class="card-title mb-0">Pickeos Completados por Usuario (Total Histórico)</h5>
                             <h6 class="card-subtitle text-muted pt-1">Cantidad total de Work Orders distintas marcadas como completadas por cada usuario.</h6>
                        </div>
                        <div class="card-body pt-2 pb-3">
                            <div class="chart chart-lg" style="position: relative; height:400px;">
                                <canvas id="chartjs-pickeos-usuario"></canvas>
                            </div>
                             <div id="chart-loading-indicator" class="text-center p-4" style="display: none;">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                                <p class="mt-2">Cargando datos de gráfica...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php // --- Fin Fila Gráfica --- ?>

            <?php // --- Tabla Resumen --- ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                     <div>
                        <h5 class="card-title mb-0">Detalle por Usuario</h5>
                        <h6 class="card-subtitle text-muted pt-1">Tabla resumen de pickeos completados por usuario.</h6>
                    </div>
                    <div class="dt-buttons-container">
                        </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-pickeos-usuario" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th class="text-end">Total Pickeos Completados</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                             <tfoot>
                                <tr>
                                    <th>Total General</th>
                                    <th class="text-end" id="total-general-pickeos">Calculando...</th>
                                </tr>
                            </tfoot>
                        </table>
                         <div id="table-loading-indicator" class="text-center p-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                            <p class="mt-2">Cargando tabla...</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php // --- Fin Tabla Resumen --- ?>

        </div>
    </main>

    <?php include_once('../layouts/footer.php'); // Necesita Chart.js, DataTables JS (+ Buttons) ?>
</div>

<?php // --- Scripts JS --- ?>
<script>
    // Fallbacks y verificaciones
    if (typeof jQuery == 'undefined') { console.error("jQuery no cargado."); }
    if (typeof $.fn.dataTable == 'undefined') { console.error("DataTables no cargado."); }
    if (typeof $.fn.dataTable.Buttons == 'undefined') { console.warn("DataTables Buttons no cargado (Exportación no funcionará)."); }
    if (typeof Chart == 'undefined') { console.error("Chart.js no está cargado."); }
</script>

<script>
$(document).ready(function() {

    let userPicksChartInstance = null;
    let userPicksTableInstance = null;
    let isTableInitialized = false;

    function showLoading(type) { $(`#${type}-loading-indicator`).show(); }
    function hideLoading(type) { $(`#${type}-loading-indicator`).hide(); }

    /**
     * Obtiene y procesa los datos de pickeos por usuario
     */
    function fetchDataAndInitialize() {
        const ajaxUrl = '../ajax/get_pickeos_por_usuario.php';
        console.log("Fetching user pick counts from:", ajaxUrl);
        showLoading('chart'); // Mostrar ambos loaders inicialmente
        showLoading('table');

        fetch(ajaxUrl)
            .then(response => { if (!response.ok) { throw new Error(`Network error (${response.status})`); } return response.json(); })
            .then(json => {
                 console.log("User pick counts received:", json);
                 hideLoading('chart'); // Ocultar loaders
                 hideLoading('table');

                 let userPicksData = [];
                 if (json && json.data) {
                     userPicksData = json.data;
                 } else { throw new Error('Formato de datos inválido o clave "data" ausente.'); }

                 // Renderizar Gráfica
                 if (typeof Chart !== 'undefined') {
                     try { renderUserPicksChart(userPicksData); }
                     catch (e) { console.error("Error rendering user picks chart:", e); displayChartError('Error al generar gráfica.'); }
                 } else { console.warn("Chart.js not available."); displayChartError('Librería de gráficas no disponible.'); }

                 // Inicializar Tabla
                 if ($('#tabla-pickeos-usuario').length && typeof $.fn.dataTable !== 'undefined' && !isTableInitialized) {
                    try { initializeUserPicksTable(userPicksData); isTableInitialized = true; }
                    catch (e) { console.error("Error initializing user picks table:", e); $('#tabla-pickeos-usuario tbody').html('<tr><td colspan="2" class="text-center text-danger">Error al inicializar tabla.</td></tr>'); }
                 }
            })
            .catch(error => {
                console.error('Error fetching user pick counts:', error);
                hideLoading('chart'); hideLoading('table');
                $('#tabla-pickeos-usuario tbody').html(`<tr><td colspan="2" class="text-center text-danger">Error al cargar datos: ${error.message}</td></tr>`);
                displayChartError(`Error al cargar datos: ${error.message}`);
            });
    }

    /**
     * Muestra un mensaje de error en el contenedor de la gráfica
     */
    function displayChartError(message) {
         $('#chartjs-pickeos-usuario').parent().empty().html(`<div class="text-center text-muted p-3">${message}</div>`);
    }

    /**
     * Inicializa la tabla DataTables con los datos de pickeos por usuario.
     */
    function initializeUserPicksTable(tableData) {
        if ($.fn.DataTable.isDataTable('#tabla-pickeos-usuario')) { $('#tabla-pickeos-usuario').DataTable().destroy(); $('#tabla-pickeos-usuario tbody').empty(); isTableInitialized = false; }

        let totalGeneral = 0; // Calcular total general
        tableData.forEach(item => { totalGeneral += parseInt(item.total_pickeos || 0); });
        $('#total-general-pickeos').text(new Intl.NumberFormat('es-MX').format(totalGeneral)); // Mostrar total en footer


        userPicksTableInstance = $('#tabla-pickeos-usuario').DataTable({
            responsive: true, processing: true, pageLength: 25, // Mostrar 25 por defecto
            data: tableData,
            columns: [
                { data: 'nombre_usuario', render: $.fn.dataTable.render.text() }, // Nombre de usuario
                {
                    data: 'total_pickeos', // Conteo
                    className: 'text-end', // Alinear a la derecha
                    render: function(data, type, row){ // Formatear número
                         return new Intl.NumberFormat('es-MX').format(data || 0);
                    }
                }
            ],
             dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
             language: { // Lenguaje inline como se pidió
                 lengthMenu: "Mostrar _MENU_ usuarios", zeroRecords: "No se encontraron usuarios con pickeos completados", info: "Mostrando _START_ a _END_ de _TOTAL_ usuarios", infoEmpty: "Mostrando 0 usuarios", infoFiltered: "(filtrado de _MAX_)", search: "_INPUT_", searchPlaceholder: "Buscar usuario...", paginate: { first: "<<", last: ">>", next: ">", previous: "<" }, emptyTable: "No hay datos de pickeos por usuario.", processing: 'Procesando...'
             },
             lengthMenu: [ [10, 25, 50, -1], [10, 25, 50, 'Todos'] ],
             order: [ [1, 'desc'] ], // Ordenar por total descendente por defecto
             drawCallback: function(settings) { if (typeof feather !== 'undefined') { feather.replace(); } /* Tooltips si se usan */ }
        });

        // --- Inicializar y Posicionar Botones de Exportación ---
        if (typeof $.fn.dataTable.Buttons !== 'undefined') {
             new $.fn.dataTable.Buttons(userPicksTableInstance, {
                 buttons: [
                     { extend: 'excelHtml5', text: '<i class="align-middle" data-feather="file"></i> Excel', className: 'btn btn-success btn-sm', titleAttr: 'Excel' },
                     { extend: 'pdfHtml5', text: '<i class="align-middle" data-feather="file-text"></i> PDF', className: 'btn btn-danger btn-sm', titleAttr: 'PDF' },
                     { extend: 'csvHtml5', text: '<i class="align-middle" data-feather="save"></i> CSV', className: 'btn btn-secondary btn-sm', titleAttr: 'CSV' }
                 ]
             });
             var buttonContainer = userPicksTableInstance.buttons().container();
             buttonContainer.appendTo($('#tabla-pickeos-usuario').closest('.card').find('.dt-buttons-container'));
             buttonContainer.find('.dt-buttons').addClass('btn-group flex-wrap');
             buttonContainer.find('.btn').removeClass('ms-1').addClass('m-1');
             if (typeof feather !== 'undefined') { feather.replace(); } // Actualizar iconos botones
        } else { console.warn("DataTables Buttons extension no está cargada."); }

    } // Fin initializeUserPicksTable

    /**
     * Renderiza la gráfica de barras de pickeos por usuario
     */
    function renderUserPicksChart(chartData) {
        const ctx = document.getElementById('chartjs-pickeos-usuario')?.getContext('2d');
        if (!ctx) { console.error("Canvas para gráfica de pickeos no encontrado"); return; }
        if (userPicksChartInstance) { userPicksChartInstance.destroy(); }

        const labels = chartData.map(item => item.nombre_usuario);
        const data = chartData.map(item => parseInt(item.total_pickeos || 0));

        // Si no hay datos, mostrar mensaje en lugar de gráfica vacía
        if (labels.length === 0 || data.length === 0) {
            displayChartError('No hay datos de pickeos completados para mostrar en la gráfica.');
            return;
        }

        const backgroundColors = ['rgba(59, 125, 221, 0.7)', 'rgba(40, 167, 69, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(108, 117, 125, 0.7)', 'rgba(23, 162, 184, 0.7)', 'rgba(253, 126, 20, 0.7)', 'rgba(102, 16, 242, 0.7)', 'rgba(232, 62, 140, 0.7)'];
        const chartBackgroundColors = labels.map((_, i) => backgroundColors[i % backgroundColors.length]);
        const chartBorderColors = chartBackgroundColors.map(color => color.replace('0.7', '1'));
        const maxValue = data.length > 0 ? Math.max(...data) : 10;
        const suggestedMax = Math.ceil(maxValue * 1.1) + 5; // Margen para el eje Y

        userPicksChartInstance = new Chart(ctx, {
            type: 'bar', // Gráfica de barras vertical
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pickeos Completados',
                    data: data,
                    backgroundColor: chartBackgroundColors,
                    borderColor: chartBorderColors,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y', // *** Hacerla de BARRAS HORIZONTALES para mejor lectura de nombres largos ***
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { // El eje X ahora representa la cantidad
                        min: 0,
                        suggestedMax: suggestedMax,
                        title: { display: true, text: 'Cantidad de Pickeos Completados' }
                    },
                    y: { // El eje Y ahora representa los usuarios
                        title: { display: true, text: 'Usuario' }
                    }
                },
                plugins: {
                    legend: { display: false }, // Ocultar leyenda, la barra es auto-explicativa
                    title: { display: false }, // Título ya está en el card
                    tooltip: {
                         callbacks: { label: function(context) { return ` ${context.label}: ${new Intl.NumberFormat('es-MX').format(context.raw)}`; } }
                    }
                }
            }
        });
    } // Fin renderUserPicksChart


    fetchDataAndInitialize(); // Iniciar carga de datos al estar listo el DOM

    if (typeof feather !== 'undefined') { feather.replace(); } // Inicializar iconos
});
</script>