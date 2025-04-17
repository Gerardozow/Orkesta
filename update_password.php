<?php
// update_password.php
// Script para actualizar la contraseña de un usuario existente.
// ¡SOLO PARA DESARROLLO LOCAL! Eliminar o proteger después de usar.

require_once('includes/load.php'); // Carga BD, funciones, etc.

// --- Variables ---
$page_title = 'Actualizar Contraseña de Usuario';
$error_msg = null;           // Mensaje de error general
$success_msg = null;         // Mensaje de éxito
$validation_errors = [];   // Errores específicos de campos
$form_data = [];           // Para repoblar username si hay error

// --- Procesar POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_data = $_POST; // Guardar datos para repoblar
    $username         = trim($_POST['username'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = null; // Para guardar el ID del usuario si lo encontramos

    // --- Validación ---
    if (empty($username)) {
        $validation_errors['username'] = "Debes ingresar el nombre de usuario a modificar.";
    } else {
        // Verificar si el usuario existe (activo o inactivo, no importa aquí)
        if (function_exists('buscar_usuario_por_identificador')) {
            // Podríamos necesitar una función que busque sin importar si está activo
            // Por ahora, asumimos que buscar_usuario_por_identificador SÓLO busca activos.
            // Vamos a usar buscar_usuario_por_id si podemos obtener el ID de otra forma,
            // o crear una función buscar_usuario_por_username_existente() en sql.php.

            // Solución simple: Busquemos por username directamente en este script.
            try {
                global $db;
                $sql_find = "SELECT id FROM usuarios WHERE username = :username LIMIT 1";
                $stmt_find = $db->prepare($sql_find);
                $stmt_find->execute([':username' => $username]);
                $user_found = $stmt_find->fetch();
                if ($user_found) {
                    $user_id = $user_found['id']; // Encontramos el ID
                } else {
                    $validation_errors['username'] = "No se encontró ningún usuario con ese nombre de usuario.";
                }
            } catch (\PDOException $e) {
                $error_msg = "Error al buscar usuario en la base de datos.";
                error_log("Error buscando usuario en update_password.php: " . $e->getMessage());
            }
        } else {
            $error_msg = "Error interno: Función de búsqueda de usuario no disponible.";
        }
    }

    if (empty($new_password)) {
        $validation_errors['new_password'] = "La nueva contraseña es obligatoria.";
    } elseif (strlen($new_password) < 8) {
        $validation_errors['new_password'] = "La nueva contraseña debe tener al menos 8 caracteres.";
    } elseif ($new_password !== $confirm_password) {
        if (!isset($validation_errors['new_password'])) { // Solo mostrar si la pw principal está bien
            $validation_errors['confirm_password'] = "Las contraseñas no coinciden.";
        }
    }

    // --- Si no hay errores y encontramos al usuario, actualizar ---
    if (empty($validation_errors) && $user_id !== null) {

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        if ($hashed_password === false) {
            $error_msg = "Error fatal al hashear la nueva contraseña.";
        } else {
            $update_data = ['password' => $hashed_password];

            if (function_exists('actualizar_usuario')) {
                if (actualizar_usuario($user_id, $update_data)) {
                    $success_msg = "¡Contraseña actualizada con éxito para el usuario '" . htmlspecialchars($username) . "'!";
                    $form_data = []; // Limpiar para no repoblar
                } else {
                    $error_msg = "Error al guardar la nueva contraseña en la base de datos.";
                }
            } else {
                $error_msg = "Error interno: Función de actualización no disponible.";
            }
        }
    } elseif (empty($error_msg)) { // Si no hay error general pero sí de validación
        $error_msg = "Por favor, corrige los errores indicados.";
    }
} // Fin POST

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($page_title); ?> - WorkOrders Setup</title>
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/img/icons/icon-48x48.png" />
    <link href="<?php echo BASE_URL; ?>assets/css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f5f7fb;
            padding-top: 3rem;
        }

        .validation-error {
            color: #dc3545;
            font-size: 0.875em;
        }

        .is-invalid+.invalid-feedback {
            display: block;
        }
    </style>
</head>

<body>
    <main class="d-flex w-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                    <div class="text-center mt-4 mb-4">
                        <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                        <p class="lead">Establece una nueva contraseña para un usuario existente.</p>
                        <p class="text-danger"><strong>¡Este script es solo para desarrollo!</strong></p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="m-sm-3">

                                <?php // Mensajes de resultado 
                                ?>
                                <?php if ($success_msg): ?>
                                    <div class="alert alert-success" role="alert">
                                        <?php echo htmlspecialchars($success_msg); ?>
                                        <hr>
                                        <a href="login.php" class="btn btn-sm btn-primary">Ir a Iniciar Sesión</a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($error_msg && empty($validation_errors)): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?php echo htmlspecialchars($error_msg); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($error_msg && !empty($validation_errors)): ?>
                                    <div class="alert alert-warning" role="alert">
                                        <?php echo htmlspecialchars($error_msg); ?>
                                    </div>
                                <?php endif; ?>


                                <?php // Mostrar formulario solo si no hubo éxito 
                                ?>
                                <?php if (!$success_msg): ?>
                                    <form method="POST" action="update_password.php" novalidate>

                                        <?php // Campo Username del usuario a modificar 
                                        ?>
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Nombre de Usuario a modificar <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control <?php echo isset($validation_errors['username']) ? 'is-invalid' : ''; ?>"
                                                id="username" name="username" required
                                                value="<?php echo htmlspecialchars($form_data['username'] ?? 'admin'); /* Sugiere admin por defecto */ ?>">
                                            <?php if (isset($validation_errors['username'])): ?>
                                                <div class="invalid-feedback"><?php echo $validation_errors['username']; ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <?php // Campo Nueva Contraseña 
                                        ?>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control <?php echo (isset($validation_errors['new_password']) || isset($validation_errors['confirm_password'])) ? 'is-invalid' : ''; ?>"
                                                id="new_password" name="new_password" required minlength="8">
                                            <?php if (isset($validation_errors['new_password'])): ?>
                                                <div class="invalid-feedback"><?php echo $validation_errors['new_password']; ?></div>
                                            <?php else: ?>
                                                <small class="form-text text-muted">Mínimo 8 caracteres.</small>
                                            <?php endif; ?>
                                        </div>

                                        <?php // Campo Confirmar Nueva Contraseña 
                                        ?>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                                id="confirm_password" name="confirm_password" required>
                                            <?php if (isset($validation_errors['confirm_password'])): ?>
                                                <div class="invalid-feedback"><?php echo $validation_errors['confirm_password']; ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <?php // Botón Enviar 
                                        ?>
                                        <div class="d-grid gap-2 mt-3">
                                            <button type="submit" class="btn btn-lg btn-primary">Actualizar Contraseña</button>
                                        </div>

                                        <div class="text-center mt-3">
                                            <a href="login.php">Cancelar e ir a Login</a>
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
    </main>
</body>

</html>