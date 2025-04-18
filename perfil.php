<?php

// Cargar configuración, sesión, BD, funciones, etc.
require_once('includes/load.php');

$page_title = 'Mi Perfil';
$active_page = 'perfil';

// Requerir que el usuario esté logueado para ver esta página
if (function_exists('requerir_login')) {
    requerir_login();
} else {
    die("Error: Sistema de autenticación no disponible."); // Fallback
}


// --- Obtener datos iniciales y preparar variables ---
$datos_sesion = obtener_datos_sesion(); // Función de session.php
$user_id_actual = $datos_sesion['id'] ?? null;
$usuario = null;                // Para guardar los datos completos del usuario
$validation_errors = [];      // Para errores de validación de formularios
$form_action = $_POST['form_action'] ?? null; // Identifica qué formulario se envió

// Si no podemos identificar al usuario logueado, redirigir
if (!$user_id_actual) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al identificar al usuario.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}

// Obtener datos actuales del usuario ANTES de procesar cualquier POST
// Se necesita para obtener el hash de la contraseña actual y el nombre de la foto actual
$usuario = buscar_usuario_por_id($user_id_actual); // Función de sql.php
if (!$usuario) {
    // Si no se encuentra el usuario en la BD (raro si está logueado)
    if (function_exists('mensaje_sesion')) mensaje_sesion("No se pudieron cargar los datos del perfil desde la base de datos.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}
// Guardar el hash actual para la validación del cambio de contraseña
$current_password_hash = $usuario['password'] ?? null;
// Guardar nombre de foto actual para posible borrado
$current_photo_filename = $usuario['foto_perfil'] ?? null;


// --- Procesar Formularios (si se envió la página vía POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $should_redirect = true; // Asumimos redirección, la cancelamos si hay error de validación

    // --- A. Procesar Actualización de Información ---
    if ($form_action === 'update_info') {

        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $email_original = $usuario['email']; // Email actual del usuario

        // Validaciones para la info
        if (empty($nombre))   $validation_errors['nombre'] = "El nombre es obligatorio.";
        if (empty($apellido)) $validation_errors['apellido'] = "El apellido es obligatorio.";

        $email_a_guardar = null; // Valor por defecto
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validation_errors['email'] = "El formato del email no es válido.";
            } elseif ($email !== $email_original) {
                // Verificar duplicado solo si el email cambió
                try {
                    global $db;
                    $sql_check = "SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1";
                    $stmt_check = $db->prepare($sql_check);
                    $stmt_check->execute([':email' => $email, ':id' => $user_id_actual]);
                    if ($stmt_check->fetch()) {
                        $validation_errors['email'] = "Este email ya está registrado por otro usuario.";
                    } else {
                        $email_a_guardar = $email; // Email válido
                    }
                } catch (\PDOException $e) {
                    if (function_exists('mensaje_sesion')) mensaje_sesion("Error de base de datos al verificar el email.", "danger");
                    error_log("Error verificando email en perfil.php: " . $e->getMessage());
                    $should_redirect = false; // Evitar seguir si hay error grave
                }
            } else {
                $email_a_guardar = $email; // Email no cambió
            }
        } // Fin validación email no vacío

        // Si no hubo errores y no falló la verificación de email
        if (empty($validation_errors) && $should_redirect) {
            $update_data = [
                'nombre'   => $nombre,
                'apellido' => $apellido,
                'email'    => $email_a_guardar
            ];

            if (actualizar_usuario($user_id_actual, $update_data)) {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Perfil actualizado con éxito.", "success");
                // Actualizar nombre en sesión
                $_SESSION['nombre_completo'] = trim($nombre . ' ' . $apellido);
            } else {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Error al actualizar el perfil en la base de datos.", "danger");
            }
        } elseif ($should_redirect) { // Hubo errores de validación
            if (function_exists('mensaje_sesion')) mensaje_sesion("Por favor, corrige los errores en el formulario de información.", "warning");
            $should_redirect = false; // No redirigir
        }
    } // --- Fin 'update_info' ---

    // --- B. Procesar Subida de Foto ---
    elseif ($form_action === 'update_picture') {

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['profile_pic']['tmp_name'];
            $file_name     = basename($_FILES['profile_pic']['name']);
            $file_size     = $_FILES['profile_pic']['size'];
            $file_ext      = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $actual_mime_type = mime_content_type($file_tmp_path);

            // Validación
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 2 * 1024 * 1024; // 2 MB

            if (!in_array($actual_mime_type, $allowed_mime_types) || !in_array($file_ext, $allowed_extensions)) {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Error: Tipo de archivo no permitido. Solo JPG, PNG o GIF.", "danger");
            } elseif ($file_size > $max_file_size) {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Error: El archivo es demasiado grande (máximo 2MB).", "danger");
            } else {
                // Validación OK, procesar
                $upload_dir = dirname(__FILE__) . '/uploads/users/';
                if (!is_dir($upload_dir)) {
                    if (!@mkdir($upload_dir, 0755, true)) { // Intentar crear recursivamente
                        if (function_exists('mensaje_sesion')) mensaje_sesion("Error crítico: No se pudo crear el directorio de subida.", "danger");
                        error_log("Error mkdir() para " . $upload_dir);
                        goto end_post_processing; // Saltar al final del POST
                    }
                } elseif (!is_writable($upload_dir)) {
                    if (function_exists('mensaje_sesion')) mensaje_sesion("Error crítico: Sin permisos de escritura en el directorio de subida.", "danger");
                    error_log("Sin permisos de escritura para " . $upload_dir);
                    goto end_post_processing; // Saltar al final del POST
                }

                $new_filename = "user_" . $user_id_actual . "_" . time() . "." . $file_ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp_path, $target_path)) {
                    // Archivo movido, actualizar BD
                    $old_filename = $current_photo_filename; // Usar el valor cargado al inicio

                    if (actualizar_usuario($user_id_actual, ['foto_perfil' => $new_filename])) {
                        if (function_exists('mensaje_sesion')) mensaje_sesion("Foto de perfil actualizada con éxito.", "success");
                        $_SESSION['foto_perfil'] = $new_filename; // Actualizar sesión
                        // Borrar foto anterior
                        if ($old_filename && $old_filename !== 'default.png' && file_exists($upload_dir . $old_filename)) {
                            @unlink($upload_dir . $old_filename);
                        }
                    } else {
                        if (function_exists('mensaje_sesion')) mensaje_sesion("Foto subida, pero hubo un error al guardar la referencia en la base de datos.", "danger");
                        @unlink($target_path); // Borrar archivo subido si falla BD
                    }
                } else {
                    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al guardar el archivo subido. Verifica permisos.", "danger");
                    error_log("Error move_uploaded_file en perfil.php para " . $target_path);
                }
            }
        } elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] != UPLOAD_ERR_NO_FILE) {
            if (function_exists('mensaje_sesion')) mensaje_sesion("Error durante la subida del archivo. Código: " . $_FILES['profile_pic']['error'], "danger");
        } else {
            if (function_exists('mensaje_sesion')) mensaje_sesion("Por favor, selecciona un archivo de imagen para subir.", "warning");
        }
    } // --- Fin 'update_picture' ---

    // --- C. Procesar Cambio de Contraseña ---
    elseif ($form_action === 'update_password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validaciones
        if (empty($current_password)) {
            $validation_errors['current_password'] = "Debes ingresar tu contraseña actual.";
        } elseif (!$current_password_hash || !password_verify($current_password, $current_password_hash)) {
            // Verificar contra el hash cargado al inicio
            $validation_errors['current_password'] = "La contraseña actual no es correcta.";
        }

        if (empty($new_password)) {
            $validation_errors['new_password'] = "La nueva contraseña es obligatoria.";
        } elseif (strlen($new_password) < 8) {
            $validation_errors['new_password'] = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif ($new_password !== $confirm_password) {
            if (!isset($validation_errors['new_password'])) {
                $validation_errors['confirm_password'] = "Las contraseñas nuevas no coinciden.";
            }
        }

        // Si no hay errores, actualizar
        if (empty($validation_errors)) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($hashed_new_password === false) {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Error crítico al procesar la nueva contraseña.", "danger");
                error_log("Error password_hash en perfil.php para user ID: " . $user_id_actual);
            } else {
                $update_data = ['password' => $hashed_new_password];
                if (actualizar_usuario($user_id_actual, $update_data)) {
                    if (function_exists('mensaje_sesion')) mensaje_sesion("Contraseña actualizada con éxito.", "success");
                } else {
                    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al guardar la nueva contraseña en la base de datos.", "danger");
                }
            }
        } else {
            // Hubo errores de validación
            if (function_exists('mensaje_sesion')) mensaje_sesion("Por favor, corrige los errores en el formulario de cambio de contraseña.", "warning");
            $should_redirect = false; // No redirigir para mostrar errores
        }
    } // --- Fin 'update_password' ---

    // Etiqueta para saltos goto en caso de errores críticos
    end_post_processing:

    // --- Redirección final (si no hubo errores de validación) ---
    if ($should_redirect) {
        if (function_exists('redirigir_a')) redirigir_a('perfil.php');
        exit;
    }
} // --- Fin POST ---


