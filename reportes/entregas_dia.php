<?php
// reportes/entregas_dia.php - Reporte HISTÓRICO con Gráficas, Tabla con Exportación e Idioma Inline
// Fecha de última revisión: 22 de Abril de 2025

require_once('../includes/load.php');
requerir_login();

// --- Permisos ---
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Supervisor Produccion'])) {
    if (function_exists('mensaje_sesion')) {
         mensaje_sesion("No tienes permiso para acceder a esta sección.", "danger");
    }
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Variables de Plantilla ---
$page_title = 'Historial de Entregas a Producción';
$active_page = 'reportes';
$active_subpage = 'entregas_dia';

// --- Incluir Layout ---
// Asegúrate que header.php cargue Chart.js, DataTables CSS y DataTables Buttons CSS
include_once('../layouts/header.php');
?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <?php // --- Fila para Gráficas --- ?>
            <div class="row mb-4">
                <div class="col-lg-7">
                    <div class="card flex-fill w-100">
                        <div class="card-header">
                             <h5 class="card-title mb-0">Entregas por Día (Últimos 30 días)</h5>
                        </div>
                        <div class="card-body pt-2 pb-3">
                            <div class="chart chart-sm" style="position: relative; height:350px;">
                                <canvas id="chartjs-deliveries-daily"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                 <div class="col-lg-5">
                    <div class="card flex-fill w-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Entregas por Usuario (Últimos 30 días)</h5>
                        </div>
                         <div class="card-body d-flex">
                            <div class="align-self-center w-100" style="position: relative; height:350px;">
                                <canvas id="chartjs-deliveries-user"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php // --- Fin Fila Gráficas --- ?>

            <?php // --- Tabla de Historial --- ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                     <div>
                        <h5 class="card-title mb-0">Detalle del Historial Completo de Entregas</h5>
                        <h6 class="card-subtitle text-muted pt-1">Lista de todas las Work Orders marcadas como entregadas a producción.</h6>
                    </div>
                    <div class="dt-buttons-container">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-entregas-historial" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Work Order</th>
                                    <th>Num. Parte</th>
                                    <th>Descripción</th>
                                    <th>Fecha/Hora Entrega</th>
                                    <th>Entregado Por</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                         <div id="loading-indicator" class="text-center p-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando historial...</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php // --- Fin Tabla Historial --- ?>

        </div>
    </main>

    <?php
    // Asegúrate que footer.php cargue:
    // 1. jQuery
    // 2. DataTables JS Core
    // 3. DataTables Buttons JS (dataTables.buttons.min.js)
    // 4. Botones HTML5 (buttons.html5.min.js)
    // 5. JSZip (jszip.min.js) - para Excel
    // 6. pdfmake (pdfmake.min.js, vfs_fonts.js) - para PDF
    // 7. Chart.js
    include_once('../layouts/footer.php');
    ?>
</div>

<?php // --- Scripts JS --- ?>
<script>
    // Fallbacks y verificaciones
    if (typeof jQuery == 'undefined') { console.error("jQuery no cargado."); }
    if (typeof $.fn.dataTable == 'undefined') { console.error("DataTables no cargado."); }
    // Verificar extensiones de botones (opcional)
    if (typeof $.fn.dataTable.Buttons == 'undefined') { console.error("DataTables Buttons no cargado."); }
    if (typeof Chart == 'undefined') { console.error("Chart.js no está cargado."); }

</script>

