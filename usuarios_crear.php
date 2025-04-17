<?php
// usuarios_crear.php - Formulario y procesamiento para añadir nuevo usuario

require_once('includes/load.php');
requerir_login(); // Asegura que el usuario esté logueado

// Verificación de Rol: Solo Admin puede crear usuarios
if (!function_exists('tiene_rol') || !tiene_rol('Admin')) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para crear nuevos usuarios.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('index.php');
    exit;
}

// --- Obtener Roles Disponibles ---
$lista_roles_bd = []; // Inicializar
if (function_exists('buscar_todos_los_roles')) {
    $lista_roles_bd = buscar_todos_los_roles(); // Llama a la función de sql.php
    // Filtrar roles si un Admin no puede crear otro Admin
    // $lista_roles_bd = array_filter($lista_roles_bd, function($rol) { return $rol['nombre_rol'] !== 'Admin'; });
} else {
    error_log("Error: Función buscar_todos_los_roles() no encontrada.");
    mensaje_sesion("Error al cargar los roles disponibles.", "danger");
    // Considerar redirigir o mostrar error fatal
}

// --- Variables ---
$page_title = 'Añadir Nuevo Usuario';
$active_page = 'usuarios'; // Para marcar sección activa en sidebar
$active_subpage = 'crear_usuario'; // Para resaltar el submenú específico
$validation_errors = [];
$form_data = []; // Para repoblar en caso de error

// --- Procesar POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = $_POST; // Guardar datos para repoblar

    $username   = trim($_POST['username'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $apellido   = trim($_POST['apellido'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $id_rol_seleccionado = $_POST['id_rol'] ?? ''; // Se espera el ID del rol
    // Establecer explícitamente como INACTIVO por defecto al crear
    $activo = 0;

    // --- Validación ---
    if (empty($username)) $validation_errors['username'] = "El nombre de usuario es obligatorio.";
    elseif (buscar_usuario_por_identificador($username)) $validation_errors['username'] = "Este nombre de usuario ya está en uso.";

    if (empty($nombre))   $validation_errors['nombre'] = "El nombre es obligatorio.";
    if (empty($apellido)) $validation_errors['apellido'] = "El apellido es obligatorio.";

    $email_a_guardar = null;
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors['email'] = "El formato del email no es válido.";
        } elseif (buscar_usuario_por_identificador($email)) {
            $validation_errors['email'] = "Este email ya está registrado.";
        } else {
            $email_a_guardar = $email;
        }
    }

    if (empty($password)) {
        $validation_errors['password'] = "La contraseña es obligatoria.";
    } elseif (strlen($password) < 8) {
        $validation_errors['password'] = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $password_confirm) {
        if (!isset($validation_errors['password'])) {
            $validation_errors['password_confirm'] = "Las contraseñas no coinciden.";
        }
    }

    // Validar Rol Seleccionado
    if (empty($id_rol_seleccionado)) {
        $validation_errors['id_rol'] = "Debes seleccionar un rol.";
    } else {
        $rol_valido = false;
        foreach ($lista_roles_bd as $rol_bd) {
            // Comparación no estricta por si ID viene como string de POST
            if ($rol_bd['id'] == $id_rol_seleccionado) {
                $rol_valido = true;
                break;
            }
        }
        if (!$rol_valido) {
            $validation_errors['id_rol'] = "El rol seleccionado no es válido.";
        }
    }

    // --- Si no hay errores, crear usuario ---
    if (empty($validation_errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
            mensaje_sesion("Error crítico al hashear contraseña.", "danger");
            error_log("Error password_hash en usuarios_crear.php");
        } else {
            $new_user_data = [
                'username' => $username,
                'password' => $hashed_password,
                'email'    => $email_a_guardar,
                'nombre'   => $nombre,
                'apellido' => $apellido,
                'id_rol'   => (int)$id_rol_seleccionado, // Guardar ID numérico
                'activo'   => $activo // Se guarda como 0 (inactivo)
            ];

            $resultado = crear_usuario($new_user_data); // Usa la función que espera id_rol
            if ($resultado !== false) {
                mensaje_sesion("Usuario '" . htmlspecialchars($username) . "' creado con éxito (inactivo). Debes activarlo desde la lista.", "success");
                redirigir_a('usuarios_ver.php'); // Redirigir a la lista
            } else {
                mensaje_sesion("Error al crear el usuario en la base de datos.", "danger");
                // No redirigir, dejar que se muestre el form con errores
            }
        }
    } else {
        mensaje_sesion("Por favor, corrige los errores en el formulario.", "warning");
        // No redirigir
    }
} // Fin POST

include_once('layouts/header.php');
?>

<?php include_once('layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>
                <a href="usuarios_ver.php" class="btn btn-secondary">
                    <i class="align-middle" data-feather="arrow-left"></i> <span class="align-middle">Volver a la Lista</span>
                </a>
            </div>

            <div class="px-3 px-lg-4 mb-3">
                <?php
                // Mostrar mensajes flash si la función existe
                if (function_exists('mostrar_mensaje_flash')) {
                    echo mostrar_mensaje_flash();
                }
                ?>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Detalles del Nuevo Usuario</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="usuarios_crear.php" novalidate>

                                <?php // Campo Username 
                                ?>
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($validation_errors['username']) ? 'is-invalid' : ''; ?>"
                                        id="username" name="username" required
                                        value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>">
                                    <?php if (isset($validation_errors['username'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['username']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php // Nombre y Apellido 
                                ?>
                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="nombre" class="form-control <?php echo isset($validation_errors['nombre']) ? 'is-invalid' : ''; ?>"
                                            id="nombre" value="<?php echo htmlspecialchars($form_data['nombre'] ?? ''); ?>" required>
                                        <?php if (isset($validation_errors['nombre'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['nombre']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                                        <input type="text" name="apellido" class="form-control <?php echo isset($validation_errors['apellido']) ? 'is-invalid' : ''; ?>"
                                            id="apellido" value="<?php echo htmlspecialchars($form_data['apellido'] ?? ''); ?>" required>
                                        <?php if (isset($validation_errors['apellido'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['apellido']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php // Email 
                                ?>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email (Opcional)</label>
                                    <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
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
                                            <option value="<?php echo (int)$rol_bd['id']; // Enviar ID 
                                                            ?>"
                                                <?php echo (isset($form_data['id_rol']) && $form_data['id_rol'] == $rol_bd['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rol_bd['nombre_rol']); // Mostrar nombre 
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($validation_errors['id_rol'])): ?>
                                        <div class="invalid-feedback"><?php echo $validation_errors['id_rol']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <?php // Contraseñas 
                                ?>
                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control <?php echo (isset($validation_errors['password']) || isset($validation_errors['password_confirm'])) ? 'is-invalid' : ''; ?>"
                                            id="password" name="password" required minlength="8">
                                        <?php if (isset($validation_errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['password']; ?></div>
                                        <?php else: ?>
                                            <small class="form-text text-muted">Mínimo 8 caracteres.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label for="password_confirm" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control <?php echo isset($validation_errors['password_confirm']) ? 'is-invalid' : ''; ?>"
                                            id="password_confirm" name="password_confirm" required>
                                        <?php if (isset($validation_errors['password_confirm'])): ?>
                                            <div class="invalid-feedback"><?php echo $validation_errors['password_confirm']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php // No incluimos checkbox de activo, se crea inactivo por defecto 
                                ?>

                                <?php // Botones 
                                ?>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
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