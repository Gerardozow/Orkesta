<?php
// almacen/gestionar_ordenes.php - Página para ver y gestionar estados de WO (Versión AJAX - Solo Aprobar)
// Fecha de última revisión: 16 de Abril de 2025

// Carga inicial (subiendo un nivel)
require_once('../includes/load.php');

// Asegura que el usuario esté logueado
if (function_exists('requerir_login')) {
    requerir_login();
} else {
    die("Error: Sistema de autenticación no disponible.");
}

// --- Permisos: Solo Admin y Supervisor Almacen ---
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen'])) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para acceder a esta sección.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Variables de Plantilla ---
$page_title = 'Gestionar Work Orders (Aprobación)';
$active_page = 'almacen';
$active_subpage = 'gestionar_ordenes';
// $datos_sesion y $current_user_id se obtienen en el handler AJAX si son necesarios

// --- NO HAY PROCESAMIENTO POST AQUÍ - Se movió a ajax/workorder_actions.php ---
// --- NO HAY CARGA DE DATOS DE TABLA AQUÍ - Se hace vía AJAX ---

// --- Incluir Layout ---
include_once('../layouts/header.php');
?>
<?php // Asumimos CSS DataTables/SweetAlert2 está en header.php globalmente 
?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="card-title mb-0">Estado de Work Orders</h5>
                        <h6 class="card-subtitle text-muted pt-1">Visualiza y aprueba las órdenes pendientes.</h6>
                    </div>
                    <div></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-gestion-wo" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Work Order</th>
                                    <th>Num. Parte</th>
                                    <th>Req. Pickeo</th>
                                    <th>Aprobación</th>
                                    <th>Est. Pickeo</th>
                                    <th>Asignado a</th>
                                    <th>Solic. Prod.</th>
                                    <th>Est. Entrega</th>
                                    <th>Últ. Act. Estado</th>
                                    <th class="text-center dt-nowrap">Acción</th>
                                    <th class="text-center dt-nowrap">Historial</th>
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

    <?php include_once('../layouts/footer.php'); // Footer carga librerías JS globales 
    ?>
</div>