<script>
$(document).ready(function() {

    var tableEntregasHistorial = null;
    let dailyChartInstance = null;
    let userChartInstance = null;
    let isDataTableInitialized = false;

    function showLoading() { $('#loading-indicator').show(); }
    function hideLoading() { $('#loading-indicator').hide(); }

    function fetchDataAndInitialize() {
        const ajaxUrl = '../ajax/get_historial_entregas.php';
        console.log("Fetching data from:", ajaxUrl);
        showLoading();

        fetch(ajaxUrl)
            .then(response => { if (!response.ok) { throw new Error(`Network error (${response.status})`); } return response.json(); })
            .then(json => {
                 console.log("Data received:", json);
                 hideLoading();
                 let historyData = [];
                 if (json && json.data) { historyData = json.data; }
                 else { /* ... (manejo error datos inválidos) ... */ return; }

                 // Renderizar Gráficas
                 if (typeof Chart !== 'undefined') {
                     try { renderDeliveryCharts(historyData); } catch (e) { console.error("Error rendering charts:", e); displayChartError('Error al generar gráficas.'); }
                 } else { displayChartError('Librería de gráficas no disponible.'); }

                 // Inicializar DataTable (o mostrar error)
                 if ($('#tabla-entregas-historial').length && typeof $.fn.dataTable !== 'undefined' && !isDataTableInitialized) {
                    try { initializeHistoryTable(historyData); isDataTableInitialized = true; }
                    catch (e) { console.error("Error initializing DataTable:", e); $('#tabla-entregas-historial tbody').html('<tr><td colspan="5" class="text-center text-danger">Error al inicializar la tabla.</td></tr>'); }
                 } else { /* ... */ }
            })
            .catch(error => { /* ... (manejo error fetch) ... */ });
    }

    function displayChartError(message) { /* ... (sin cambios) ... */
         $('#chartjs-deliveries-daily').parent().empty().html(`<div class="text-center text-muted p-3">${message}</div>`);
         $('#chartjs-deliveries-user').parent().empty().html(`<div class="text-center text-muted p-3">${message}</div>`);
    }

    /**
     * Inicializa la tabla DataTables con los datos y configuración deseada.
     * @param {Array} tableData El array de datos para la tabla.
     */
    function initializeHistoryTable(tableData) {
        console.log(`Initializing DataTable with ${tableData.length} rows.`);
        if ($.fn.DataTable.isDataTable('#tabla-entregas-historial')) {
             $('#tabla-entregas-historial').DataTable().destroy();
             $('#tabla-entregas-historial tbody').empty();
             isDataTableInitialized = false;
        }

        tableEntregasHistorial = $('#tabla-entregas-historial').DataTable({
            // --- Opciones de Configuración ---
            responsive: true,
            processing: true,
            pageLength: 50,
            data: tableData,
            columns: [
                { data: 'workorder', render: $.fn.dataTable.render.text() },
                { data: 'numero_parte', render: $.fn.dataTable.render.text(), defaultContent: "-" },
                { data: 'descripcion', render: $.fn.dataTable.render.text(), defaultContent: "-" },
                { data: 'fecha_entrega', render: function(data, type, row) { if (!data) return '-'; try { let d = new Date(data.includes(' ') ? data.replace(' ', 'T') + 'Z' : data + 'Z'); if (isNaN(d)) { return data; } return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('es-MX', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }); } catch (e) { return data; } } },
                { data: 'nombre_usuario_entrega', render: function(data, type, row) { let userId = row.id_usuario_accion; let userName = data; if (userId && (!userName || userName.trim() === '')) { return 'Usuario Borrado (ID: ' + $('<div>').text(userId).html() + ')'; } else if (!userId && (!userName || userName.trim() === '')) { return 'Sistema'; } else { return userName ? $('<div>').text(userName).html() : 'N/A'; } }, defaultContent: "N/A" }
            ],
            // --- Configuración de Apariencia y Lenguaje (Inline) ---
             dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + // Length y Filter
                  "<'row'<'col-sm-12'tr>>" + // Table
                  "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>", // Info y Pagination
             language: { // *** LENGUAJE INLINE ***
                 lengthMenu: "Mostrar _MENU_ registros",
                 zeroRecords: "No se encontraron resultados",
                 info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                 infoEmpty: "Mostrando 0 a 0 de 0 registros",
                 infoFiltered: "(filtrado de _MAX_ registros totales)",
                 search: "_INPUT_", // Oculta la etiqueta "Buscar:"
                 searchPlaceholder: "Buscar en historial...", // Placeholder para el input
                 paginate: {
                     first: "<<",
                     last: ">>",
                     next: ">",
                     previous: "<"
                 },
                 emptyTable: "No hay entregas registradas en el historial.",
                 processing: '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"><span class="visually-hidden">Procesando...</span></div> Procesando...' // Indicador de carga
             },
             lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos'] ],
             order: [ [3, 'desc'] ], // Ordenar por fecha descendente
             // No se necesitan columnDefs porque no hay columnas sin ordenar por defecto
             drawCallback: function(settings) {
                 if (typeof feather !== 'undefined') { feather.replace(); }
                 var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                 var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) });
             }
        });

        // --- INICIALIZAR Y POSICIONAR BOTONES DE EXPORTACIÓN ---
        if (typeof $.fn.dataTable.Buttons !== 'undefined') {
             new $.fn.dataTable.Buttons(tableEntregasHistorial, {
                 buttons: [{
                         extend: 'excelHtml5',
                         text: '<i class="align-middle" data-feather="file"></i> Excel',
                         className: 'btn btn-success btn-sm', // Clases Bootstrap
                         titleAttr: 'Exportar a Excel'
                     },
                     {
                         extend: 'pdfHtml5',
                         text: '<i class="align-middle" data-feather="file-text"></i> PDF',
                         className: 'btn btn-danger btn-sm',
                         titleAttr: 'Exportar a PDF',
                         orientation: 'landscape' // Orientación horizontal para PDF
                     },
                     {
                         extend: 'csvHtml5',
                         text: '<i class="align-middle" data-feather="save"></i> CSV',
                         className: 'btn btn-secondary btn-sm',
                         titleAttr: 'Exportar a CSV'
                     }
                 ]
             });

             // Mover los botones al contenedor preparado en el card-header
             var buttonContainer = tableEntregasHistorial.buttons().container();
             buttonContainer.appendTo($('#tabla-entregas-historial').closest('.card').find('.dt-buttons-container'));
             // Añadir clases Bootstrap al contenedor de botones
             buttonContainer.find('.dt-buttons').addClass('btn-group flex-wrap');
              // Ajustar márgenes de los botones
             buttonContainer.find('.btn').removeClass('ms-1').addClass('m-1');
             // Actualizar iconos Feather en los botones recién añadidos
             if (typeof feather !== 'undefined') { feather.replace(); }

        } else {
             console.warn("DataTables Buttons extension no está cargada. No se añadirán botones de exportación.");
        }

    } // Fin initializeHistoryTable

    function renderDeliveryCharts(historyData) { /* ... (sin cambios) ... */
        if (!historyData || historyData.length === 0) { return; } console.log("Processing chart data for the last 30 days..."); const thirtyDaysAgo = new Date(); thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30); thirtyDaysAgo.setHours(0, 0, 0, 0); const today = new Date(); today.setHours(23, 59, 59, 999); const filteredDataLast30Days = historyData.filter(item => { try { const deliveryDate = new Date(item.fecha_entrega.includes(' ') ? item.fecha_entrega.replace(' ', 'T') + 'Z' : item.fecha_entrega + 'Z'); return !isNaN(deliveryDate) && deliveryDate >= thirtyDaysAgo && deliveryDate <= today; } catch (e) { return false; } }); console.log(`Filtered data to ${filteredDataLast30Days.length} records for the last 30 days.`); if (filteredDataLast30Days.length === 0) { console.info("No delivery data found in the last 30 days for charts."); displayChartError('No hay datos de entregas en los últimos 30 días para graficar.'); if (dailyChartInstance) dailyChartInstance.destroy(); if (userChartInstance) userChartInstance.destroy(); return; } const dailyCounts = {}; filteredDataLast30Days.forEach(item => { const deliveryDate = new Date(item.fecha_entrega.includes(' ') ? item.fecha_entrega.replace(' ', 'T') + 'Z' : item.fecha_entrega + 'Z'); const dayKey = deliveryDate.toISOString().split('T')[0]; dailyCounts[dayKey] = (dailyCounts[dayKey] || 0) + 1; }); const finalDailyLabels = []; const finalDailyData = []; let currentDate = new Date(thirtyDaysAgo); while (currentDate <= today) { let day = String(currentDate.getDate()).padStart(2, '0'); let month = String(currentDate.getMonth() + 1).padStart(2, '0'); let year = currentDate.getFullYear(); let label = `${day}/${month}`; let key = `${year}-${month}-${day}`; finalDailyLabels.push(label); finalDailyData.push(dailyCounts[key] || 0); currentDate.setDate(currentDate.getDate() + 1); } const userCountsLast30Days = {}; filteredDataLast30Days.forEach(item => { let userName = item.nombre_usuario_entrega; let userId = item.id_usuario_accion; let userKey = "Indefinido"; if (userId && (!userName || userName.trim() === '')) { userKey = `Usuario Borrado (ID: ${userId})`; } else if (!userId && (!userName || userName.trim() === '')) { userKey = 'Sistema'; } else if (userName) { userKey = userName; } userCountsLast30Days[userKey] = (userCountsLast30Days[userKey] || 0) + 1; }); const userLabels = Object.keys(userCountsLast30Days); const userData = userLabels.map(user => userCountsLast30Days[user]); console.log("Rendering charts with 30-day data..."); renderDailyChart(finalDailyLabels, finalDailyData); renderUserChart(userLabels, userData); console.log("Charts rendering calls finished.");
    }

    function renderDailyChart(labels, data) { /* ... (sin cambios) ... */
        const ctx = document.getElementById('chartjs-deliveries-daily')?.getContext('2d'); if (!ctx) { console.error("Canvas para gráfica diaria no encontrado"); return; } if (dailyChartInstance) { dailyChartInstance.destroy(); } const numericData = data.filter(d => typeof d === 'number'); const maxValue = numericData.length > 0 ? Math.max(...numericData) : 10; const suggestedMax = Math.ceil(maxValue * 1.1) + 2; dailyChartInstance = new Chart(ctx, { type: 'bar', data: { labels: labels, datasets: [{ label: 'Entregas por Día', data: data, backgroundColor: 'rgba(59, 125, 221, 0.5)', borderColor: 'rgba(59, 125, 221, 1)', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, suggestedMax: suggestedMax, title: { display: true, text: 'Cantidad Entregas' } }, x: { title: { display: true, text: 'Fecha (Últimos 30 días)' } } }, plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } } } });
    }

    function renderUserChart(labels, data) { /* ... (sin cambios) ... */
        const ctx = document.getElementById('chartjs-deliveries-user')?.getContext('2d'); if (!ctx) { console.error("Canvas para gráfica de usuario no encontrado"); return; } if (userChartInstance) { userChartInstance.destroy(); } if (labels.length === 0 || data.length === 0) { $(ctx.canvas).parent().empty().html('<div class="text-center text-muted p-3">No hay entregas de usuarios en los últimos 30 días.</div>'); return; } const backgroundColors = ['rgba(59, 125, 221, 0.7)', 'rgba(40, 167, 69, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(108, 117, 125, 0.7)', 'rgba(23, 162, 184, 0.7)', 'rgba(253, 126, 20, 0.7)', 'rgba(102, 16, 242, 0.7)', 'rgba(232, 62, 140, 0.7)']; const chartBackgroundColors = labels.map((_, i) => backgroundColors[i % backgroundColors.length]); const chartBorderColors = chartBackgroundColors.map(color => color.replace('0.7', '1')); userChartInstance = new Chart(ctx, { type: 'doughnut', data: { labels: labels, datasets: [{ label: 'Entregas (Últ. 30 días)', data: data, backgroundColor: chartBackgroundColors, borderColor: chartBorderColors, borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', display: labels.length <= 10 }, title: { display: false } } } });
    }

    fetchDataAndInitialize(); // Iniciar carga de datos

    if (typeof feather !== 'undefined') { feather.replace(); } // Inicializar iconos
});
</script>