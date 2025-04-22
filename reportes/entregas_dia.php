<?php
// reportes/entregas_dia.php - Reporte HISTÓRICO de WOs entregadas con GRÁFICAS
// (Considera renombrar a historial_entregas.php si prefieres)
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
$active_subpage = 'entregas_dia'; // O 'historial_entregas' si renombras

// --- Incluir Layout ---
include_once('../layouts/header.php'); // Asegúrate que header.php cargue Chart.js
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
                             <h5 class="card-title mb-0">Entregas por Día (Últimos 60 días)</h5>
                        </div>
                        <div class="card-body pt-2 pb-3">
                            <div class="chart chart-sm" style="position: relative; height:300px;">
                                <canvas id="chartjs-deliveries-daily"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                 <div class="col-lg-5">
                    <div class="card flex-fill w-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Entregas por Usuario (Total Histórico)</h5>
                        </div>
                         <div class="card-body d-flex">
                            <div class="align-self-center w-100" style="position: relative; height:300px;">
                                <canvas id="chartjs-deliveries-user"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php // --- Fin Fila Gráficas --- ?>


            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detalle del Historial de Entregas</h5>
                    <h6 class="card-subtitle text-muted pt-1">Lista de todas las Work Orders marcadas como entregadas a producción.</h6>
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
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include_once('../layouts/footer.php'); ?>
</div>

<?php // --- Scripts JS --- ?>
<script>
    // Fallbacks y verificaciones
    if (typeof jQuery == 'undefined') { console.error("jQuery no cargado."); }
    if (typeof Swal == 'undefined') { console.error("SweetAlert2 no cargado."); }
    if (typeof $.fn.dataTable == 'undefined') { console.error("DataTables no cargado."); }
    // Verificar Chart.js (asumiendo que está en el scope global)
    if (typeof Chart == 'undefined') {
         console.error("Chart.js no está cargado. Asegúrate de incluirlo en header.php o footer.php ANTES de este script.");
         // Podrías deshabilitar la funcionalidad de gráficas aquí
    }
</script>

