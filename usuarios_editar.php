<?php
// usuarios_editar.php - Formulario y procesamiento para editar un usuario existente

require_once('includes/load.php');
requerir_login();

// --- Verificación de Rol: Solo Admin puede editar usuarios ---
if (!function_exists('tiene_rol') || !tiene_rol('Admin')) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para editar usuarios.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}

// --- Obtener y Validar ID del Usuario a Editar ---
$user_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id_to_edit <= 0) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("ID de usuario inválido.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php');
    exit;
}

// --- Obtener Datos Actuales del Usuario ---
$user_to_edit = buscar_usuario_por_id($user_id_to_edit); // Busca por ID

if (!$user_to_edit) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Usuario no encontrado.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php');
    exit;
}

// --- Obtener Roles Disponibles ---
$lista_roles_bd = [];
if (function_exists('buscar_todos_los_roles')) {
    $lista_roles_bd = buscar_todos_los_roles();
} else {
    error_log("Error: Función buscar_todos_los_roles() no encontrada.");
    mensaje_sesion("Error al cargar los roles disponibles.", "danger");
}

// --- Variables ---
$page_title = 'Editar Usuario: ' . htmlspecialchars($user_to_edit['username']);
$active_page = 'usuarios';
$validation_errors = [];
$form_data_post = []; // Guardará los datos POST si hay error de validación

// --- Procesar POST si se envió el formulario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data_post = $_POST; // Guardar datos POST para repoblar si falla validación

    // Validar que el ID enviado en POST coincide con el de la URL
    $posted_user_id = (int)($_POST['user_id'] ?? 0);
    if ($posted_user_id !== $user_id_to_edit) {
        mensaje_sesion("Error de inconsistencia de datos al editar.", "danger");
        redirigir_a('usuarios_ver.php');
    }

    // Recuperar datos del formulario
    $nombre     = trim($_POST['nombre'] ?? '');
    $apellido   = trim($_POST['apellido'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $id_rol_seleccionado = $_POST['id_rol'] ?? ''; // Se espera ID de rol
    $activo     = isset($_POST['activo']) ? 1 : 0; // Obtener estado activo del radio button
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Validación ---
    if (empty($nombre))   $validation_errors['nombre'] = "El nombre es obligatorio.";
    if (empty($apellido)) $validation_errors['apellido'] = "El apellido es obligatorio.";

    $email_a_guardar = null;
    $email_original = $user_to_edit['email']; // Email actual del usuario
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors['email'] = "El formato del email no es válido.";
        } elseif ($email !== $email_original) { // Validar unicidad solo si cambió
            try {
                global $db;
                $sql_check = "SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1";
                $stmt_check = $db->prepare($sql_check);
                $stmt_check->execute([':email' => $email, ':id' => $user_id_to_edit]);
                if ($stmt_check->fetch()) {
                    $validation_errors['email'] = "Este email ya está registrado por otro usuario.";
                } else {
                    $email_a_guardar = $email;
                }
            } catch (\PDOException $e) {
                mensaje_sesion("Error de base de datos al verificar el email.", "danger");
                error_log("Error verificando email en usuarios_editar.php: " . $e->getMessage());
            }
        } else {
            $email_a_guardar = $email; // No cambió
        }
    } // Fin validación email no vacío

    // Validar Rol Seleccionado
    if (empty($id_rol_seleccionado)) {
        $validation_errors['id_rol'] = "Debes seleccionar un rol.";
    } else {
        $rol_valido = false;
        foreach ($lista_roles_bd as $rol_bd) {
            if ($rol_bd['id'] == $id_rol_seleccionado) {
                $rol_valido = true;
                break;
            }
        }
        if (!$rol_valido) {
            $validation_errors['id_rol'] = "El rol seleccionado no es válido.";
        }
    }

    // Validar contraseña SÓLO si se ingresó una nueva
    $hashed_new_password = null;
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $validation_errors['new_password'] = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif ($new_password !== $confirm_password) {
            if (!isset($validation_errors['new_password'])) {
                $validation_errors['confirm_password'] = "Las contraseñas nuevas no coinciden.";
            }
        } else {
            // Contraseña nueva válida, hashear
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($hashed_new_password === false) {
                mensaje_sesion("Error crítico al procesar la nueva contraseña.", "danger");
                error_log("Error password_hash en usuarios_editar.php para user ID: " . $user_id_to_edit);
                $validation_errors['new_password'] = "Error interno al procesar contraseña.";
            }
        }
    }

    // --- Si no hay errores de validación, actualizar usuario ---
    if (empty($validation_errors)) {

        $update_data = [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'email'    => $email_a_guardar, // Puede ser null
            'id_rol'   => (int)$id_rol_seleccionado, // Guardar ID de rol
            'activo'   => $activo
        ];

        // Añadir contraseña al array de actualización SÓLO si se proporcionó una nueva válida
        if ($hashed_new_password !== null) {
            $update_data['password'] = $hashed_new_password;
        }

        if (actualizar_usuario($user_id_to_edit, $update_data)) { // Usa la función que espera id_rol
            mensaje_sesion("Usuario '" . htmlspecialchars($user_to_edit['username']) . "' actualizado con éxito.", "success");
            redirigir_a('usuarios_ver.php'); // Volver a la lista
        } else {
            mensaje_sesion("Error al actualizar el usuario en la base de datos.", "danger");
            redirigir_a('usuarios_editar.php?id=' . $user_id_to_edit); // Quedarse aquí con mensaje de error
        }
        exit;
    } else {
        mensaje_sesion("Por favor, corrige los errores indicados en el formulario.", "warning");
        // No redirigir, dejar que la página se recargue mostrando errores y datos POST
    }
} // Fin POST


