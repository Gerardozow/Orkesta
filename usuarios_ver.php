<?php
// usuarios_ver.php - Muestra la lista de usuarios y permite activar/desactivar

require_once('includes/load.php');
if (function_exists('requerir_login')) {
    requerir_login();
} else {
    die("Error: Sistema de autenticación no disponible.");
}

// --- Verificación de Rol: Solo Admin puede ver esta lista ---
if (!function_exists('tiene_rol') || !tiene_rol('Admin')) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para ver la lista de usuarios.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}
// --- Fin Verificación de Rol ---

$usuario_actual = obtener_datos_sesion();
if (!$usuario_actual) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al obtener datos de sesión.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('logout.php');
    exit;
}

$lista_usuarios = buscar_todos_los_usuarios();

$page_title = 'Lista de Usuarios';
$active_page = 'usuarios';
$active_subpage = 'ver_usuarios';

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
                    <?php // Mostrar botón solo si es Admin 
                    ?>
                    <?php if (tiene_rol('Admin')): ?>
                        <a href="usuarios_crear.php" class="btn btn-success">
                            <i class="align-middle" data-feather="plus"></i> <span class="align-middle">Añadir Usuario</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Usuarios Registrados</h5>
                    <h6 class="card-subtitle text-muted">Lista de todos los usuarios en el sistema.</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover my-0">
                            <thead>
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lista_usuarios)): ?>
                                    <?php foreach ($lista_usuarios as $usuario): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['email'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['nombre_rol'] ?? 'Sin Rol'); // Usar 'nombre_rol' 
                                                ?></td>
                                            <td class="text-center">
                                                <?php if ($usuario['activo'] == 1): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group" aria-label="Acciones Usuario <?php echo htmlspecialchars($usuario['username']); ?>">
                                                    <?php // Mostrar acciones solo si el usuario logueado es Admin 
                                                    ?>
                                                    <?php if (tiene_rol('Admin')): ?>

                                                        <?php // --- Botón Editar --- 
                                                        ?>
                                                        <a href="usuarios_editar.php?id=<?php echo (int)$usuario['id']; ?>" class="btn btn-info btn-sm" title="Editar Usuario">
                                                            <i class="align-middle" data-feather="edit-2"></i>
                                                        </a>

                                                        <?php // --- Botón Activar/Desactivar --- 
                                                        ?>
                                                        <?php if ($usuario_actual['id'] != $usuario['id']): // No mostrar para uno mismo 
                                                        ?>
                                                            <?php if ($usuario['activo'] == 1): ?>
                                                                <?php // Formulario para DESACTIVAR 
                                                                ?>
                                                                <form action="usuarios_estado.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Desactivar a este usuario?">
                                                                    <input type="hidden" name="user_id" value="<?php echo (int)$usuario['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="0">
                                                                    <button type="submit" class="btn btn-warning btn-sm" title="Desactivar Usuario">
                                                                        <i class="align-middle" data-feather="user-minus"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <?php // Formulario para ACTIVAR 
                                                                ?>
                                                                <form action="usuarios_estado.php" method="POST" class="d-inline needs-confirmation" data-confirm-message="¿Activar a este usuario?">
                                                                    <input type="hidden" name="user_id" value="<?php echo (int)$usuario['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="1">
                                                                    <button type="submit" class="btn btn-success btn-sm" title="Activar Usuario">
                                                                        <i class="align-middle" data-feather="user-check"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <?php // Botón Eliminar (Comentado) 
                                                        ?>
                                                        <?php /* ... */ ?>

                                                    <?php else: // Si NO es Admin 
                                                    ?>
                                                        <span class="text-muted small">N/A</span>
                                                    <?php endif; // Fin if tiene_rol('Admin') 
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No se encontraron usuarios registrados.</td>
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