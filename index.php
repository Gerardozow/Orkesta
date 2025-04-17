<?php
// index.php - Dashboard Principal (Versión AJAX para tablas)
// Fecha de última revisión: 16 de Abril de 2025

// Carga inicial: configuración, funciones, sesión, conexión BD.
require_once('includes/load.php');

// Asegura login
if (function_exists('requerir_login')) {
    requerir_login();
} else {
    die("Error: Sistema de autenticación no disponible.");
}

// --- Obtener datos usuario y rol ---
$datos_sesion = obtener_datos_sesion();
$current_user_id = $datos_sesion['id'] ?? null;
$current_user_rol = $datos_sesion['rol'] ?? null;
$current_user_name = $datos_sesion['nombre_completo'] ?? 'Usuario';

// --- Variables y Carga de Datos Condicional (SOLO PARA TARJETAS) ---
$mostrar_seccion_almacen = false; // Flag general para secciones de almacén/producción?
$mostrar_tabla_pickeos_activos = false; // Flag específico para la tabla de pickeos activos (Admin/Sup Alm)
$count_pendientes = 0;
$count_en_proceso = 0;
$count_en_espera = 0;
$count_parciales = 0;
$count_entregadas_hoy = 0;
// Las listas para las tablas se cargan vía AJAX

// Verificar si mostrar contenido de Almacén
// Ajusta los roles si es necesario (ej. añadir Sup Prod para ver?)
if (function_exists('tiene_algun_rol') && tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen', 'Supervisor Produccion'])) {
    $mostrar_seccion_almacen = true;

    // Cargar datos SOLO para las tarjetas de resumen
    if (function_exists('contar_wos_pendientes_pickeo')) $count_pendientes = contar_wos_pendientes_pickeo();
    if (function_exists('contar_wos_en_proceso_pickeo')) $count_en_proceso = contar_wos_en_proceso_pickeo();
    if (function_exists('contar_wos_en_espera_entrega')) $count_en_espera = contar_wos_en_espera_entrega();
    if (function_exists('contar_wos_entregadas_hoy')) $count_entregadas_hoy = contar_wos_entregadas_hoy();
    if (function_exists('contar_wos_parciales')) $count_parciales = contar_wos_parciales(); // Para la tarjeta de parciales
}
// Verificar si mostrar tabla de pickeos activos
if (function_exists('tiene_algun_rol') && tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen'])) {
    $mostrar_tabla_pickeos_activos = true;
}

// --- Variables de Plantilla ---
$page_title = 'Dashboard Principal';
$active_page = 'dashboard';

// --- Incluir Cabecera HTML ---
include_once('layouts/header.php');
?>
<?php // Asumimos CSS DataTables/SweetAlert2 está en header.php globalmente 
?>

