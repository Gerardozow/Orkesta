<?php
// almacen/mis_pickeos.php - Página para Usuario Almacen (Versión AJAX - 4 Estados Pickeo)
// Fecha de última revisión: 16 de Abril de 2025

require_once('../includes/load.php');
requerir_login();

// --- Permisos: Roles de Almacén ---
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen'])) {
    mensaje_sesion("No tienes permiso para acceder a esta sección.", "danger");
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Variables de Plantilla ---
$page_title = 'Gestión de Pickeos';
$active_page = 'almacen';
$active_subpage = 'mis_pickeos';
// $datos_sesion y $current_user_id se obtienen en el handler AJAX si son necesarios

// --- NO HAY CARGA DE DATOS DE TABLA AQUÍ - Se hace vía AJAX ---
// --- NO HAY PROCESAMIENTO POST AQUÍ - Se movió a ajax/workorder_actions.php ---

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
                <div class="card-header">
                    <h5 class="card-title mb-0">Mis Pickeos Asignados</h5>
                    <h6 class="card-subtitle text-muted pt-1">Work Orders asignadas a ti en estado 'En Proceso' o 'Parcial'.</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-mis-pickeos" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Work Order</th>
                                    <th>Num. Parte</th>
                                    <th>Descripción</th>
                                    <th>Estado Pickeo</th>
                                    <th>Solicitada Prod.</th>
                                    <th>Últ. Actualización</th>
                                    <th class="text-center dt-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pickeos Disponibles para Asignar</h5>
                    <h6 class="card-subtitle text-muted pt-1">Work Orders aprobadas y pendientes de pickeo.</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-wos-disponibles" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Work Order</th>
                                    <th>Num. Parte</th>
                                    <th>Descripción</th>
                                    <th>Últ. Actualización Estado</th>
                                    <th class="text-center dt-nowrap">Acción</th>
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

    <?php include_once('../layouts/footer.php'); // Footer carga JS globales 
    ?>
</div> <?php // --- Scripts JS --- 
        ?>
<script>
    // Fallbacks opcionales
    if (typeof jQuery == 'undefined') {
        /* ... Carga jQuery ... */
    }
    if (typeof Swal == 'undefined') {
        console.error("SweetAlert2 no cargado.");
    }
    if (typeof $.fn.dataTable == 'undefined') {
        console.error("DataTables no cargado.");
    }
</script>

