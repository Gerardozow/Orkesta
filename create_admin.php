<?php
// create_admin.php (Versión Navegador)
// Script para crear el usuario administrador inicial usando un formulario web.
// ¡NO USAR EN PRODUCCIÓN! Eliminar o proteger después de usar.

require_once('includes/load.php'); // Carga BD, funciones, etc.

// --- Variables para mensajes y datos del formulario ---
$page_title = 'Crear Usuario Administrador';
$error_msg = null;           // Mensaje de error general o de creación
$success_msg = null;         // Mensaje de éxito
$validation_errors = [];   // Array para errores de validación específicos
$form_data = [];           // Para repoblar el formulario en caso de error

// --- Procesar el formulario si se envió (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recuperar datos del POST y guardarlos para posible repoblación
    $form_data = $_POST;
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $apellido   = trim($_POST['apellido'] ?? '');
    $password   = $_POST['password'] ?? ''; // No trimear contraseña
    $password_confirm = $_POST['password_confirm'] ?? '';

    // --- Validación ---
    if (empty($username)) {
        $validation_errors['username'] = "El nombre de usuario es obligatorio.";
    }
    // TODO: Podrías añadir validación para formato/longitud de username aquí

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = "El formato del email no es válido.";
    } elseif (empty($email)) {
        $email = null; // Guardar como NULL si está vacío en el formulario
    }

    if (empty($nombre)) {
        $validation_errors['nombre'] = "El nombre es obligatorio.";
    }
    if (empty($apellido)) {
        $validation_errors['apellido'] = "El apellido es obligatorio.";
    }

    if (empty($password)) {
        $validation_errors['password'] = "La contraseña es obligatoria.";
    } elseif (strlen($password) < 8) { // Ejemplo: Mínimo 8 caracteres
        $validation_errors['password'] = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $password_confirm) {
        // Solo marcar error en confirmación si la contraseña principal no está vacía y tiene longitud mínima
        if (!isset($validation_errors['password'])) {
            $validation_errors['password_confirm'] = "Las contraseñas no coinciden.";
        }
    }

    // --- Si no hay errores de validación, intentar crear usuario ---
    if (empty($validation_errors)) {

        // Hashear contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($hashed_password === false) {
            // Error muy improbable si PHP está bien configurado
            $error_msg = "Error fatal: No se pudo hashear la contraseña.";
        } else {
            // Preparar datos para la función crear_usuario() de sql.php
            $admin_data = [
                'username' => $username,
                'password' => $hashed_password,
                'email'    => $email, // Será null si se dejó vacío
                'nombre'   => $nombre,
                'apellido' => $apellido,
                'rol'      => 'Admin', // Rol fijo para este script
                'activo'   => 1        // Activo por defecto
                // 'foto_perfil' => null // Podrías añadirlo si es necesario
            ];

            // Llamar a la función de creación 
            if (function_exists('crear_usuario')) {
                $resultado = crear_usuario($admin_data);

                if ($resultado !== false) {
                    // Éxito al crear
                    $success_msg = "¡Usuario Administrador '" . htmlspecialchars($username) . "' creado con éxito! ID: " . $resultado;
                    // Limpiar datos del formulario para no repoblar tras éxito
                    $form_data = [];
                } else {
                    // Error probable: usuario o email duplicado (constraint UNIQUE en BD)
                    $error_msg = "Error al crear el administrador. ¿El nombre de usuario o el email ya existen en la base de datos?";
                }
            } else {
                // Error si la función no existe (problema de carga/inclusión)
                $error_msg = "Error interno: La función crear_usuario() no está disponible.";
            }
        }
    } else {
        // Hubo errores de validación, preparar mensaje general
        $error_msg = "Por favor, corrige los errores indicados en el formulario.";
    }
} // Fin del procesamiento POST

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?> - WorkOrders Setup</title>
    <?php // Favicon y CSS principal 
    ?>
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/img/icons/icon-48x48.png" />
    <link href="<?php echo BASE_URL; ?>assets/css/app.css" rel="stylesheet">
    <?php // Fuentes 
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f5f7fb;
        }

        .is-invalid+.invalid-feedback {
            display: block;
        }

        /* Asegura que se muestre el error */
    </style>
</head>