<?php include_once('layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong>Dashboard</strong></h1>

            <?php if ($mostrar_seccion_almacen): ?>
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4"> <?php include_once('layouts/components/cards/card_pendientes_pickeo.php'); ?> </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4"> <?php include_once('layouts/components/cards/card_en_proceso_pickeo.php'); ?> </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4"> <?php include_once('layouts/components/cards/card_en_espera_entrega.php'); ?> </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4"> <?php include_once('layouts/components/cards/card_parciales.php'); ?> </div>
                </div><?php endif; ?>
            <?php if ($mostrar_tabla_pickeos_activos): ?>
                <div class="row mt-0">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h5 class="card-title mb-0">WOs en Pickeo (Pendiente / En Proceso / Parcial)</h5>
                                    <h6 class="card-subtitle text-muted pt-1">Órdenes aprobadas que esperan inicio, están en pickeo activo o pausado.</h6>
                                </div>
                                <div></div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tabla-wos-activas" class="table table-striped table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Work Order</th>
                                                <th>Num. Parte</th>
                                                <th>Est. Pickeo</th>
                                                <th>Asignado a</th>
                                                <th>Solic. Prod.</th>
                                                <th>Última Nota / Evento</th>
                                                <th>Últ. Act.</th>
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
                </div>
            <?php endif; // Fin if $mostrar_tabla_pickeos_activos 
            ?>
            <?php if ($mostrar_seccion_almacen): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h5 class="card-title mb-0">Work Orders Pendientes de Entrega</h5>
                                    <h6 class="card-subtitle text-muted pt-1">Órdenes aprobadas con pickeo completo, en proceso o parcial.</h6>
                                </div>
                                <div><?php /* Botones Exportar */ ?></div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tabla-wos-pend-entrega" class="table table-striped table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Work Order</th>
                                                <th>Num. Parte</th>
                                                <th>Descripción</th>
                                                <th>Estado Pickeo</th>
                                                <th>Asignado a</th>
                                                <th>Solic. Prod.</th>
                                                <th>Últ. Act. Estado</th>
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
                </div>
            <?php endif; // Fin if $mostrar_seccion_almacen 
            ?>
        </div>
    </main>

    <?php include_once('layouts/footer.php'); // Footer carga JS globales 
    ?>
</div> <?php // --- Scripts JS --- 
        ?>
<script>
    // Fallbacks
    if (typeof jQuery == 'undefined') {
        document.write('<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>');
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

        var tableActivas = null; // Referencia a la tabla DataTables WOs Activas
        var tablePendEntrega = null; // Referencia a la tabla DataTables WOs Pendientes Entrega

        // --- Inicializar DataTable para WOs Activas (CON AJAX) ---
        // Solo inicializar si la tabla existe (depende del rol PHP)
        if ($('#tabla-wos-activas').length) {
            tableActivas = $('#tabla-wos-activas').DataTable({
                responsive: true,
                processing: true, // Muestra indicador "Procesando..." de DataTables
                ajax: {
                    url: 'ajax/get_dashboard_data.php?tabla=pickeos_activos', // Script PHP que devuelve JSON
                    type: 'GET',
                    dataSrc: 'data' // Clave donde están los datos en el JSON
                },
                columns: [ // Definición de columnas para mapear JSON
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
                        data: 'estado_pickeo',
                        render: function(data, type, row) {
                            // Renderizar badge de estado pickeo + icono comentario
                            let status = data || 'PENDIENTE';
                            let p_badge = 'secondary';
                            let p_text = 'Pendiente';
                            if (status === 'EN_PROCESO') {
                                p_badge = 'primary';
                                p_text = 'En Proceso';
                            } else if (status === 'PARCIAL') {
                                p_badge = 'warning text-dark';
                                p_text = 'Parcial';
                            }
                            // Completo no debería mostrarse aquí según la query SQL
                            let badge = `<span class="badge bg-${p_badge}">${p_text}</span>`;
                            // Asumimos que 'tiene_comentario_progreso' viene del AJAX
                            if (['EN_PROCESO', 'PARCIAL'].includes(status) && (row.tiene_comentario_progreso == 1)) {
                                badge += ' <i class="align-middle ms-1" data-feather="message-square" style="width:14px; height:14px; color:#0d6efd;" title="Tiene comentarios"></i>';
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
                            return (data == 1) ? '<span class="badge bg-info">Sí</span>' : '<span class="badge bg-light text-dark">No</span>';
                        }
                    },
                    {
                        data: 'ultimo_detalle_historial',
                        render: function(data, type, row) {
                            // Renderizar último historial (acortado con tooltip)
                            if (!data) return '<span class="text-muted small">-</span>';
                            let safeData = $('<div>').text(data).html(); // Escapar para tooltip
                            let shortText = data.length > 40 ? data.substr(0, 40) + '...' : data;
                            return `<span title="${safeData}">${$('<div>').text(shortText).html()}</span>`; // Escapar para mostrar
                        }
                    },
                    {
                        data: 'fecha_estado_actualizacion',
                        render: function(data, type, row) {
                            // Renderizar fecha formateada
                            if (!data) return '-';
                            try {
                                let d = new Date(data + 'Z'); // Asumir UTC si no hay timezone
                                return d.toLocaleDateString('es-MX') + ' ' + d.toLocaleTimeString('es-MX', {
                                    hour12: false
                                });
                            } catch (e) {
                                return data;
                            } // Devolver original si falla formateo
                        }
                    },
                    {
                        data: 'workorder',
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            // Renderizar botón historial
                            let wo_encoded = encodeURIComponent(data);
                            return `<a href="almacen/historial_wo.php?wo=${wo_encoded}" class="btn btn-outline-secondary btn-sm" title="Ver Historial"><i data-feather="list"></i></a>`;
                        }
                    }
                ],
                // Opciones generales de DataTables
                dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                language: {
                    lengthMenu: "Mostrar _MENU_",
                    zeroRecords: "No hay WOs activas",
                    info: "_START_-_END_ de _TOTAL_",
                    infoEmpty: "0 activas",
                    infoFiltered: "(de _MAX_)",
                    search: "_INPUT_",
                    searchPlaceholder: "Buscar WO activa...",
                    paginate: {
                        first: "<<",
                        last: ">>",
                        next: ">",
                        previous: "<"
                    },
                    emptyTable: "No hay Work Orders aprobadas con pickeo pendiente, en proceso o parcial.", // Mensaje tabla vacía
                    processing: "Procesando..." // Mensaje indicador de carga
                },
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'Todos']
                ],
                order: [
                    [6, 'desc']
                ], // Ordenar por Últ. Act. (índice 6)
                columnDefs: [{
                    "orderable": false,
                    "targets": [7]
                }], // Historial (índice 7)
                // Callback para reinicializar iconos después de cada dibujo
                drawCallback: function(settings) {
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                }
            });

            // Inicializar/Mover Botones de Exportación para esta tabla
            new $.fn.dataTable.Buttons(tableActivas, {
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
            var buttonContainerActivas = tableActivas.buttons().container();
            // Añadir botones al header de la tarjeta específica
            buttonContainerActivas.appendTo($('#tabla-wos-activas').closest('.card').find('.card-header > div:last-child'));
            buttonContainerActivas.find('.dt-buttons').addClass('btn-group flex-wrap');
            buttonContainerActivas.find('.btn').removeClass('ms-1').addClass('m-1');
        }


        // --- Inicializar DataTable para WOs Pendientes de Entrega (CON AJAX) ---
        if ($('#tabla-wos-pend-entrega').length) {
            tablePendEntrega = $('#tabla-wos-pend-entrega').DataTable({
                responsive: true,
                processing: true,
                pageLength: 50,
                ajax: {
                    url: 'ajax/get_dashboard_data.php?tabla=pendientes_entrega',
                    type: 'GET',
                    dataSrc: 'data'
                },
                columns: [ // Columnas: WO, NP, Desc, EstPickeo, Asignado, Solic, UltActEst, Acción(Entregar)
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
                        data: 'estado_pickeo', // Renderizado corregido para PARCIAL
                        render: function(data, type, row) {
                            let status = data || 'PENDIENTE';
                            let badgeClass = 'secondary';
                            let text = status;
                            if (status === 'EN_PROCESO') {
                                badgeClass = 'primary';
                                text = 'En Proceso';
                            } else if (status === 'PARCIAL') {
                                badgeClass = 'warning text-dark';
                                text = 'Parcial';
                            } else if (status === 'COMPLETO') {
                                badgeClass = 'success';
                                text = 'Completo';
                            }
                            return `<span class="badge bg-${badgeClass}">${text}</span>`;
                        }
                    },
                    {
                        data: 'nombre_usuario_asignado',
                        render: $.fn.dataTable.render.text(),
                        defaultContent: "N/A"
                    },
                    {
                        data: 'solicitada_produccion',
                        render: function(data, type, row) {
                            /* ... render badge solicitada ... */
                            return (data == 1) ? '<span class="badge bg-info">Sí</span>' : '<span class="badge bg-light text-dark">No</span>';
                        }
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
                        data: 'workorder', // Columna Acción (Entregar)
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            // Lógica JS para mostrar botón Entregar condicionalmente
                            let puede_entregar_wo = false;
                            // Pasar rol PHP a JS de forma segura para usarlo aquí
                            const currentUserRoleJS = <?php echo json_encode($current_user_rol ?? ''); ?>;
                            const rolesAlmacenJS = ['Admin', 'Supervisor Almacen', 'Usuario Almacen'];
                            const rolesSupJS = ['Admin', 'Supervisor Almacen'];

                            if (row.estado_aprobacion_almacen === 'APROBADA') {
                                if (row.estado_pickeo === 'COMPLETO') {
                                    if (rolesAlmacenJS.includes(currentUserRoleJS)) puede_entregar_wo = true;
                                } else if (['EN_PROCESO', 'PARCIAL'].includes(row.estado_pickeo)) { // Incluir PARCIAL
                                    if (rolesSupJS.includes(currentUserRoleJS)) puede_entregar_wo = true;
                                }
                            }

                            if (puede_entregar_wo) {
                                let wo_escaped = $('<div>').text(data).html();
                                // Formulario que será interceptado por JS para AJAX
                                return `<form action="../ajax/workorder_actions.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Marcar WO ${wo_escaped} como ENTREGADA?">
                                             <input type="hidden" name="form_action" value="entregar_wo">
                                             <input type="hidden" name="workorder" value="${wo_escaped}">
                                             <button type="submit" class="btn btn-info btn-sm" title="Entregar a Producción"><i data-feather="truck"></i> Entregar</button>
                                         </form>`;
                            } else {
                                return '<span class="text-muted small" title="No cumple requisitos o sin permiso">-</span>';
                            }
                        } // fin render acción
                    } // fin columna acción
                ], // Fin columns
                dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                language: {
                    /* ... Español ... */
                    emptyTable: "No hay Work Orders pendientes de entrega."
                },
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'Todos']
                ],
                order: [
                    [6, 'asc']
                ], // Últ. Act. Estado (índice 6)
                columnDefs: [{
                    "orderable": false,
                    "targets": [7]
                }], // Acción (índice 7)
                drawCallback: function(settings) {
                    // Re-renderizar iconos Feather cada vez que se dibuja la tabla
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                    // Inicializar Tooltips de Bootstrap (si los usas)
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('#tabla-wos-pend-entrega [title]'));
                    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
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
            // Botones de exportación si se quieren para esta tabla
        }

        // --- Lógica SweetAlert2/AJAX para forms .needs-confirmation (común) ---
        // Se delega al contenedor '.content' para capturar submits de ambas tablas
        $('.content').on('submit', 'form.needs-confirmation', function(event) {
            event.preventDefault(); // Prevenir envío normal del form
            const formElement = this;
            const formData = new FormData(formElement); // Obtener datos del form que disparó el evento
            const message = $(formElement).data('confirm-message') || '¿Estás seguro?'; // Mensaje de confirmación
            const action = formData.get('form_action'); // Qué acción se está ejecutando

            // Definir qué acciones permiten comentario opcional en esta página
            const actions_with_optional_comment = ['entregar_wo'];
            // Definir qué acciones requieren comentario obligatorio (ninguna en esta página)
            const actions_with_required_comment = [];

            let swalConfig = { // Configuración base de SweetAlert2
                title: 'Confirmación',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'No, cancelar',
                confirmButtonColor: '#3B7DDD', // Color primario 
                cancelButtonColor: '#6c757d', // Color secundario
                reverseButtons: true // Botón confirmar a la derecha
            };

            // Ajustar config específica para la acción 'entregar_wo'
            if (action === 'entregar_wo') {
                swalConfig.icon = 'info';
                swalConfig.confirmButtonText = 'Sí, Entregar';
                // Habilitar comentario opcional
                swalConfig.input = 'textarea';
                swalConfig.inputLabel = 'Comentario (Opcional)';
                swalConfig.inputPlaceholder = 'Añade un comentario a la entrega...';
                swalConfig.confirmButtonText = 'Entregar y Guardar';
            }
            // Añadir más 'else if (action === ...)' si se añaden más botones de acción a ESTA PÁGINA

            // Añadir validador si el comentario es obligatorio
            if (actions_with_required_comment.includes(action)) {
                swalConfig.inputValidator = (value) => {
                    if (!value || value.trim() === '') return '¡Necesitas añadir un comentario!';
                }
            }

            // Mostrar el modal de SweetAlert2
            Swal.fire(swalConfig).then((result) => {
                // Si el usuario confirma
                if (result.isConfirmed) {
                    // Añadir comentario a los datos del formulario si el modal tenía input y se escribió algo
                    if (result.value !== undefined && result.value !== null && (actions_with_optional_comment.includes(action) || actions_with_required_comment.includes(action))) {
                        formData.append('accion_comentario', result.value);
                    }

                    // Mostrar loader mientras se procesa
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Por favor espera.',
                        didOpen: () => {
                            Swal.showLoading()
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });

                    // Enviar datos vía FETCH (AJAX) al handler PHP
                    const ajaxUrl = 'ajax/workorder_actions.php'; // Endpoint AJAX
                    fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData // Enviar los datos recolectados
                        })
                        .then(response => { // Recibir respuesta del servidor
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.statusText);
                            }
                            return response.json(); // Interpretar como JSON
                        })
                        .then(data => { // Procesar respuesta JSON
                            Swal.close(); // Ocultar el loader
                            if (data.success) { // Si la acción PHP fue exitosa
                                Swal.fire({ // Mostrar toast de éxito
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    text: data.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 2500,
                                    timerProgressBar: true
                                });
                                // Recargar AMBAS tablas DataTables vía AJAX para reflejar cambios
                                if (tableActivas) {
                                    tableActivas.ajax.reload(null, false);
                                }
                                if (tablePendEntrega) {
                                    tablePendEntrega.ajax.reload(null, false);
                                }
                            } else {
                                // Si la acción PHP falló, mostrar alerta de error
                                Swal.fire('Error', data.message || 'Ocurrió un error desconocido.', 'error');
                            }
                        })
                        .catch(error => { // Si hubo error en la comunicación AJAX
                            Swal.close(); // Ocultar loader
                            console.error('Error en petición AJAX:', error);
                            Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor. (' + error.message + ')', 'error');
                        });
                } // Fin if (result.isConfirmed)
            }); // Fin Swal.fire().then()
        }); // Fin event listener submit
        // --- NUEVO: Actualización Periódica de Tarjetas ---
        function actualizarContadoresTarjetas() {
            // Solo ejecutar si las tarjetas están visibles en la página
            if ($('#count-pendientes').length > 0) {
                const ajaxUrlCounts = 'ajax/get_dashboard_counts.php'; // URL del nuevo script PHP

                fetch(ajaxUrlCounts)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) { // Verificar si la respuesta fue exitosa desde PHP
                            // Formatear números (opcional, pero mejora lectura)
                            const formatNumber = (num) => new Intl.NumberFormat('es-MX').format(num || 0);

                            // Actualizar el texto de cada H1 con el nuevo conteo
                            $('#count-pendientes').text(formatNumber(data.pendientes));
                            $('#count-en-proceso').text(formatNumber(data.en_proceso));
                            $('#count-en-espera').text(formatNumber(data.en_espera));
                            $('#count-parciales').text(formatNumber(data.parciales));

                            // Opcional: Añadir un efecto visual sutil al actualizar
                            $('#count-pendientes, #count-en-proceso, #count-en-espera, #count-parciales')
                                .addClass('animate__animated animate__flash animate__faster');
                            setTimeout(() => {
                                $('#count-pendientes, #count-en-proceso, #count-en-espera, #count-parciales')
                                    .removeClass('animate__animated animate__flash animate__faster');
                            }, 500); // Requiere Animate.css

                        } else {
                            console.warn("No se pudieron actualizar los contadores: ", data.message || "Permiso denegado o error del servidor.");
                        }
                    })
                    .catch(error => {
                        console.error('Error al actualizar contadores de tarjetas:', error);
                    });
            }
        }

        // Llamar a la función una vez al cargar la página (después de un breve retraso)
        setTimeout(actualizarContadoresTarjetas, 1000);

        // Establecer intervalo para actualizar cada 10 seg
        setInterval(actualizarContadoresTarjetas, 10000);
        // --- Fin Actualización Periódica ---

        // Re-inicializar Feather Icons una vez que todo el DOM está listo
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

    }); // Fin $(document).ready
</script>