<script>
    $(document).ready(function() {

        var tableMisPickeos = null;
        var tableDisponibles = null;

        // --- Inicializar DataTable para MIS PICKEOS (CON AJAX y 4 estados) ---
        if ($('#tabla-mis-pickeos').length) {
            tableMisPickeos = $('#tabla-mis-pickeos').DataTable({
                responsive: true,
                processing: true,
                ajax: {
                    url: '../ajax/get_dashboard_data.php?tabla=mis_pickeos', // Endpoint AJAX
                    type: 'GET',
                    dataSrc: 'data'
                },
                columns: [ // Definir columnas y renderizado
                    {
                        data: 'workorder',
                        render: $.fn.dataTable.render.text()
                    },
                    {
                        data: 'numero_parte',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "-"
                    },
                    {
                        data: 'descripcion',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "-"
                    },
                    {
                        data: 'estado_pickeo', // Columna Estado Pickeo con badges
                        render: function(data, type, row) {
                            let status = data || 'PENDIENTE';
                            let p_badge = 'secondary';
                            let p_text = status;
                            if (status === 'EN_PROCESO') {
                                p_badge = 'primary';
                                p_text = 'En Proceso';
                            } else if (status === 'PARCIAL') {
                                p_badge = 'warning text-dark';
                                p_text = 'Parcial';
                            } // Mostrar estado PARCIAL
                            // Completo no debería aparecer aquí
                            let badge = `<span class="badge bg-${p_badge}">${p_text}</span>`;
                            // Mostrar icono si tiene comentarios y está en proceso o parcial
                            if (['EN_PROCESO', 'PARCIAL'].includes(status) && (row.tiene_comentario_progreso == 1)) {
                                badge += ' <i class="align-middle ms-1" data-feather="message-square" style="width:14px; height:14px; color:#0d6efd;" title="Tiene comentarios"></i>';
                            }
                            return badge;
                        }
                    },
                    {
                        data: 'solicitada_produccion', // Columna Solicitada
                        render: function(data, type, row) {
                            return (data == 1) ? '<span class="badge bg-info">Sí</span>' : '<span class="badge bg-light text-dark">No</span>';
                        }
                    },
                    {
                        data: 'fecha_estado_actualizacion', // Fecha formateada
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
                        data: 'workorder', // Columna Acciones
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            let wo_escaped = $('<div>').text(data).html();
                            let buttons = '<div class="btn-group btn-group" role="group">';
                            let pickStatus = row.estado_pickeo || 'PENDIENTE';

                            if (pickStatus === 'EN_PROCESO') {
                                buttons += `<form action="mis_pickeos.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Pausar pickeo para WO ${wo_escaped}? Se requiere comentario.">
                        <input type="hidden" name="form_action" value="pausar_pickeo">
                        <input type="hidden" name="workorder" value="${wo_escaped}">
                        <button type="submit" class="btn btn-warning btn-lg me-1" title="Pausar Pickeo (Marcar Parcial)">
                            <i class="align-middle" data-feather="pause-circle"></i>
                            <span class="d-inline d-lg-none ms-1">Pausar</span>
                        </button>
                    </form>`;
                            }

                            if (pickStatus === 'PARCIAL') {
                                buttons += `<form action="mis_pickeos.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Reanudar pickeo para WO ${wo_escaped}?">
                        <input type="hidden" name="form_action" value="reanudar_pickeo">
                        <input type="hidden" name="workorder" value="${wo_escaped}">
                        <button type="submit" class="btn btn-primary btn-lg me-1" title="Reanudar Pickeo">
                            <i class="align-middle" data-feather="play-circle"></i>
                            <span class="d-inline d-lg-none ms-1">Reanudar</span>
                        </button>
                    </form>`;
                            }

                            if (['EN_PROCESO', 'PARCIAL'].includes(pickStatus)) {
                                buttons += `<form action="mis_pickeos.php" method="POST" class="d-inline needs-confirmation">
                        <input type="hidden" name="form_action" value="registrar_progreso_pickeo">
                        <input type="hidden" name="workorder" value="${wo_escaped}">
                        <button type="submit" class="btn btn-secondary btn-lg me-1" title="Añadir Nota de Progreso">
                            <i class="align-middle" data-feather="file-plus"></i>
                            <span class="d-inline d-lg-none ms-1">Nota</span>
                        </button>
                    </form>`;
                            }

                            if (['EN_PROCESO', 'PARCIAL'].includes(pickStatus)) {
                                buttons += `<form action="mis_pickeos.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Marcar como COMPLETO el pickeo para WO ${wo_escaped}?">
                        <input type="hidden" name="form_action" value="completar_pickeo">
                        <input type="hidden" name="workorder" value="${wo_escaped}">
                        <button type="submit" class="btn btn-success btn-lg me-1" title="Completar Pickeo">
                            <i data-feather="check-square"></i>
                            <span class="d-inline d-lg-none ms-1">Completar</span>
                        </button>
                    </form>`;
                            }

                            buttons += '</div>';
                            return buttons;
                        }


                    } // fin columna acciones
                ], // Fin columns
                dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                language: {
                    /* ... Español ... */
                    emptyTable: "No tienes Work Orders asignadas en proceso o parciales."
                },
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'Todos']
                ],
                order: [
                    [5, 'asc']
                ], // Ordenar por fecha asc
                columnDefs: [{
                    "orderable": false,
                    "targets": [6]
                }], // Acciones (índice 6)
                // Callback para reinicializar iconos después de cada dibujo
                drawCallback: function(settings) {
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                }
            });
        }

        // --- Inicializar DataTable para WOs DISPONIBLES (CON AJAX) ---
        if ($('#tabla-wos-disponibles').length) {
            tableDisponibles = $('#tabla-wos-disponibles').DataTable({
                responsive: true,
                processing: true,
                ajax: {
                    url: '../ajax/get_dashboard_data.php?tabla=wos_disponibles',
                    type: 'GET',
                    dataSrc: 'data'
                },
                columns: [ // Definir columnas
                    {
                        data: 'workorder',
                        render: $.fn.dataTable.render.text()
                    },
                    {
                        data: 'numero_parte',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "-"
                    },
                    {
                        data: 'descripcion',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "-"
                    },
                    {
                        data: 'fecha_estado_actualizacion',
                        render: function(data, type, row) {
                            /* ... render fecha ... */
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
                        data: 'workorder', // Columna Acción (Asignarme)
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            let wo_escaped = $('<div>').text(data).html();
                            return `<form action="mis_pickeos.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Asignarte la WO ${wo_escaped}?">
                                        <input type="hidden" name="form_action" value="auto_asignar_wo">
                                        <input type="hidden" name="workorder" value="${wo_escaped}">
                                        <button type="submit" class="btn btn-primary btn-sm" title="Asignarme esta WO"><i class="align-middle" data-feather="user-check"></i> Asignarme</button>
                                    </form>`;
                        }
                    }
                ], // Fin columns
                dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                language: {
                    /* ... Español ... */
                    emptyTable: "No hay Work Orders disponibles para asignar."
                },
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'Todos']
                ],
                order: [
                    [3, 'asc']
                ], // Ordenar por fecha asc
                columnDefs: [{
                    "orderable": false,
                    "targets": [4]
                }], // Acción
                drawCallback: function(settings) {
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                }
            });
        }

        // --- Lógica SweetAlert2/AJAX para forms .needs-confirmation (común a ambas tablas) ---
        $('.card').on('submit', 'form.needs-confirmation', function(event) {
            event.preventDefault();
            const formElement = this;
            const formData = new FormData(formElement);
            const message = $(formElement).data('confirm-message') || '¿Estás seguro?';
            const action = formData.get('form_action');

            // Definir acciones con comentario opcional/requerido en ESTA página
            const actions_with_optional_comment = ['completar_pickeo', 'reanudar_pickeo'];
            const actions_with_required_comment = ['registrar_progreso_pickeo', 'pausar_pickeo'];
            const simple_confirm_actions = ['auto_asignar_wo'];

            let swalConfig = {
                /* ... Configuración base Swal ... */
                title: 'Confirmación',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'No, cancelar',
                confirmButtonColor: '#3B7DDD',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            };

            // Ajustar config según acción
            if (action === 'auto_asignar_wo') {
                swalConfig.icon = 'question';
                swalConfig.confirmButtonText = 'Sí, asignarme';
            } else if (action === 'pausar_pickeo') {
                swalConfig.confirmButtonText = 'Sí, Pausar';
            } else if (action === 'reanudar_pickeo') {
                swalConfig.icon = 'info';
                swalConfig.confirmButtonText = 'Sí, Reanudar';
            } else if (action === 'completar_pickeo') {
                swalConfig.icon = 'success';
                swalConfig.confirmButtonText = 'Sí, Completar';
            } else if (action === 'registrar_progreso_pickeo') {
                swalConfig.icon = 'info';
                swalConfig.confirmButtonText = 'Guardar Nota';
                swalConfig.title = 'Añadir Nota de Progreso';
            }

            // Añadir input si aplica
            if (actions_with_optional_comment.includes(action) || actions_with_required_comment.includes(action)) {
                swalConfig.input = 'textarea';
                swalConfig.inputLabel = 'Comentario ' + (actions_with_required_comment.includes(action) ? '(Obligatorio)' : '(Opcional)');
                swalConfig.inputPlaceholder = 'Escribe un comentario aquí...';
                swalConfig.confirmButtonText = 'Confirmar y Guardar';
                if (actions_with_required_comment.includes(action)) {
                    swalConfig.inputValidator = (value) => {
                        if (!value || value.trim() === '') return '¡Necesitas añadir un comentario!';
                    }
                }
            }

            Swal.fire(swalConfig).then((result) => {
                if (result.isConfirmed) {
                    // Añadir comentario a FormData
                    if (result.value !== undefined && result.value !== null && (actions_with_optional_comment.includes(action) || actions_with_required_comment.includes(action))) {
                        formData.append('accion_comentario', result.value);
                    }

                    Swal.fire({
                        title: 'Procesando...',
                        /* ... loader ... */
                    });

                    // Enviar AJAX al handler centralizado
                    const ajaxUrl = '../ajax/workorder_actions.php';
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
                                    timer: 2000,
                                    timerProgressBar: true
                                });

                                // Recargar AMBAS tablas DataTables con AJAX
                                if (tableMisPickeos) {
                                    tableMisPickeos.ajax.reload(null, false);
                                }
                                if (tableDisponibles) {
                                    tableDisponibles.ajax.reload(null, false);
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

        // Re-inicializar Feather Icons al cargar
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>