// --- Preparar datos para mostrar en el formulario ---
// Si hubo error POST, usa datos del POST ($form_data_post), si no, usa datos de BD ($user_to_edit)
$display_data = !empty($validation_errors) ? $form_data_post : $user_to_edit;
// Asegurar que id_rol esté presente para el select, tomando de $user_to_edit si no vino en POST (o si es carga inicial)
if (!isset($display_data['id_rol'])) {
    $display_data['id_rol'] = $user_to_edit['id_rol'] ?? null; // Usar el ID de rol original
}
// Asegurar que 'activo' esté presente para los radio buttons
if (!isset($display_data['activo'])) {
    $display_data['activo'] = $user_to_edit['activo'] ?? 1; // Default a activo si falta
}


// --- Incluir Cabecera ---
include_once('layouts/header.php');
?>

<?php include_once('layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3"><strong><?php echo $page_title; ?></strong></h1>
                <a href="usuarios_ver.php" class="btn btn-secondary">
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
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Modificar Detalles del Usuario</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="usuarios_editar.php?id=<?php echo $user_id_to_edit; ?>" novalidate>
                                <input type="hidden" name="user_id" value="<?php echo (int)$user_id_to_edit; ?>">

                                <?php // Username (Readonly) 
                                ?>
                                <div class="mb-3">
                                    <label class="form-label" for="username">Nombre de Usuario</label>
                                    <input type="text" class="form-control" id="username"
                                        value="<?php echo htmlspecialchars($user_to_edit['username']); // Siempre mostrar el original 
                                                ?>" readonly>
                                    <small class="form-text text-muted">El nombre de usuario no se puede cambiar.</small>
                                </div>

                                <?php // Nombre y Apellido 
                                ?>
                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="nombre" class="form-control <?php echo isset($validation_errors['nombre']) ? 'is-invalid' : ''; ?>"
                                            id="nombre" value="<?php echo htmlspecialchars($display_data['nombre'] ?? ''); ?>" required>
                                        <?php if (isset($validation_errors['nombre'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['nombre']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                                        <input type="text" name="apellido" class="form-control <?php echo isset($validation_errors['apellido']) ? 'is-invalid' : ''; ?>"
                                            id="apellido" value="<?php echo htmlspecialchars($display_data['apellido'] ?? ''); ?>" required>
                                        <?php if (isset($validation_errors['apellido'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['apellido']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php // Email 
                                ?>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" value="<?php echo htmlspecialchars($display_data['email'] ?? ''); ?>">
                                    <?php if (isset($validation_errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php // Rol (Desde BD) 
                                ?>
                                <div class="mb-3">
                                    <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                                    <select name="id_rol" id="id_rol" class="form-select <?php echo isset($validation_errors['id_rol']) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">Selecciona un rol...</option>
                                        <?php foreach ($lista_roles_bd as $rol_bd): ?>
                                            <?php // Comprobar si este rol es el actual del usuario 
                                            ?>
                                            <?php $selected = (isset($display_data['id_rol']) && $display_data['id_rol'] == $rol_bd['id']); ?>
                                            <option value="<?php echo (int)$rol_bd['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rol_bd['nombre_rol']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($validation_errors['id_rol'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['id_rol']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php // Estado Activo/Inactivo 
                                ?>
                                <div class="mb-3">
                                    <label class="form-label d-block">Estado <span class="text-danger">*</span></label>
                                    <?php // Obtener el estado actual para marcar el radio button correcto 
                                    ?>
                                    <?php $current_status = $display_data['activo'] ?? 1; ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="activo" id="activo_si" value="1" <?php echo ($current_status == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="activo_si">Activo</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <?php // No permitir desactivar al usuario con ID 1 si es el superadmin? Podría añadirse lógica aquí 
                                        ?>
                                        <input class="form-check-input" type="radio" name="activo" id="activo_no" value="0" <?php echo ($current_status == 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="activo_no">Inactivo</label>
                                    </div>
                                </div>

                                <hr>
                                <h5 class="card-title mb-3">Cambiar Contraseña (Opcional)</h5>
                                <p class="text-muted">Deja estos campos en blanco si no deseas cambiar la contraseña.</p>

                                <?php // Contraseñas (Opcionales) 
                                ?>
                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label for="new_password" class="form-label">Nueva Contraseña</label>
                                        <input type="password" class="form-control <?php echo (isset($validation_errors['new_password']) || isset($validation_errors['confirm_password'])) ? 'is-invalid' : ''; ?>"
                                            id="new_password" name="new_password" minlength="8">
                                        <?php if (isset($validation_errors['new_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['new_password']; ?></div>
                                        <?php else: ?>
                                            <small class="form-text text-muted">Mínimo 8 caracteres (si se cambia).</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                                        <input type="password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                            id="confirm_password" name="confirm_password">
                                        <?php if (isset($validation_errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php // Botones Finales 
                                ?>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                    <a href="usuarios_ver.php" class="btn btn-secondary">Cancelar</a>
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