// --- Obtener datos frescos si NO hubo redirección (por errores de validación) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$should_redirect) {
    $usuario = buscar_usuario_por_id($user_id_actual); // Recargar datos para mostrar
    if (!$usuario) {
        if (function_exists('mensaje_sesion')) mensaje_sesion("Error fatal al recargar datos del perfil tras error.", "danger");
        if (function_exists('redirigir_a')) redirigir_a('index.php');
        exit;
    }
}


// --- Calcular URL de la foto de perfil a mostrar ---
$nombre_archivo_foto = $usuario['foto_perfil'] ?? null;
$url_foto_perfil = '';
$url_foto_default = (defined('BASE_URL') ? BASE_URL : '/') . 'uploads/users/default.png';
if (!empty($nombre_archivo_foto)) {
    $url_foto_especifica = (defined('BASE_URL') ? BASE_URL : '/') . 'uploads/users/' . htmlspecialchars($nombre_archivo_foto);
    $ruta_fisica_foto = dirname(__FILE__) . '/uploads/users/' . $nombre_archivo_foto;
    $url_foto_perfil = file_exists($ruta_fisica_foto) ? $url_foto_especifica : $url_foto_default;
} else {
    $url_foto_perfil = $url_foto_default;
}

// --- Variables para plantilla ---
$page_title = 'Mi Perfil';
$active_page = 'perfil';