<script>
$(document).ready(function() {

    var tableEntregasHistorial = null;
    // Variables para almacenar las instancias de las gráficas
    let dailyChartInstance = null;
    let userChartInstance = null;

    // --- Inicializar DataTable ---
    if ($('#tabla-entregas-historial').length && typeof $.fn.dataTable !== 'undefined') {
        tableEntregasHistorial = $('#tabla-entregas-historial').DataTable({
            responsive: true,
            processing: true,
            ajax: {
                url: '../ajax/get_historial_entregas.php', // Endpoint que devuelve TODO el historial
                type: 'GET',
                dataSrc: 'data', // Los datos están bajo la clave 'data'
                // *** IMPORTANTE: Procesar datos para gráficas DESPUÉS de que AJAX tenga éxito ***
                success: function(json) {
                    // console.log("Datos recibidos para tabla y gráficas:", json.data); // Para depuración
                    if (typeof Chart !== 'undefined' && json.data && json.data.length > 0) {
                         // Llamar a la función que procesa datos y renderiza las gráficas
                        renderDeliveryCharts(json.data);
                    } else if (!json.data || json.data.length === 0) {
                         console.log("No hay datos de historial para generar gráficas.");
                         // Opcional: Mostrar mensaje en lugar de las gráficas
                    } else {
                         console.warn("Chart.js no está disponible, no se generarán gráficas.");
                    }
                    // DataTables continuará procesando json.data para la tabla automáticamente
                },
                error: function(jqXHR, textStatus, errorThrown) {
                     console.error("Error al cargar historial de entregas: ", textStatus, errorThrown, jqXHR.responseText);
                     $('#tabla-entregas-historial tbody').html(
                         '<tr><td colspan="5" class="text-center text-danger">Error al cargar el historial ('+ jqXHR.status + ' ' + errorThrown + '). Intente recargar o contacte al administrador.</td></tr>'
                     );
                     $('.dataTables_processing', tableEntregasHistorial.table().container()).hide();
                 }
            },
            columns: [ // Definición de columnas (sin cambios respecto a la versión anterior)
                { data: 'workorder', render: $.fn.dataTable.render.text() },
                { data: 'numero_parte', render: $.fn.dataTable.render.text(), defaultContent: "-" },
                { data: 'descripcion', render: $.fn.dataTable.render.text(), defaultContent: "-" },
                {
                    data: 'fecha_entrega',
                    render: function(data, type, row) {
                        if (!data) return '-';
                        try {
                            let d = new Date(data.includes(' ') ? data.replace(' ', 'T') + 'Z' : data + 'Z');
                            if (isNaN(d)) { return data; }
                            return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                                   d.toLocaleTimeString('es-MX', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        } catch (e) { return data; }
                    }
                },
                {
                    data: 'nombre_usuario_entrega',
                    render: function(data, type, row) {
                        let userId = row.id_usuario_accion;
                        let userName = data;
                        if (userId && (!userName || userName.trim() === '')) { return 'Usuario Borrado (ID: ' + $('<div>').text(userId).html() + ')'; }
                        else if (!userId && (!userName || userName.trim() === '')) { return 'Sistema'; }
                        else { return userName ? $('<div>').text(userName).html() : 'N/A'; }
                    },
                    defaultContent: "N/A"
                }
            ],
            // Resto de la configuración de DataTables (dom, language, lengthMenu, order, drawCallback...)
             dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
             language: {
                 url: '../assets/json/datatables_spanish.json',
                 emptyTable: "No se encontraron entregas registradas en el historial.",
                 processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> Cargando historial...'
             },
             lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos'] ],
             order: [ [3, 'desc'] ], // Ordenar por fecha descendente
             drawCallback: function(settings) {
                 if (typeof feather !== 'undefined') { feather.replace(); }
                 var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                 var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) });
             }
        });
    }

    /**
     * Procesa los datos del historial y renderiza las gráficas de Chart.js
     * @param {Array} historyData Array de objetos, cada uno representando una entrega.
     */
    function renderDeliveryCharts(historyData) {
        if (!historyData || historyData.length === 0) {
            console.log("No hay datos para generar gráficas.");
            return;
        }

        // --- 1. Procesar datos para Gráfica Diaria (Últimos 60 días) ---
        const dailyCounts = {};
        const sixtyDaysAgo = new Date();
        sixtyDaysAgo.setDate(sixtyDaysAgo.getDate() - 60); // Fecha de hace 60 días
        sixtyDaysAgo.setHours(0, 0, 0, 0); // Poner a medianoche para comparación

        historyData.forEach(item => {
            try {
                 // Convertir fecha_entrega a objeto Date
                 // Asegurar interpretación UTC añadiendo 'Z' si no hay offset
                 const deliveryDate = new Date(item.fecha_entrega.includes(' ') ? item.fecha_entrega.replace(' ', 'T') + 'Z' : item.fecha_entrega + 'Z');

                 if (!isNaN(deliveryDate) && deliveryDate >= sixtyDaysAgo) {
                    // Formatear fecha a YYYY-MM-DD para usar como clave
                    const dayKey = deliveryDate.toISOString().split('T')[0];
                    dailyCounts[dayKey] = (dailyCounts[dayKey] || 0) + 1;
                 }
            } catch (e) {
                 console.warn("Error procesando fecha para gráfica diaria:", item.fecha_entrega, e);
            }
        });

        // Ordenar claves (fechas) y preparar datos para Chart.js
        const sortedDays = Object.keys(dailyCounts).sort();
        const dailyLabels = sortedDays.map(day => {
             // Formatear para mostrar D/M
             const [y, m, d] = day.split('-');
             return `${d}/${m}`;
        });
        const dailyData = sortedDays.map(day => dailyCounts[day]);

        // --- 2. Procesar datos para Gráfica por Usuario (Total) ---
        const userCounts = {};
        historyData.forEach(item => {
            // Usar nombre de usuario o 'Sistema'/'Usuario Borrado' como clave
             let userName = item.nombre_usuario_entrega;
             let userId = item.id_usuario_accion;
             let userKey = "Indefinido"; // Default

             if (userId && (!userName || userName.trim() === '')) { userKey = `Usuario Borrado (ID: ${userId})`; }
             else if (!userId && (!userName || userName.trim() === '')) { userKey = 'Sistema'; }
             else if (userName) { userKey = userName; }

             userCounts[userKey] = (userCounts[userKey] || 0) + 1;
        });

        // Preparar datos para Chart.js (ordenar por cantidad descendente opcional)
        const userLabels = Object.keys(userCounts);
        const userData = userLabels.map(user => userCounts[user]);

        // --- 3. Renderizar Gráficas ---
        renderDailyChart(dailyLabels, dailyData);
        renderUserChart(userLabels, userData); // Usar gráfica de Donut
    }

    /**
     * Renderiza la gráfica de entregas diarias
     */
    function renderDailyChart(labels, data) {
        const ctx = document.getElementById('chartjs-deliveries-daily')?.getContext('2d');
        if (!ctx) return;

        // Destruir gráfica anterior si existe para evitar solapamientos al recargar
        if (dailyChartInstance) {
            dailyChartInstance.destroy();
        }

        dailyChartInstance = new Chart(ctx, {
            type: 'bar', // O 'line'
            data: {
                labels: labels,
                datasets: [{
                    label: 'Entregas por Día',
                    data: data,
                    backgroundColor: 'rgba(59, 125, 221, 0.5)', // Azul Adminkit semi-transparente
                    borderColor: 'rgba(59, 125, 221, 1)',
                    borderWidth: 1,
                    // tension: 0.1 // Para gráficas de línea suavizadas
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Permitir que la altura se ajuste al contenedor
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Cantidad Entregas' }
                    },
                    x: {
                         title: { display: true, text: 'Fecha (Últimos 60 días)' }
                    }
                },
                plugins: {
                    legend: { display: false }, // Ocultar leyenda si solo hay un dataset
                    tooltip: { mode: 'index', intersect: false }
                }
            }
        });
    }

    /**
     * Renderiza la gráfica de entregas por usuario (Donut)
     */
    function renderUserChart(labels, data) {
        const ctx = document.getElementById('chartjs-deliveries-user')?.getContext('2d');
        if (!ctx) return;

        if (userChartInstance) {
            userChartInstance.destroy();
        }

         // Paleta de colores simple (puedes ampliarla o usar una librería)
        const backgroundColors = [
            'rgba(59, 125, 221, 0.7)', // Blue
            'rgba(40, 167, 69, 0.7)',  // Green
            'rgba(255, 193, 7, 0.7)',   // Yellow
            'rgba(220, 53, 69, 0.7)',   // Red
            'rgba(108, 117, 125, 0.7)', // Gray
            'rgba(23, 162, 184, 0.7)', // Teal
            'rgba(253, 126, 20, 0.7)'  // Orange
        ];
         const borderColors = backgroundColors.map(color => color.replace('0.7', '1')); // Hacer borde opaco

        userChartInstance = new Chart(ctx, {
            type: 'doughnut', // O 'pie', o 'bar' si prefieres barras
            data: {
                labels: labels,
                datasets: [{
                    label: 'Entregas Totales',
                    data: data,
                    backgroundColor: backgroundColors, // Aplicar colores diferentes
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                         position: 'top', // O 'bottom', 'left', 'right'
                         display: labels.length <= 10 // Mostrar leyenda si no hay demasiados usuarios
                    },
                    title: {
                         display: false, // El título ya está en el Card
                         // text: 'Entregas por Usuario'
                    },
                    tooltip: {
                         callbacks: {
                             // Mostrar porcentaje en tooltip (opcional)
                             // label: function(context) {
                             //     let label = context.label || '';
                             //     let value = context.parsed || 0;
                             //     let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                             //     let percentage = sum > 0 ? ((value / sum) * 100).toFixed(1) + '%' : '0%';
                             //     return `${label}: ${value} (${percentage})`;
                             // }
                         }
                    }
                }
            }
        });
    }


    // Inicializar Feather Icons al final
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>