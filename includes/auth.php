<?php
// includes/auth.php

// Funciones relacionadas con la autenticación de usuarios.
// Depende de: session.php, sql.php, functions.php (cargados vía load.php)

/**
 * Intenta iniciar sesión con un identificador (usuario o email) y contraseña.
 * @param string $identifier El nombre de usuario o email.
 * @param string $password La contraseña ingresada por el usuario.
 * @return bool True si el inicio de sesión es exitoso, False en caso contrario.
 */
function intentar_login($identifier, $password)
{
    // Verificar que la función de búsqueda existe
    if (!function_exists('buscar_usuario_por_identificador')) {
        // Podrías loguear este error si quieres, pero no para el usuario final
        return false;
    }
    // Buscar usuario activo por username o email
    $usuario = buscar_usuario_por_identificador($identifier);

    // Si se encontró un usuario activo
    if ($usuario) {
        // Verificar la contraseña contra el hash almacenado
        if (password_verify($password, $usuario['password'])) {
            // Contraseña correcta, registrar sesión
            if (function_exists('registrar_sesion')) {
                registrar_sesion($usuario);
                // Opcional: Actualizar último login
                // if (function_exists('actualizar_ultimo_login')) {
                //     actualizar_ultimo_login($usuario['id']); 
                // }
                return true; // Éxito
            } else {
                // No se pudo registrar sesión, fallar login
                return false;
            }
        }
    }

    // Si no se encontró usuario, está inactivo, o la contraseña es incorrecta
    return false;
}

/**
 * Verifica si el usuario actual está conectado (basado en la sesión).
 * @return bool True si está conectado, False si no.
 */
function esta_logueado()
{
    if (function_exists('verificar_sesion')) {
        return verificar_sesion();
    }
    return false; // Asumir no logueado si la función falta
}

/**
 * Cierra la sesión del usuario actual.
 */
function logout()
{
    if (function_exists('destruir_sesion')) {
        destruir_sesion();
    } else {
        // Fallback si destruir_sesion no existe
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        @session_destroy();
    }
}

/**
 * Requiere que el usuario esté logueado para acceder a la página actual.
 * Si no está logueado, redirige a la página de login y detiene el script.
 */
function requerir_login()
{
    if (!esta_logueado()) {
        if (function_exists('redirigir_a')) {
            global $BASE_URL;
            $login_page = ($BASE_URL ?? '/orkesta/') . 'login.php'; // Usar BASE_URL si está definida
            redirigir_a($login_page);
        } else {
            // Fallback si redirigir_a no existe
            die("Acceso denegado. Por favor, inicia sesión.");
        }
        exit; // Detener ejecución
    }
}
