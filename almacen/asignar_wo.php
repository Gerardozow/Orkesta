<?php
// almacen/asignar_wo.php - Página para ver y auto-asignarse WOs listas para pickear

require_once('../includes/load.php');
requerir_login();

// --- Permisos: Roles de Almacén pueden ver y asignarse ---
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen'])) {
    mensaje_sesion("No tienes permiso para acceder a esta sección.", "danger");
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Variables ---
$page_title = 'Pickeos Disponibles para Asignar';
$active_page = 'almacen';
$active_subpage = 'asignar_wo';
$form_action = $_POST['form_action'] ?? null;
$datos_sesion = obtener_datos_sesion();
$current_user_id = $datos_sesion['id'] ?? null;


// --- Procesar Acción POST (Auto-asignación) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form_action === 'auto_asignar_wo' && $current_user_id) {

    $wo_a_asignar = $_POST['workorder'] ?? null;
    $should_redirect = true; // Redirigir por defecto

    if (empty($wo_a_asignar)) {
        mensaje_sesion("No se especificó la Work Order a asignar.", "warning");
        $should_redirect = false;
    } else {
        // --- Doble Verificación de Estado ANTES de asignar ---
        // Asegura que la WO todavía esté disponible y lista en el momento exacto
        $estado_actual = null;
        try {
            global $db;
            // Comprobamos todos los criterios necesarios
            $sql_check = "SELECT estado_aprobacion_almacen, estado_pickeo, id_usuario_asignado 
                          FROM workorder_status 
                          WHERE workorder = :wo 
                            AND requiere_pickeo = 1 
                            AND estado_aprobacion_almacen = 'APROBADA' 
                            AND estado_pickeo = 'PENDIENTE' 
                            AND id_usuario_asignado IS NULL 
                            AND estado_entrega = 'PENDIENTE' 
                          LIMIT 1";
            $stmt_check = $db->prepare($sql_check);
            $stmt_check->execute([':wo' => $wo_a_asignar]);
            $estado_actual = $stmt_check->fetch(); // Será false si no cumple criterios

        } catch (\PDOException $e) {
            error_log("Error verificando estado antes de asignar WO {$wo_a_asignar}: " . $e->getMessage());
            mensaje_sesion("Error de base de datos al verificar la WO.", "danger");
            $estado_actual = false;
            $should_redirect = false;
        }

        // Si la verificación pasó (la WO está realmente disponible)
        if ($estado_actual) {
            // Preparar actualización: Cambiar estado y asignar usuario
            $update_data = [
                'estado_pickeo'       => 'EN_PROCESO',
                'id_usuario_asignado' => $current_user_id
            ];

            if (actualizar_estado_wo($wo_a_asignar, $update_data)) {
                mensaje_sesion("WO '" . htmlspecialchars($wo_a_asignar) . "' asignada a ti correctamente. ¡Puedes empezar el pickeo!", "success");
                registrar_historial_wo($wo_a_asignar, 'ASIGNACION_PICKEO', "Auto-asignado a Usuario ID: $current_user_id");
                // Redirigir a "Mis Pickeos" para verla ahí
                redirigir_a('mis_pickeos.php');
                exit; // Salir después de redirigir
            } else {
                mensaje_sesion("Error al intentar asignar la WO '" . htmlspecialchars($wo_a_asignar) . "'.", "danger");
                $should_redirect = false; // No redirigir para ver el error
            }
        } else {
            // Si $estado_actual es false, significa que ya no cumple las condiciones
            mensaje_sesion("La WO '" . htmlspecialchars($wo_a_asignar) . "' ya no está disponible para asignación (probablemente asignada por otro usuario).", "warning");
            // Redirigir a la misma página para refrescar la lista
        }
    } // Fin else empty wo

    // Redirigir si no se hizo antes
    if ($should_redirect) {
        redirigir_a('asignar_wo.php');
        exit;
    }
} // --- Fin POST ---


// --- Obtener Datos para la Tabla (WOs disponibles) ---
$lista_wos_disponibles = [];
if (function_exists('buscar_wos_para_asignar')) {
    $lista_wos_disponibles = buscar_wos_para_asignar(); // Usa la función de sql.php
} else {
    error_log("Error: Función buscar_wos_para_asignar() no encontrada.");
    mensaje_sesion("Error al cargar la lista de Work Orders disponibles.", "danger");
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
                        <h5 class="card-title mb-0">Work Orders Aprobadas Pendientes de Pickeo</h5>
                        <h6 class="card-subtitle text-muted pt-1">Selecciona una WO para asignártela e iniciar el pickeo.</h6>
                    </div>
                    <div><?php /* Contenedor botones exportar si se necesitan */ ?></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-asignar-wo" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Work Order</th>
                                    <th>Num. Parte</th>
                                    <th>Descripción</th>
                                    <th>Última Actualización Estado</th>
                                    <th class="text-center dt-nowrap">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lista_wos_disponibles)): ?>
                                    <?php foreach ($lista_wos_disponibles as $wo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($wo['workorder']); ?></td>
                                            <td><?php echo htmlspecialchars($wo['numero_parte'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($wo['descripcion'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars(function_exists('formatear_fecha') ? formatear_fecha($wo['fecha_estado_actualizacion'], 'd/m/Y H:i') : ($wo['fecha_estado_actualizacion'] ?? '-')); ?></td>
                                            <td class="text-center">
                                                <?php // Botón para Auto-Asignarse 
                                                ?>
                                                <form action="asignar_wo.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Asignarte la WO <?php echo htmlspecialchars($wo['workorder']); ?> para iniciar pickeo?">
                                                    <input type="hidden" name="form_action" value="auto_asignar_wo">
                                                    <input type="hidden" name="workorder" value="<?php echo htmlspecialchars($wo['workorder']); ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm" title="Asignarme esta WO">
                                                        <i class="align-middle" data-feather="user-check"></i> Asignarme
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No hay Work Orders disponibles para asignar en este momento.</td>
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

<?php // --- Scripts JS --- 
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php // DataTables y extensiones cargadas desde footer.php 
?>

<script>
    $(document).ready(function() {
        // Inicializar DataTables para esta tabla
        var table = $('#tabla-asignar-wo').DataTable({
            responsive: true,
            // No necesitamos botones de exportación aquí por defecto
            dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            language: {
                /* ... Español ... */
                lengthMenu: "Mostrar _MENU_",
                zeroRecords: "No hay WOs disponibles",
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
            // Ordenar por fecha de estado ascendente (más antigua primero)
            order: [
                [3, 'asc']
            ]
        });

        // Lógica de Confirmación con SweetAlert2
        $('#tabla-asignar-wo').on('submit', 'form.needs-confirmation', function(event) {
            event.preventDefault();
            const message = $(this).data('confirm-message') || '¿Estás seguro?';
            const formElement = this;

            Swal.fire({
                title: 'Confirmar Asignación',
                text: message,
                icon: 'question', // Icono de pregunta
                showCancelButton: true,
                confirmButtonText: 'Sí, asignarme',
                cancelButtonText: 'No, cancelar',
                confirmButtonColor: '#3B7DDD',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    formElement.submit();
                }
            });
        });

        // Re-inicializar Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>