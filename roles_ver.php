<?php


// roles_ver.php - Muestra la lista de Roles del sistema

require_once('includes/load.php'); // Carga inicial

// Asegurar que el usuario esté logueado
if (function_exists('requerir_login')) {
    requerir_login();
} else {
    die("Error: Sistema de autenticación no disponible.");
}

// Asegurar que solo el rol 'Admin' pueda acceder a esta página
if (!function_exists('tiene_rol') || !tiene_rol('Admin')) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para gestionar roles.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}

// Obtener la lista de todos los roles desde la base de datos
$lista_roles = [];
if (function_exists('buscar_todos_los_roles')) {
    $lista_roles = buscar_todos_los_roles(); // Función de sql.php
} else {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Error: La función para buscar roles no está disponible.", "danger");
    error_log("Error fatal: función buscar_todos_los_roles() no encontrada en roles_ver.php");
    // Podríamos redirigir o simplemente mostrar tabla vacía
}


$page_title = 'Ver Roles';
$active_page = 'usuarios'; // La sección padre principal
$active_subpage = 'ver_roles'; // La subpágina específica

// Incluir layout
include_once('layouts/header.php');
?>

<?php include_once('layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>
                <div>
                    <?php // Botón para ir a crear un nuevo rol 
                    ?>
                    <a href="roles_crear.php" class="btn btn-success">
                        <i class="align-middle" data-feather="plus"></i> <span class="align-middle">Crear Nuevo Rol</span>
                    </a>
                </div>
            </div>

            <div class="px-3 px-lg-4 mb-3">
                <?php
                // Mostrar mensajes flash (éxito/error al crear/editar/eliminar roles)
                if (function_exists('mostrar_mensaje_flash')) {
                    echo mostrar_mensaje_flash();
                }
                ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Roles Definidos</h5>
                    <h6 class="card-subtitle text-muted">Roles disponibles en el sistema y sus descripciones.</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover my-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre del Rol</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lista_roles)): ?>
                                    <?php foreach ($lista_roles as $rol): ?>
                                        <tr>
                                            <td><?php echo (int)$rol['id']; ?></td>
                                            <td><?php echo htmlspecialchars($rol['nombre_rol']); ?></td>
                                            <td><?php echo htmlspecialchars($rol['descripcion_rol'] ?? '-'); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <?php // Botón para Editar Rol (lleva a roles_editar.php) 
                                                    ?>
                                                    <a href="roles_editar.php?id=<?php echo (int)$rol['id']; ?>" class="btn btn-info btn-sm" title="Editar Rol y Permisos">
                                                        <i class="align-middle" data-feather="edit"></i> Editar
                                                    </a>

                                                    <?php // Botón para Eliminar Rol (con precauciones) 
                                                    ?>
                                                    <?php
                                                    // No permitir eliminar rol 'Admin' (o ID 1) y roles con usuarios asignados
                                                    // Necesitaríamos una función count_users_in_role($rol_id) en sql.php
                                                    $can_delete = true;
                                                    if ($rol['nombre_rol'] === 'Admin' || $rol['id'] === 1) { // No borrar Admin
                                                        $can_delete = false;
                                                    }
                                                    // else if (function_exists('count_users_in_role') && count_users_in_role($rol['id']) > 0) {
                                                    //    $can_delete = false; // No borrar si tiene usuarios
                                                    //}
                                                    ?>
                                                    <?php if ($can_delete): ?>
                                                        <form action="roles_eliminar.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Estás seguro de ELIMINAR el rol '<?php echo htmlspecialchars($rol['nombre_rol']); ?>'? ¡Los usuarios asignados podrían quedar sin rol si la restricción lo permite!">
                                                            <input type="hidden" name="rol_id_to_delete" value="<?php echo (int)$rol['id']; ?>">
                                                            <?php // Añadir CSRF token 
                                                            ?>
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar Rol">
                                                                <i class="align-middle" data-feather="trash-2"></i> Eliminar
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <?php // Mostrar botón deshabilitado o nada 
                                                        ?>
                                                        <button type="button" class="btn btn-danger btn-sm" title="No se puede eliminar este rol" disabled>
                                                            <i class="align-middle" data-feather="trash-2"></i> Eliminar
                                                        </button>
                                                    <?php endif; ?>

                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No se encontraron roles definidos.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include_once('layouts/footer.php'); ?>
</div>

<?php // Incluir script de confirmación si no está globalmente en el footer 
?>
<script>
    // Script para confirmación usando SweetAlert2
    document.addEventListener('DOMContentLoaded', function() {
        // Selecciona todos los formularios que necesitan confirmación
        const forms = document.querySelectorAll('.needs-confirmation');

        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                // 1. Prevenir el envío normal del formulario INMEDIATAMENTE
                event.preventDefault();

                const message = form.getAttribute('data-confirm-message') || '¿Estás seguro de realizar esta acción?';
                const formElement = this; // Guardar referencia al formulario

                // 2. Mostrar la alerta de SweetAlert2
                Swal.fire({
                    title: 'Confirmación',
                    text: message,
                    icon: 'warning', // Icono de advertencia
                    showCancelButton: true, // Mostrar botón de cancelar
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'No, cancelar',
                    confirmButtonColor: '#3B7DDD', // Azul primario AdminKit
                    cancelButtonColor: '#dc3545', // Rojo peligro Bootstrap
                    reverseButtons: true // Poner botón de confirmar a la derecha
                }).then((result) => {
                    // 3. Comprobar si el usuario confirmó
                    if (result.isConfirmed) {
                        // Si confirmó, ahora sí enviar el formulario original
                        formElement.submit();
                    }
                    // Si cancela (result.isDismissed), no se hace nada y el formulario no se envía.
                });
            });
        });

        // Volver a inicializar Feather Icons (si lo necesitas después de algún cambio dinámico)
        if (typeof feather !== 'undefined') {
            // Puede que no sea necesario aquí si solo es al cargar, pero no estorba
            // feather.replace(); 
        }
    });
</script>