<script>
    $(document).ready(function() {

        var table = null; // Variable para la tabla DataTables



        // --- Inicializar DataTable para Gestión WO (CON AJAX) ---
        if ($('#tabla-gestion-wo').length) {
            table = $('#tabla-gestion-wo').DataTable({
                responsive: true,
                processing: true, // Indicador de carga
                ajax: {
                    url: '../ajax/get_dashboard_data.php?tabla=gestion_ordenes', // URL del script que devuelve JSON
                    type: 'GET',
                    dataSrc: 'data' // Clave donde están los datos en el JSON
                },
                // --- Definir Columnas y Renderizado ---
                columns: [{
                        data: 'workorder',
                        render: $.fn.dataTable.render.text()
                    },
                    {
                        data: 'numero_parte',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "-"
                    },
                    {
                        data: 'requiere_pickeo',
                        render: function(data, type, row) {
                            return (data == 1) ? 'Sí' : 'No';
                        }
                    },
                    {
                        data: 'estado_aprobacion_almacen',
                        render: function(data, type, row) {
                            return (data === 'APROBADA') ? '<span class="badge bg-success">Aprobada</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>';
                        }
                    },
                    {
                        data: 'estado_pickeo',
                        render: function(data, type, row) {
                            // Establecer estado por defecto a 'PENDIENTE' si no hay dato
                            let status = data || 'PENDIENTE';

                            // Valores por defecto para el badge (estado PENDIENTE)
                            let p_badge = 'secondary';
                            let p_text = 'Pendiente';

                            // Asignar clase y texto según el estado específico
                            if (status === 'EN_PROCESO') {
                                p_badge = 'primary';
                                p_text = 'En Proceso';
                            } else if (status === 'PARCIAL') {
                                p_badge = 'warning'; // Badge amarillo/naranja para Parcial
                                p_text = 'Parcial';
                            } else if (status === 'COMPLETO') {
                                p_badge = 'success';
                                p_text = 'Completo';
                            }
                            // Si no coincide con ninguno, usa los valores por defecto

                            // Construir el HTML del badge
                            let badge = `<span class="badge bg-${p_badge}">${p_text}</span>`;

                            // Añadir icono si está en proceso y tiene comentario de progreso
                            if (status === 'EN_PROCESO' && (row.tiene_comentario_progreso == 1)) {
                                badge += ' <i data-feather="message-square" title="Tiene comentario de progreso"></i>';
                            }

                            return badge;
                        }
                    },
                    {
                        data: 'nombre_asignado',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "N/A"
                    },
                    {
                        data: 'solicitada_produccion',
                        render: function(data, type, row) {
                            return (data == 1) ? '<span class="badge bg-info">Solicitada</span>' : '<span class="badge bg-light text-dark">No Solicitada</span>';
                        }
                    },
                    {
                        data: 'estado_entrega',
                        render: function(data, type, row) {
                            return (data === 'ENTREGADA') ? '<span class="badge bg-success">Entregada</span>' : '<span class="badge bg-secondary">Pendiente</span>';
                        }
                    },
                    {
                        data: 'fecha_estado_actualizacion',
                        render: function(data, type, row) {
                            if (!data) return '-';
                            try {
                                let d = new Date(data + 'Z');
                                return d.toLocaleDateString('es-MX') + ' ' + d.toLocaleTimeString('es-MX', {
                                    hour12: false
                                });
                            } catch (e) {
                                return data;
                            }
                        }
                    },
                    {
                        data: 'workorder', // Columna Acción (Aprobar/Restablecer)
                        orderable: false,
                        className: 'text-center dt-nowrap',
                        render: function(data, type, row) {
                            // Escapar el número de WO para usarlo de forma segura en HTML
                            let wo_escaped = $('<div>').text(data).html();
                            // Obtener el estado de aprobación, default a 'PENDIENTE' si no existe
                            let estadoAprobacion = row.estado_aprobacion_almacen || 'PENDIENTE';

                            // --- Lógica Condicional para los Botones ---
                            if (estadoAprobacion === 'PENDIENTE') {
                                // Si está PENDIENTE, mostrar botón para APROBAR
                                return `<form class="d-inline needs-confirmation" action="../ajax/workorder_actions.php" method="POST" data-confirm-message="¿APROBAR la WO ${wo_escaped}?">
                        <input type="hidden" name="form_action" value="aprobar_wo">
                        <input type="hidden" name="workorder" value="${wo_escaped}">
                        <button type="submit" class="btn btn-success btn-sm" title="Aprobar WO">
                            <i class="align-middle" data-feather="check-circle"></i>
                        </button>
                    </form>`;
                            } else if (estadoAprobacion === 'APROBADA') {
                                // Si está APROBADA, mostrar botón para RESTABLECER
                                return `<form class="d-inline needs-confirmation" action="../ajax/workorder_actions.php" method="POST" data-confirm-message="¿RESTABLECER la WO ${wo_escaped}? ¡Esto revertirá la aprobación y el estado de pickeo!">
                        <input type="hidden" name="form_action" value="resetear_wo">
                        <input type="hidden" name="workorder" value="${wo_escaped}">
                        <button type="submit" class="btn btn-warning btn-sm" title="Restablecer WO">
                            <i class="align-middle" data-feather="refresh-cw"></i> 
                        </button>
                    </form>`;
                            } else {
                                // Para cualquier otro estado
                                return '<span class="text-muted small">-</span>'; // Mostrar un guión
                            }
                        }
                    },
                    {
                        data: 'workorder', // Columna Historial
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            let wo_encoded = encodeURIComponent(data);
                            // Ruta correcta desde almacen/
                            return `<a href="historial_wo.php?wo=${wo_encoded}" class="btn btn-outline-secondary btn-sm" title="Ver Historial"><i data-feather="list"></i></a>`;
                        }
                    }
                ], // Fin columns
                // Resto de opciones DataTables
                dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                language: {
                    /* ... Español ... */
                    emptyTable: "No se encontraron Work Orders con estado."
                },
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'Todos']
                ],
                pageLength: 50,
                // Ordenar por fecha estado (índice 8 ahora)
                columnDefs: [{
                    "orderable": false,
                    "targets": [9, 10]
                }], // Accion, Historial (índices 9, 10)
                // Callback para reinicializar iconos Feather
                drawCallback: function(settings) {
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                    // Inicializar Tooltips de Bootstrap por si se usan en botones generados
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
                    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                        // Prevenir reinicialización múltiple
                        var existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                        if (!existingTooltip) {
                            return new bootstrap.Tooltip(tooltipTriggerEl, {
                                trigger: 'hover'
                            })
                        }
                        return existingTooltip;
                    });
                }
            });

            // Inicializar/Mover Botones de Exportación
            new $.fn.dataTable.Buttons(table, {
                buttons: [{
                        extend: 'excelHtml5',
                        text: '<i class="align-middle" data-feather="file"></i> Excel',
                        className: 'btn btn-success btn-sm ms-1',
                        titleAttr: 'Excel'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="align-middle" data-feather="file-text"></i> PDF',
                        className: 'btn btn-danger btn-sm ms-1',
                        titleAttr: 'PDF',
                        orientation: 'landscape'
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="align-middle" data-feather="save"></i> CSV',
                        className: 'btn btn-secondary btn-sm ms-1',
                        titleAttr: 'CSV'
                    }
                ]
            });
            var buttonContainer = table.buttons().container();
            buttonContainer.appendTo($('#tabla-gestion-wo').closest('.card').find('.card-header > div:last-child'));
            buttonContainer.find('.dt-buttons').addClass('btn-group flex-wrap');
            buttonContainer.find('.btn').removeClass('ms-1').addClass('m-1');
        } // Fin if tabla existe

        // --- Manejador para forms .needs-confirmation (AJAX) ---
        // Solo la acción 'aprobar_wo' es relevante aquí ahora
        $('#tabla-gestion-wo').on('submit', 'form.needs-confirmation', function(event) {
            event.preventDefault();
            const formElement = this;
            const formData = new FormData(formElement);
            const message = $(formElement).data('confirm-message') || '¿Estás seguro?';
            const action = formData.get('form_action');

            // Solo permitir comentario opcional para aprobar si se habilita
            const actions_with_optional_comment = []; // ['aprobar_wo']; 

            let swalConfig = {
                title: 'Confirmación',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'No, cancelar',
                confirmButtonColor: '#198754', // Verde para aprobar
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            };

            // Habilitar comentario si se desea para aprobar
            /*
            if (actions_with_optional_comment.includes(action)) {
                swalConfig.input = 'textarea';
                swalConfig.inputLabel = 'Comentario (Opcional)';
                swalConfig.inputPlaceholder = 'Añade un comentario...';
                swalConfig.confirmButtonText = 'Confirmar y Guardar'; 
            }
            */

            Swal.fire(swalConfig).then((result) => {
                if (result.isConfirmed) {
                    // Añadir comentario a FormData si existía input y valor
                    if (result.value !== undefined && result.value !== null && result.value !== '' && actions_with_optional_comment.includes(action)) {
                        formData.append('accion_comentario', result.value);
                    }

                    Swal.fire({
                        title: 'Procesando...',
                        /* ... loader ... */
                    });

                    // --- Enviar AJAX ---
                    const ajaxUrl = '../ajax/workorder_actions.php'; // URL correcta desde almacen/
                    fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            Swal.close();
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    text: data.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 2500,
                                    timerProgressBar: true
                                });
                                // --- Recargar DataTable con AJAX ---
                                if (table) { // Asegurar que la tabla se inicializó
                                    table.ajax.reload(null, false); // Recargar sin perder paginación
                                }
                            } else {
                                Swal.fire('Error', data.message || 'Ocurrió un error.', 'error');
                            }
                        })
                        .catch(error => {
                            /* ... Manejo error fetch ... */
                        });
                }
            });
        }); // Fin event listener submit

        // Re-inicializar Feather Icons (se hace también en drawCallback)
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

    }); // Fin $(document).ready
</script>