<?php

require_once('../includes/load.php');
requerir_login();

// --- Permisos: Solo Admin y Supervisor Almacen ---
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen'])) {
    mensaje_sesion("No tienes permiso para acceder a la asignación de pickeos.", "danger");
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Obtener lista de usuarios a los que se puede asignar/reasignar ---
$lista_usuarios_asignables = [];
if (function_exists('buscar_usuarios_para_asignacion')) {
    // Esta función devuelve usuarios con roles Admin, Sup Alm, User Alm que están activos
    $lista_usuarios_asignables = buscar_usuarios_para_asignacion();
} else {
    error_log("Error: Función buscar_usuarios_para_asignacion() no encontrada."); // Mantenemos log de error crítico
    mensaje_sesion("Error al cargar la lista de usuarios para asignación.", "danger");
}

// --- Variables ---
$page_title = 'Asignación de Pickeo';
$active_page = 'almacen';
$active_subpage = 'asignacion_pickeo'; // Para resaltar en sidebar
$form_action = $_POST['form_action'] ?? null;
$datos_sesion = obtener_datos_sesion();
$current_user_id = $datos_sesion['id'] ?? null;


// --- Procesar Acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user_id) {

    $should_redirect = true;
    $wo_afectada = $_POST['workorder'] ?? null;
    $nuevo_usuario_id = isset($_POST['nuevo_usuario_id']) ? (int)$_POST['nuevo_usuario_id'] : null; // ID para asignar/reasignar
    $comentario = trim($_POST['accion_comentario'] ?? ''); // Comentario opcional (no usado en asignar/reasignar por ahora)
    $detalle_historial = '';
    $accion_realizada = false;
    $tipo_historial = '';

    if (empty($wo_afectada)) {
        mensaje_sesion("No se especificó la Work Order.", "warning");
        $should_redirect = false;
    } else {
        // Obtener estado actual completo de la WO afectada ANTES de procesar
        $estado_wo_actual = null;
        try {
            global $db;
            $sql_st = "SELECT requiere_pickeo, estado_aprobacion_almacen, estado_pickeo, solicitada_produccion, estado_entrega, id_usuario_asignado
FROM workorder_status WHERE workorder = :wo LIMIT 1";
            $stmt_st = $db->prepare($sql_st);
            $stmt_st->execute([':wo' => $wo_afectada]);
            $estado_wo_actual = $stmt_st->fetch();
            if (!$estado_wo_actual) {
                throw new Exception("No se encontró registro de estado para la WO.");
            }
        } catch (\PDOException | \Exception $e) {
            error_log("Error buscando estado actual para acción en WO {$wo_afectada}: " . $e->getMessage());
            mensaje_sesion("Error de base de datos al buscar la WO para la acción.", "danger");
            $estado_wo_actual = false;
            $should_redirect = false;
        }

        // Procesar acción solo si encontramos el estado de la WO
        if ($estado_wo_actual) {

            // --- Acción: Asignar Pickeo (cuando estaba PENDIENTE) ---
            if ($form_action === 'asignar_pickeo') {
                $tipo_historial = 'ASIGNACION_PICKEO';
                // Validar estado y que el usuario a asignar sea válido
                if ($estado_wo_actual['estado_pickeo'] !== 'PENDIENTE' || $estado_wo_actual['id_usuario_asignado'] !== null) {
                    mensaje_sesion("Esta WO ya no está pendiente de asignación.", "warning");
                    $should_redirect = false;
                } elseif (empty($nuevo_usuario_id)) {
                    mensaje_sesion("Debes seleccionar un usuario para asignar.", "warning");
                    $should_redirect = false;
                } else {
                    $usuario_valido = false;
                    foreach ($lista_usuarios_asignables as $usr) {
                        if ($usr['id'] == $nuevo_usuario_id) {
                            $usuario_valido = true;
                            break;
                        }
                    }
                    if (!$usuario_valido) {
                        mensaje_sesion("Usuario seleccionado inválido.", "danger");
                        $should_redirect = false;
                    } else {
                        // Asignar y poner en proceso
                        $update_data = ['estado_pickeo' => 'EN_PROCESO', 'id_usuario_asignado' => $nuevo_usuario_id];
                        if (actualizar_estado_wo($wo_afectada, $update_data)) {
                            mensaje_sesion("WO asignada correctamente.", "success");
                            $detalle_historial = "Asignado a Usuario ID: $nuevo_usuario_id por Usuario ID: $current_user_id.";
                            $accion_realizada = true;
                        } else {
                            mensaje_sesion("Error al asignar la WO.", "danger");
                            $should_redirect = false;
                        }
                    }
                }
            }
            // --- Acción: Reasignar Pickeo (cuando estaba EN_PROCESO) ---
            elseif ($form_action === 'reasignar_pickeo') {
                $tipo_historial = 'REASIGNACION_PICKEO';
                $usuario_asignado_actual_id = $estado_wo_actual['id_usuario_asignado'];
                // Validar estado y selección
                if ($estado_wo_actual['estado_pickeo'] !== 'EN_PROCESO' || $usuario_asignado_actual_id === null) {
                    mensaje_sesion("Solo reasignar WOs EN PROCESO y ya asignadas.", "warning");
                    $should_redirect = false;
                } elseif (empty($nuevo_usuario_id)) {
                    mensaje_sesion("No se seleccionó nuevo usuario.", "warning");
                    $should_redirect = false;
                } elseif ($nuevo_usuario_id == $usuario_asignado_actual_id) {
                    mensaje_sesion("La WO ya está asignada a ese usuario.", "info");
                    $should_redirect = false;
                } else {
                    $nuevo_usuario_valido = false;
                    $nombre_nuevo_usuario = '?';
                    foreach ($lista_usuarios_asignables as $usr) {
                        if ($usr['id'] == $nuevo_usuario_id) {
                            $nuevo_usuario_valido = true;
                            $nombre_nuevo_usuario = $usr['nombre_completo'];
                            break;
                        }
                    }
                    if (!$nuevo_usuario_valido) {
                        mensaje_sesion("Usuario seleccionado inválido.", "danger");
                        $should_redirect = false;
                    } else {
                        // Solo actualizar asignación
                        $update_data = ['id_usuario_asignado' => $nuevo_usuario_id];
                        if (actualizar_estado_wo($wo_afectada, $update_data)) {
                            mensaje_sesion("WO reasignada a " . htmlspecialchars($nombre_nuevo_usuario) . ".", "success");
                            $detalle_historial = "Reasignado a Usuario ID: $nuevo_usuario_id (Desde: $usuario_asignado_actual_id) por Usuario ID: $current_user_id.";
                            $accion_realizada = true;
                        } else {
                            mensaje_sesion("Error al reasignar WO.", "danger");
                            $should_redirect = false;
                        }
                    }
                }
            } // --- Fin Reasignar ---
            else {
                // Ignorar acciones desconocidas o no pertinentes a esta página
                if (!empty($form_action)) { // Solo mostrar mensaje si se envió una acción
                    mensaje_sesion("Acción no válida en esta página: $form_action.", "warning");
                    $should_redirect = false;
                }
            }

            // Registrar historial si se realizó una acción válida
            if ($accion_realizada && !empty($tipo_historial)) {
                // Comentario no se usa en estas acciones por ahora, pero podría añadirse al modal/form si es necesario
                // if (!empty($comentario)) { $detalle_historial .= " Comentario: " . $comentario; }
                if (function_exists('registrar_historial_wo')) {
                    registrar_historial_wo($wo_afectada, $tipo_historial, $detalle_historial);
                }
            }
        } // Fin if ($estado_wo_actual)

    } // Fin if empty($wo_afectada) else ...

    // --- Redirección final ---
    if ($should_redirect) {
        if (function_exists('redirigir_a')) redirigir_a('asignacion_pickeo.php');
        exit;
    }
} // --- Fin POST ---


