<?php
// reportes/entregas_dia.php - Reporte de WOs entregadas hoy (AJAX con endpoint dedicado)
// Fecha de última revisión: 22 de Abril de 2025

require_once('../includes/load.php');
requerir_login();

// --- Permisos: Roles que pueden ver reportes (Ajustar según necesidad) ---
// Ejemplo: Admins, Supervisores de Almacén y Producción
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Supervisor Produccion'])) {
    mensaje_sesion("No tienes permiso para acceder a esta sección.", "danger");
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Variables de Plantilla ---
$page_title = 'Reporte de Entregas del Día';
$active_page = 'reportes'; // Página principal activa en el menú
$active_subpage = 'entregas_dia'; // Subpágina activa

// --- No hay carga de datos PHP aquí - Se hace vía AJAX ---

// --- Incluir Layout ---
include_once('../layouts/header.php');
?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <?php /* Mostrar mensajes de sesión si existen (opcional) */ ?>
            <?php echo mostrar_mensaje(leer_mensaje_sesion()); ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Entregas Realizadas Hoy</h5>
                    <h6 class="card-subtitle text-muted pt-1">Lista de Work Orders marcadas como entregadas a producción durante el día actual (<?php echo date('d/m/Y'); ?>).</h6>
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

    <?php include_once('../layouts/footer.php'); // Footer carga JS globales ?>
</div>

<?php // --- Scripts JS Específicos para esta página --- ?>
<script>
    // Fallbacks opcionales (si no están asegurados globalmente)
    if (typeof jQuery == 'undefined') { console.error("jQuery no cargado."); }
    if (typeof Swal == 'undefined') { console.error("SweetAlert2 no cargado."); }
    if (typeof $.fn.dataTable == 'undefined') { console.error("DataTables no cargado."); }
</script>

<script>
$(document).ready(function() {

    var tableEntregasHoy = null;

    // --- Inicializar DataTable para ENTREGAS DE HOY (CON AJAX DEDICADO) ---
    if ($('#tabla-entregas-hoy').length) {
        tableEntregasHoy = $('#tabla-entregas-hoy').DataTable({
            responsive: true,
            processing: true, // Muestra indicador de carga
            ajax: {
                // URL del endpoint AJAX dedicado para este reporte
                url: '../ajax/get_entregas_hoy.php',
                type: 'GET', // Método HTTP para la petición
                dataSrc: 'data', // Clave en el JSON de respuesta que contiene los datos
                error: function(jqXHR, textStatus, errorThrown) {
                     // Manejo de errores mejorado para feedback visual
                     console.error("Error al cargar datos de entregas: ", textStatus, errorThrown, jqXHR.responseText);
                     // Muestra un mensaje de error dentro de la tabla
                     $('#tabla-entregas-hoy tbody').html(
                         '<tr><td colspan="5" class="text-center text-danger">Error al cargar los datos ('+ jqXHR.status + ' ' + errorThrown + '). Intente recargar la página o contacte al administrador.</td></tr>'
                     );
                     // Desactivar el mensaje "Processing"
                     $('.dataTables_processing', tableEntregasHoy.table().container()).hide();
                 }
            },
            columns: [ // Definición de las columnas de la tabla
                {
                    data: 'workorder',
                    render: $.fn.dataTable.render.text() // Sanitiza HTML para prevenir XSS
                },
                {
                    data: 'numero_parte',
                    render: $.fn.dataTable.render.text(),
                    defaultContent: "-" // Valor si el dato es null o undefined
                },
                {
                    data: 'descripcion',
                    render: $.fn.dataTable.render.text(),
                    defaultContent: "-"
                },
                {
                    data: 'fecha_entrega', // Dato esperado desde el AJAX
                    render: function(data, type, row) {
                        // Función para formatear la fecha/hora correctamente
                        if (!data) return '-';
                        try {
                            // Interpreta la fecha como UTC si no tiene zona horaria explícita
                            let d = new Date(data.includes(' ') ? data.replace(' ', 'T') + 'Z' : data + 'Z');
                            if (isNaN(d)) { return data; } // Si no es válida, devuelve original
                            // Formato local 'es-MX' (dd/mm/yyyy HH:MM:SS)
                            return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                                   d.toLocaleTimeString('es-MX', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        } catch (e) {
                            console.warn("Error formateando fecha: ", data, e);
                            return data; // Devuelve original en caso de error
                        }
                    }
                },
                {
                    data: 'nombre_usuario_entrega', // Dato esperado desde el AJAX
                    render: function(data, type, row) {
                        // Función para mostrar nombre de usuario o estado alternativo
                        let userId = row.id_usuario_accion; // Asume que id_usuario_accion también viene en los datos
                        let userName = data;

                        if (userId && (!userName || userName.trim() === '')) {
                            // Si hay ID pero no nombre (ej. usuario borrado)
                             return 'Usuario Borrado (ID: ' + $('<div>').text(userId).html() + ')'; // Sanitizar ID también por si acaso
                         } else if (!userId && (!userName || userName.trim() === '')) {
                             // Si no hay ID ni nombre (acción del sistema)
                             return 'Sistema';
                         } else {
                             // Si hay nombre, sanitizarlo
                             return userName ? $('<div>').text(userName).html() : 'N/A';
                         }
                    },
                    defaultContent: "N/A" // Valor por defecto si la columna no viene
                }
            ], // Fin columns
            dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + // Controles: Mostrar N entradas y Filtro
                 "<'row'<'col-sm-12'tr>>" + // La tabla en sí
                 "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>", // Información (Mostrando X de Y) y Paginación
            language: {
                // Carga externa del idioma español para DataTables
                // Asegúrate que la ruta sea correcta o incluye el objeto directamente
                url: '../assets/json/datatables_spanish.json',
                // Mensaje específico si la tabla está vacía después de la carga AJAX
                emptyTable: "No se encontraron entregas registradas el día de hoy.",
                 // Mensaje mientras carga
                processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> Cargando datos...'
            },
            lengthMenu: [ // Opciones para seleccionar cuántos registros mostrar
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'Todos']
            ],
            order: [
                [3, 'desc'] // Ordenar por la 4ª columna (Fecha/Hora Entrega) descendente
            ],
            // No se necesita columnDefs para acciones aquí
            drawCallback: function(settings) {
                // Volver a inicializar Feather Icons si se usaran dentro de la tabla (aquí no)
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                // Asegurar que el tooltip de Bootstrap funcione si se añade a futuro
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl)
                })
            }
        });
    }

    // No hay listeners para formularios '.needs-confirmation' en esta página

    // Re-inicializar Feather Icons fuera de la tabla al cargar la página
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>