<body>
    <main class="d-flex w-100">
        <div class="container d-flex flex-column">
            <?php // Centrado vertical y horizontal usando clases de Bootstrap 
            ?>
            <div class="row vh-100">
                <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
                    <div class="d-table-cell align-middle">

                        <div class="text-center mt-4 mb-4">
                            <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                            <p class="lead">Crea la cuenta inicial de administrador para el sistema.</p>
                            <p class="text-danger"><strong>¡Este script es solo para desarrollo!</strong></p>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="m-sm-3">

                                    <?php // --- Mostrar Mensajes Globales --- 
                                    ?>
                                    <?php // Mensaje de éxito 
                                    ?>
                                    <?php if ($success_msg): ?>
                                        <div class="alert alert-success" role="alert">
                                            <?php echo htmlspecialchars($success_msg); ?>
                                            <hr>
                                            <a href="login.php" class="btn btn-sm btn-primary">Ir a Iniciar Sesión</a>
                                        </div>
                                    <?php endif; ?>
                                    <?php // Mensaje de error general (si no hay errores de validación) 
                                    ?>
                                    <?php if ($error_msg && empty($validation_errors)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo htmlspecialchars($error_msg); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php // Mensaje de advertencia si hay errores de validación 
                                    ?>
                                    <?php if ($error_msg && !empty($validation_errors)): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <?php echo htmlspecialchars($error_msg); ?>
                                        </div>
                                    <?php endif; ?>


                                    <?php // --- Mostrar Formulario (solo si no hubo éxito en la creación) --- 
                                    ?>
                                    <?php if (!$success_msg): ?>
                                        <form method="POST" action="create_admin.php" novalidate>

                                            <?php // Campo Username 
                                            ?>
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($validation_errors['username']) ? 'is-invalid' : ''; ?>"
                                                    id="username" name="username" required
                                                    value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>">
                                                <?php // Muestra error específico si existe 
                                                ?>
                                                <?php if (isset($validation_errors['username'])): ?>
                                                    <div class="invalid-feedback"><?php echo $validation_errors['username']; ?></div>
                                                <?php endif; ?>
                                            </div>

                                            <?php // Campo Email (Opcional) 
                                            ?>
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email (Opcional)</label>
                                                <input type="email" class="form-control <?php echo isset($validation_errors['email']) ? 'is-invalid' : ''; ?>"
                                                    id="email" name="email"
                                                    value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                                                <?php if (isset($validation_errors['email'])): ?>
                                                    <div class="invalid-feedback"><?php echo $validation_errors['email']; ?></div>
                                                <?php endif; ?>
                                            </div>

                                            <?php // Campo Nombre 
                                            ?>
                                            <div class="mb-3">
                                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($validation_errors['nombre']) ? 'is-invalid' : ''; ?>"
                                                    id="nombre" name="nombre" required
                                                    value="<?php echo htmlspecialchars($form_data['nombre'] ?? ''); ?>">
                                                <?php if (isset($validation_errors['nombre'])): ?>
                                                    <div class="invalid-feedback"><?php echo $validation_errors['nombre']; ?></div>
                                                <?php endif; ?>
                                            </div>

                                            <?php // Campo Apellido 
                                            ?>
                                            <div class="mb-3">
                                                <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($validation_errors['apellido']) ? 'is-invalid' : ''; ?>"
                                                    id="apellido" name="apellido" required
                                                    value="<?php echo htmlspecialchars($form_data['apellido'] ?? ''); ?>">
                                                <?php if (isset($validation_errors['apellido'])): ?>
                                                    <div class="invalid-feedback"><?php echo $validation_errors['apellido']; ?></div>
                                                <?php endif; ?>
                                            </div>

                                            <?php // Campo Password 
                                            ?>
                                            <div class="mb-3">
                                                <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control <?php echo (isset($validation_errors['password']) || isset($validation_errors['password_confirm'])) ? 'is-invalid' : ''; ?>"
                                                    id="password" name="password" required minlength="8">
                                                <?php // Muestra error solo en el campo original 
                                                ?>
                                                <?php if (isset($validation_errors['password'])): ?>
                                                    <div class="invalid-feedback"><?php echo $validation_errors['password']; ?></div>
                                                <?php else: ?>
                                                    <small class="form-text text-muted">Mínimo 8 caracteres.</small>
                                                <?php endif; ?>
                                            </div>

                                            <?php // Campo Confirmar Password 
                                            ?>
                                            <div class="mb-3">
                                                <label for="password_confirm" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control <?php echo isset($validation_errors['password_confirm']) ? 'is-invalid' : ''; ?>"
                                                    id="password_confirm" name="password_confirm" required>
                                                <?php if (isset($validation_errors['password_confirm'])): ?>
                                                    <div class="invalid-feedback"><?php echo $validation_errors['password_confirm']; ?></div>
                                                <?php endif; ?>
                                            </div>

                                            <?php // Botón de envío 
                                            ?>
                                            <div class="d-grid gap-2 mt-3">
                                                <button type="submit" class="btn btn-lg btn-primary">Crear Administrador</button>
                                            </div>
                                        </form>
                                    <?php endif; // Fin if (!$success_msg) 
                                    ?>
                                </div>
                            </div>
                        </div> <?php // Fin card 
                                ?>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php // Realmente no se necesita app.js para este formulario simple, se puede omitir 
    ?>

</body>

</html>