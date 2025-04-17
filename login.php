<?php
// Incluir configuración y funciones principales
require_once('includes/load.php');

// Si el usuario ya está logueado, redirigir al dashboard (index.php)
if (function_exists('esta_logueado') && esta_logueado()) {
    if (function_exists('redirigir_a')) {
        redirigir_a('index.php'); // O a tu página principal después del login
    }
    exit; // Salir para evitar que se procese el resto de la página
}

// Variable para almacenar mensajes de error
$error_msg = null;

// Procesar el formulario cuando se envía (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? null; // Puede ser username o email
    $password = $_POST['password'] ?? null;

    // Validación simple (puedes añadir más)
    if (empty($identifier) || empty($password)) {
        $error_msg = "Por favor, ingresa tu usuario/email y contraseña.";
    } else {
        // Intentar iniciar sesión usando la función de auth.php
        if (function_exists('intentar_login')) {
            $login_exitoso = intentar_login($identifier, $password);

            if ($login_exitoso) {
                // Inicio de sesión exitoso, redirigir
                if (function_exists('redirigir_a')) {
                    redirigir_a('index.php'); // Redirige al dashboard
                }
                exit;
            } else {
                // Falló el inicio de sesión
                $error_msg = "Usuario/Email o contraseña incorrectos, o la cuenta está inactiva.";
                // Opcional: podrías usar mensajes flash de sesión aquí también
                // if(function_exists('mensaje_sesion')) {
                //    mensaje_sesion("Usuario/Email o contraseña incorrectos.", 'danger');
                // }
            }
        } else {
            // Error crítico si la función no existe
            $error_msg = "Error interno del sistema de autenticación.";
            error_log("Error: La función intentar_login() no está definida o accesible.");
        }
    }
}

// Definir título para esta página (se usará en el <head>)
$page_title = 'Iniciar Sesión';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Iniciar sesión en el Portal de Órdenes de Trabajo">
    <meta name="author" content="Tu Nombre o Empresa">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/img/icons/icon-48x48.png" />

    <title><?php echo htmlspecialchars($page_title); ?> - WorkOrders</title>

    <link href="<?php echo BASE_URL; ?>assets/css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

    <?php // Estilo simple para centrar (AdminKit ya lo hace con las clases d-flex, etc.) 
    ?>
    <style>
        /* Opcional: puedes añadir estilos específicos aquí si es necesario */
    </style>
</head>

<body>
    <main class="d-flex w-100">
        <div class="container d-flex flex-column">
            <div class="row vh-100">
                <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
                    <div class="d-table-cell align-middle">

                        <div class="text-center mt-4">
                            <h1 class="h2">¡Bienvenido!</h1>
                            <p class="lead">
                                Inicia sesión para continuar
                            </p>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="m-sm-3">

                                    <?php // Mostrar mensaje de error si existe 
                                    ?>
                                    <?php if ($error_msg): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo htmlspecialchars($error_msg); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php // Mostrar mensajes flash si los usaste 
                                    ?>
                                    <?php
                                    // if (function_exists('mostrar_mensaje_flash')) {
                                    //    echo mostrar_mensaje_flash(); 
                                    // }
                                    ?>

                                    <form method="post" action="login.php"> <?php // Envía a sí mismo 
                                                                            ?>
                                        <div class="mb-3">
                                            <label class="form-label">Usuario o Email</label> <?php // Cambiado 
                                                                                                ?>
                                            <input class="form-control form-control-lg" type="text" name="identifier" placeholder="Ingresa tu usuario o email" required /> <?php // Cambiado a type="text" y name="identifier" 
                                                                                                                                                                            ?>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Contraseña</label>
                                            <input class="form-control form-control-lg" type="password" name="password" placeholder="Ingresa tu contraseña" required />
                                            <?php // Podrías añadir enlace "¿Olvidaste tu contraseña?" aquí si implementas esa función 
                                            ?>
                                            <?php /* <small>
                                                <a href="forgot-password.php">¿Olvidaste tu contraseña?</a>
                                            </small> */ ?>
                                        </div>
                                        <?php /* <div>
                                            <div class="form-check align-items-center">
                                                <input id="customControlInline" type="checkbox" class="form-check-input" value="remember-me" name="remember-me">
                                                <label class="form-check-label text-small" for="customControlInline">Recordarme</label>
                                            </div>
                                        </div> */ ?>
                                        <div class="d-grid gap-2 mt-3">
                                            <?php // Cambiado <a> por <button type="submit"> 
                                            ?>
                                            <button type="submit" class="btn btn-lg btn-primary">Iniciar Sesión</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php /* // Descomenta y ajusta si tienes página de registro
                        <div class="text-center mb-3">
                            ¿No tienes cuenta? <a href="<?php echo BASE_URL; ?>signup.php">Regístrate</a>
                        </div>
                        */ ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>

</body>

</html>