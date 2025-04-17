<?php
// roles_editar.php - Formulario y procesamiento para editar un rol existente

require_once('includes/load.php');
requerir_login();

// --- Verificación de Rol: Solo Admin puede editar roles ---
if (!function_exists('tiene_rol') || !tiene_rol('Admin')) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para editar roles.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}

// --- Obtener y Validar ID del Rol a Editar ---
$role_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($role_id_to_edit <= 0) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("ID de rol inválido.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('roles_ver.php');
    exit;
}

// --- Obtener Datos Actuales del Rol ---
$role_to_edit = buscar_rol_por_id($role_id_to_edit); // Usa función de sql.php

if (!$role_to_edit) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Rol no encontrado.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('roles_ver.php');
    exit;
}

// --- Prevenir Edición de Nombre del Rol 'Admin' (o ID 1) ---
$can_edit_name = true;
if ($role_to_edit['nombre_rol'] === 'Admin' || $role_to_edit['id'] === 1) { // Asumiendo ID 1 es Admin
    $can_edit_name = false;
}

// --- Variables ---
$page_title = 'Editar Rol: ' . htmlspecialchars($role_to_edit['nombre_rol']);
$active_page = 'usuarios';
$active_subpage = 'ver_roles'; // Mantener activo el menú de roles
$validation_errors = [];
$form_data_post = []; // Para repoblar

// --- Procesar POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data_post = $_POST;

    // Validar ID consistente
    $posted_role_id = (int)($_POST['rol_id'] ?? 0);
    if ($posted_role_id !== $role_id_to_edit) {
        mensaje_sesion("Error de inconsistencia de datos al editar rol.", "danger");
        redirigir_a('roles_ver.php');
    }

    // Recuperar datos
    $nombre_rol_nuevo = trim($_POST['nombre_rol'] ?? '');
    $descripcion_rol = trim($_POST['descripcion_rol'] ?? '');
    $nombre_rol_original = $role_to_edit['nombre_rol'];

    // --- Validación ---
    if ($can_edit_name) { // Solo validar nombre si se permite editarlo
        if (empty($nombre_rol_nuevo)) {
            $validation_errors['nombre_rol'] = "El nombre del rol es obligatorio.";
        } elseif ($nombre_rol_nuevo !== $nombre_rol_original) {
            // Verificar si el NUEVO nombre ya existe en OTRO rol
            try {
                global $db;
                $sql_check = "SELECT id FROM roles WHERE nombre_rol = :nombre AND id != :id LIMIT 1";
                $stmt_check = $db->prepare($sql_check);
                $stmt_check->execute([':nombre' => $nombre_rol_nuevo, ':id' => $role_id_to_edit]);
                if ($stmt_check->fetch()) {
                    $validation_errors['nombre_rol'] = "Este nombre de rol ya está en uso.";
                }
            } catch (\PDOException $e) {
                mensaje_sesion("Error de base de datos al verificar el nombre del rol.", "danger");
                error_log("Error verificando nombre rol en roles_editar.php: " . $e->getMessage());
                $validation_errors['nombre_rol'] = "Error al verificar disponibilidad del nombre."; // Marcar error
            }
        }
    } else {
        // Si no se puede editar el nombre, nos aseguramos de usar el original
        $nombre_rol_nuevo = $nombre_rol_original;
    }

    // Descripción no tiene validación específica (aparte de trim)

    // --- Si no hay errores, actualizar ---
    if (empty($validation_errors)) {
        $update_data = [
            'descripcion_rol' => ($descripcion_rol === '') ? null : $descripcion_rol // Guardar NULL si está vacío
        ];
        // Añadir nombre solo si se podía editar
        if ($can_edit_name) {
            $update_data['nombre_rol'] = $nombre_rol_nuevo;
        }

        if (actualizar_rol($role_id_to_edit, $update_data)) {
            mensaje_sesion("Rol '" . htmlspecialchars($nombre_rol_nuevo) . "' actualizado con éxito.", "success");
            redirigir_a('roles_ver.php');
        } else {
            mensaje_sesion("Error al actualizar el rol. ¿Intentaste usar un nombre ya existente?", "danger");
            // No redirigir, mostrar errores
        }
    } else {
        mensaje_sesion("Por favor, corrige los errores indicados.", "warning");
        // No redirigir
    }
} // Fin POST

// --- Preparar datos para mostrar en el formulario ---
$display_data = !empty($validation_errors) ? $form_data_post : $role_to_edit;

// --- Incluir Layout ---
include_once('layouts/header.php');
?>

<?php include_once('layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3"><strong><?php echo $page_title; ?></strong></h1>
                <a href="roles_ver.php" class="btn btn-secondary">
                    <i class="align-middle" data-feather="arrow-left"></i> <span class="align-middle">Volver a la Lista</span>
                </a>
            </div>

            <div class="px-3 px-lg-4 mb-3">
                <?php
                if (function_exists('mostrar_mensaje_flash')) {
                    echo mostrar_mensaje_flash();
                }
                ?>
            </div>

            <div class="row">
                <div class="col-12 col-lg-8 col-xl-6 mx-auto"> <?php // Centrar un poco el form 
                                                                ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Detalles del Rol</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="roles_editar.php?id=<?php echo $role_id_to_edit; ?>" novalidate>
                                <input type="hidden" name="rol_id" value="<?php echo (int)$role_id_to_edit; ?>">

                                <?php // Nombre del Rol 
                                ?>
                                <div class="mb-3">
                                    <label for="nombre_rol" class="form-label">Nombre del Rol <span class="text-danger">*</span></label>
                                    <input type="text" name="nombre_rol" class="form-control <?php echo isset($validation_errors['nombre_rol']) ? 'is-invalid' : ''; ?>"
                                        id="nombre_rol" value="<?php echo htmlspecialchars($display_data['nombre_rol'] ?? ''); ?>"
                                        <?php echo !$can_edit_name ? 'readonly' : 'required'; // Hacer readonly si no se puede editar 
                                        ?>>
                                    <?php if (!$can_edit_name): ?>
                                        <small class="form-text text-muted">El nombre del rol 'Admin' no se puede modificar.</small>
                                    <?php endif; ?>
                                    <?php if (isset($validation_errors['nombre_rol'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['nombre_rol']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php // Descripción del Rol 
                                ?>
                                <div class="mb-3">
                                    <label for="descripcion_rol" class="form-label">Descripción</label>
                                    <textarea name="descripcion_rol" id="descripcion_rol" rows="3" class="form-control <?php echo isset($validation_errors['descripcion_rol']) ? 'is-invalid' : ''; ?>"><?php echo htmlspecialchars($display_data['descripcion_rol'] ?? ''); ?></textarea>
                                    <?php if (isset($validation_errors['descripcion_rol'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['descripcion_rol']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <hr>
                                <div class="mb-3">
                                    <h5 class="card-title">Gestión de Permisos</h5>
                                    <p class="text-muted">La asignación de permisos para este rol se realizará en una futura actualización.</p>
                                    <?php // Aquí iría la lógica para mostrar y seleccionar permisos cuando se implemente 
                                    ?>
                                    <div class="alert alert-info">Funcionalidad de asignación de permisos pendiente.</div>
                                </div>

                                <?php // Botones Finales 
                                ?>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                    <a href="roles_ver.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include_once('layouts/footer.php'); ?>
</div>