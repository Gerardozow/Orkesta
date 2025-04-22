<?php
// reportes/entregas_dia.php - Reporte HISTÓRICO de WOs entregadas (AJAX)
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
// CAMBIO: Título actualizado
$page_title = 'Historial de Entregas a Producción';
$active_page = 'reportes'; // Página principal activa en el menú
// CAMBIO: Actualizar subpage si renombras el archivo o si tienes varios reportes
$active_subpage = 'entregas_dia'; // O 'historial_entregas' si renombras

// --- Incluir Layout ---
include_once('../layouts/header.php');
?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Historial Completo de Entregas</h5>
                    <h6 class="card-subtitle text-muted pt-1">Lista de todas las Work Orders marcadas como entregadas a producción.</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-entregas-hoy" class="table table-striped table-hover" style="width:100%">
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
    if (typeof jQuery == 'undefined') { console.error("jQuery no cargado."); }
    if (typeof Swal == 'undefined') { console.error("SweetAlert2 no cargado."); }
    if (typeof $.fn.dataTable == 'undefined') { console.error("DataTables no cargado."); }
</script>

<script>
$(document).ready(function() {

    var tableEntregasHistorial = null; // Renombrar variable JS para claridad

    if ($('#tabla-entregas-hoy').length) { // La ID HTML se mantiene por ahora
        tableEntregasHistorial = $('#tabla-entregas-hoy').DataTable({
            responsive: true,
            processing: true,
            ajax: {
                // ***** CAMBIO IMPORTANTE: URL del AJAX *****
                url: '../ajax/get_historial_entregas.php', // Apunta al nuevo endpoint
                type: 'GET',
                dataSrc: 'data',
                error: function(jqXHR, textStatus, errorThrown) {
                     console.error("Error al cargar historial de entregas: ", textStatus, errorThrown, jqXHR.responseText);
                     $('#tabla-entregas-hoy tbody').html(
                         '<tr><td colspan="5" class="text-center text-danger">Error al cargar el historial ('+ jqXHR.status + ' ' + errorThrown + '). Intente recargar o contacte al administrador.</td></tr>'
                     );
                     $('.dataTables_processing', tableEntregasHistorial.table().container()).hide();
                 }
            },
            columns: [ // Las columnas siguen siendo las mismas
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
                        if (userId && (!userName || userName.trim() === '')) {
                             return 'Usuario Borrado (ID: ' + $('<div>').text(userId).html() + ')';
                         } else if (!userId && (!userName || userName.trim() === '')) {
                             return 'Sistema';
                         } else {
                             return userName ? $('<div>').text(userName).html() : 'N/A';
                         }
                    },
                    defaultContent: "N/A"
                }
            ], // Fin columns
            dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            language: {
                url: '../assets/json/datatables_spanish.json',
                // CAMBIO: Mensaje si no hay historial
                emptyTable: "No se encontraron entregas registradas en el historial.",
                processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> Cargando historial...'
            },
            lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos'] ],
            order: [ [3, 'desc'] ], // Mantener orden por fecha descendente
            drawCallback: function(settings) {
                if (typeof feather !== 'undefined') { feather.replace(); }
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) });
            }
        });
    }

    if (typeof feather !== 'undefined') { feather.replace(); }
});
</script>