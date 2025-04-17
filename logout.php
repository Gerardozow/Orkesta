<?php
// logout.php - Cierra la sesión del usuario

// Cargar configuración y funciones (incluye inicio de sesión y auth.php)
require_once('includes/load.php');

// Verificar si la función logout existe y llamarla
if (function_exists('logout')) {
    logout(); // Esta función (de auth.php) se encarga de destruir la sesión
} else {
    // Si logout() no existe, intentar destruir la sesión manualmente como respaldo
    // (Esto no debería ocurrir si load.php funciona bien)
    error_log("Error crítico: Función logout() no encontrada en logout.php");
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    @session_destroy();
}

// Redirigir al usuario a la página de login después de cerrar sesión
if (function_exists('redirigir_a')) {
    global $BASE_URL; // Acceder a la constante BASE_URL
    // Construir la URL de login usando BASE_URL si está definida
    $login_url = ($BASE_URL ?? '/franke/') . 'login.php';
    redirigir_a($login_url);
} else {
    // Fallback si la función redirigir_a no está disponible
    // Usar una redirección de encabezado HTTP simple
    header('Location: login.php');
    exit(); // Asegurar que el script se detenga aquí
}
?>

<?php // No debe haber NADA de HTML después de la redirección 
?>