// --- Incluir Cabecera HTML ---
include_once('layouts/header.php');
?>

<?php include_once('layouts/sidebar.php'); // Incluir Sidebar 
?>

<div class="main"> <?php // Contenedor principal 
                    ?>
    <?php include_once('layouts/navbar.php'); // Incluir Navbar 
    ?>

    <main class="content"> <?php // Contenido específico de la página 
                            ?>
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <?php // Mostrar mensajes flash (éxito, error, advertencia) 
            ?>


            <div class="row"> <?php // Fila principal 
                                ?>
                <div class="col-md-4 col-xl-3">
                    <?php // --- Columna Izquierda: Foto de Perfil --- 
                    ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Foto de Perfil</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php // Mostrar imagen 
                            ?>
                            <img src="<?php echo $url_foto_perfil; ?>"
                                alt="Foto de <?php echo htmlspecialchars($usuario['nombre'] ?? ''); ?>"
                                class="img-fluid rounded-circle mb-2 border" width="128" height="128" />

                            <?php // Mostrar Nombre y Rol 
                            ?>
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h5>
                            <div class="text-muted mb-2"><?php echo htmlspecialchars($usuario['nombre_rol'] ?? 'Rol no definido'); ?></div>

                            <div>
                                <?php // --- Formulario para Subir Nueva Foto --- 
                                ?>
                                <form method="POST" action="perfil.php" enctype="multipart/form-data">
                                    <input type="hidden" name="form_action" value="update_picture">
                                    <div class="mb-2">
                                        <label for="profile_pic_upload" class="form-label visually-hidden">Seleccionar foto</label>
                                        <input type="file" class="form-control form-control-sm" name="profile_pic" id="profile_pic_upload" accept="image/png, image/jpeg, image/gif" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Actualizar Foto</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div> <?php // Fin col foto 
                        ?>

                <div class="col-md-8 col-xl-9">
                    <?php // --- Columna Derecha: Información y Contraseña --- 
                    ?>

                    <?php // --- Card: Información del Usuario --- 
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Información del Usuario</h5>
                        </div>
                        <div class="card-body">
                            <?php // --- Formulario para Editar Información --- 
                            ?>
                            <form method="POST" action="perfil.php" novalidate>
                                <input type="hidden" name="form_action" value="update_info">

                                <div class="mb-3">
                                    <label class="form-label" for="info_username">Nombre de Usuario</label>
                                    <input type="text" class="form-control" id="info_username" value="<?php echo htmlspecialchars($usuario['username']); ?>" readonly>
                                    <small class="form-text text-muted">El nombre de usuario no se puede cambiar.</small>
                                </div>

                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="info_nombre">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="nombre" class="form-control <?php echo isset($validation_errors['nombre']) ? 'is-invalid' : ''; ?>"
                                            id="info_nombre" value="<?php echo htmlspecialchars(isset($_POST['nombre']) && $form_action === 'update_info' ? $_POST['nombre'] : $usuario['nombre']); ?>" required>
                                        <?php if (isset($validation_errors['nombre'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['nombre']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="info_apellido">Apellido <span class="text-danger">*</span></label>
                                        <input type="text" name="apellido" class="form-control <?php echo isset($validation_errors['apellido']) ? 'is-invalid' : ''; ?>"
                                            id="info_apellido" value="<?php echo htmlspecialchars(isset($_POST['apellido']) && $form_action === 'update_info' ? $_POST['apellido'] : $usuario['apellido']); ?>" required>
                                        <?php if (isset($validation_errors['apellido'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['apellido']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="info_email">Email</label>
                                    <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="info_email" value="<?php echo htmlspecialchars(isset($_POST['email']) && $form_action === 'update_info' ? $_POST['email'] : ($usuario['email'] ?? '')); ?>">
                                    <?php if (isset($validation_errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="info_fechaCreacion">Miembro desde</label>
                                    <input type="text" class="form-control" id="info_fechaCreacion"
                                        value="<?php echo htmlspecialchars(function_exists('formatear_fecha') ? formatear_fecha($usuario['fecha_creacion'], 'd/m/Y H:i') : $usuario['fecha_creacion']); ?>" readonly>
                                </div>

                                <button type="submit" class="btn btn-primary">Guardar Cambios Info</button>
                            </form>
                        </div>
                    </div> <?php // Fin card info 
                            ?>

                    <?php // --- Card: Cambio de Contraseña --- 
                    ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Cambiar Contraseña</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="perfil.php" novalidate>
                                <input type="hidden" name="form_action" value="update_password">

                                <div class="mb-3">
                                    <label class="form-label" for="current_password">Contraseña Actual <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control <?php echo isset($validation_errors['current_password']) ? 'is-invalid' : ''; ?>"
                                        name="current_password" id="current_password" required>
                                    <?php if (isset($validation_errors['current_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['current_password']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="new_password">Nueva Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control <?php echo (isset($validation_errors['new_password']) || isset($validation_errors['confirm_password'])) ? 'is-invalid' : ''; ?>"
                                        name="new_password" id="new_password" required minlength="8">
                                    <?php if (isset($validation_errors['new_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['new_password']; ?></div>
                                    <?php else: ?>
                                        <small class="form-text text-muted">Mínimo 8 caracteres.</small>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="confirm_password">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                        name="confirm_password" id="confirm_password" required>
                                    <?php if (isset($validation_errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                            </form>
                        </div>
                    </div> <?php // Fin card contraseña 
                            ?>

                </div> <?php // Fin col-md-8 
                        ?>
            </div> <?php // Fin row principal 
                    ?>

        </div> <?php // Fin .container-fluid 
                ?>
    </main>

    <?php include_once('layouts/footer.php'); // Incluir Footer 
    ?>
</div> <?php // Fin .main 
        ?>

<?php // Fin del archivo perfil.php 
?>