// --- Obtener Datos para la Tabla ---
$lista_wos_gestion_asignacion = [];
if (function_exists('buscar_wos_para_gestion_asignacion')) {
    $lista_wos_gestion_asignacion = buscar_wos_para_gestion_asignacion(); // Usa la función de sql.php
} else {
    error_log("Error: Función buscar_wos_para_gestion_asignacion() no encontrada.");
    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al cargar la lista de Work Orders.", "danger");
}

// Incluir layout
include_once('../layouts/header.php');
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
                        <h5 class="card-title mb-0">Work Orders para Asignar / Reasignar</h5>
                        <h6 class="card-subtitle text-muted pt-1">WOs aprobadas con pickeo pendiente o en proceso.</h6>
                    </div>
                    <div><?php /* Contenedor botones exportar si se necesitan */ ?></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-asignacion-wo" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Work Order</th>
                                    <th>Num. Parte</th>
                                    <th>Descripción</th>
                                    <th>Estado Pickeo</th>
                                    <th>Usuario Asignado</th>
                                    <th>Últ. Act. Estado</th>
                                    <th class="text-center dt-nowrap">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lista_wos_gestion_asignacion)): ?>
                                    <?php foreach ($lista_wos_gestion_asignacion as $wo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($wo['workorder']); ?></td>
                                            <td><?php echo htmlspecialchars($wo['numero_parte'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($wo['descripcion'] ?? '-'); ?></td>
                                            <td><?php // Badge Pickeo 
                                                ?>
                                                <?php
                                                $pick_status = $wo['estado_pickeo'] ?? 'PENDIENTE';
                                                $pick_badge_class = ($pick_status === 'EN_PROCESO') ? 'primary' : 'secondary';
                                                $pick_text = ($pick_status === 'EN_PROCESO') ? 'En Proceso' : 'Pendiente';
                                                ?>
                                                <span class="badge bg-<?php echo $pick_badge_class; ?>"><?php echo $pick_text; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($wo['nombre_usuario_asignado'] ?? 'Sin Asignar'); ?></td>
                                            <td><?php echo htmlspecialchars(function_exists('formatear_fecha') ? formatear_fecha($wo['fecha_estado_actualizacion'], 'd/m/Y H:i') : ($wo['fecha_estado_actualizacion'] ?? '-')); ?></td>

                                            <td class="text-center">
                                                <?php if ($wo['estado_pickeo'] === 'PENDIENTE' && empty($wo['id_usuario_asignado'])): ?>
                                                    <?php // Botón ASIGNAR (dispara JS) 
                                                    ?>
                                                    <button type="button" class="btn btn-success btn-sm btn-asignar"
                                                        title="Asignar Pickeo"
                                                        data-wo="<?php echo htmlspecialchars($wo['workorder']); ?>">
                                                        <i class="align-middle" data-feather="user-plus"></i> Asignar
                                                    </button>
                                                <?php elseif ($wo['estado_pickeo'] === 'EN_PROCESO' && !empty($wo['id_usuario_asignado'])): ?>
                                                    <?php // Botón REASIGNAR (dispara JS) 
                                                    ?>
                                                    <button type="button" class="btn btn-purple btn-sm btn-reasignar"
                                                        title="Reasignar Pickeo"
                                                        data-wo="<?php echo htmlspecialchars($wo['workorder']); ?>"
                                                        data-current-assignee-id="<?php echo (int)$wo['id_usuario_asignado']; ?>">
                                                        <i class="align-middle" data-feather="users"></i> Reasignar
                                                    </button>
                                                <?php else: ?>
                                                    <?php // Otro estado (ej. ya completo pero no entregado), sin acción aquí 
                                                    ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>

                                        <td colspan="7" class="text-center">No hay Work Orders pendientes de asignar o en proceso.</td>
                                        <?php // crear 6 celdas vacías para centrar el mensaje
                                        for ($i = 0; $i < 6; $i++): ?>
                                            <td></td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include_once('../layouts/footer.php'); ?>
</div>



<script>
    $(document).ready(function() {
        // Inicializar DataTables
        var table = $('#tabla-asignacion-wo').DataTable({
            responsive: true,
            dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            language: {
                /* ... Español ... */
                lengthMenu: "Mostrar _MENU_",
                zeroRecords: "No hay WOs",
                info: "_START_-_END_ de _TOTAL_",
                infoEmpty: "0 disponibles",
                infoFiltered: "(de _MAX_)",
                search: "_INPUT_",
                searchPlaceholder: "Buscar WO...",
                paginate: {
                    first: "<<",
                    last: ">>",
                    next: ">",
                    previous: "<"
                }
            },
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, 'Todos']
            ],
            order: [
                [5, 'asc']
            ] // Ordenar por fecha asc
        });

        // Preparar opciones para el select de SweetAlert2
        const usuariosAsignables = <?php echo json_encode($lista_usuarios_asignables ?? []); ?>;
        const opcionesUsuariosSwal = {};
        if (usuariosAsignables.length > 0) {
            opcionesUsuariosSwal[''] = 'Selecciona un usuario...'; // Opción vacía
            usuariosAsignables.forEach(user => {
                opcionesUsuariosSwal[user.id] = user.nombre_completo;
            });
        } else {
            opcionesUsuariosSwal[''] = 'No hay usuarios disponibles';
        }

        // --- Manejador para el botón Asignar ---
        $('#tabla-asignacion-wo').on('click', '.btn-asignar', function() {
            const workOrder = $(this).data('wo');
            if (!workOrder) return;

            Swal.fire({
                title: 'Asignar WO: ' + workOrder,
                input: 'select',
                inputOptions: opcionesUsuariosSwal,
                inputPlaceholder: 'Selecciona un usuario...',
                inputLabel: 'Asignar a:',
                showCancelButton: true,
                confirmButtonText: 'Asignar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3B7DDD',
                cancelButtonColor: '#6c757d',
                inputValidator: (value) => {
                    if (!value) return '¡Debes seleccionar un usuario!';
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Crear y enviar form oculto para asignar
                    submitAssignmentForm('asignar_pickeo', workOrder, result.value);
                }
            });
        });

        // --- Manejador para el botón Reasignar ---
        $('#tabla-asignacion-wo').on('click', '.btn-reasignar', function() {
            const workOrder = $(this).data('wo');
            const currentAssigneeId = $(this).data('current-assignee-id');
            if (!workOrder) return;

            let opcionesReasignar = {
                ...opcionesUsuariosSwal
            }; // Clonar
            // Opcional: delete opcionesReasignar[currentAssigneeId]; // Quitar usuario actual

            Swal.fire({
                title: 'Reasignar WO: ' + workOrder,
                input: 'select',
                inputOptions: opcionesReasignar,
                inputPlaceholder: 'Selecciona el nuevo usuario...',
                inputLabel: 'Reasignar a:',
                showCancelButton: true,
                confirmButtonText: 'Reasignar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3B7DDD',
                cancelButtonColor: '#6c757d',
                inputValidator: (value) => {
                    if (!value) return '¡Debes seleccionar un usuario!';
                    // Permitir reasignar al mismo usuario aquí si se quitó del delete anterior, 
                    // la validación PHP lo detectará si es necesario. O validar aquí:
                    // if (value == currentAssigneeId) return 'La WO ya está asignada a este usuario.';
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Crear y enviar form oculto para reasignar
                    submitAssignmentForm('reasignar_pickeo', workOrder, result.value);
                }
            });
        });

        // --- Función auxiliar para crear y enviar el formulario oculto ---
        function submitAssignmentForm(action, workorder, userId) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'asignacion_pickeo.php'; // Asegúrate que la acción es correcta
            form.insertAdjacentHTML('beforeend', `<input type="hidden" name="form_action" value="${action}">`);
            form.insertAdjacentHTML('beforeend', `<input type="hidden" name="workorder" value="${workorder}">`);
            form.insertAdjacentHTML('beforeend', `<input type="hidden" name="nuevo_usuario_id" value="${userId}">`);
            // Añadir comentario si se implementa en el modal de asignación/reasignación (no implementado ahora)
            // if (comment) { form.insertAdjacentHTML('beforeend', `<input type="hidden" name="accion_comentario" value="${comment}">`); }
            document.body.appendChild(form);
            form.submit();
        }

        // Inicializar Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>