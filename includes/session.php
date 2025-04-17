<?php
// includes/session.php (Actualizado para Roles y Permisos)

// session_start() ya debe haber sido llamado en load.php

/**
 * Guarda datos del usuario en sesión, incluyendo rol y permisos.
 * @param array $usuario Array con datos del usuario (debe incluir id, username, id_rol, nombre_rol, foto_perfil).
 */
function registrar_sesion($usuario)
{
    if ($usuario && is_array($usuario)) {
        $_SESSION['user_id'] = (int)$usuario['id'];
        $_SESSION['username'] = $usuario['username'];
        $_SESSION['user_id_rol'] = (int)$usuario['id_rol'];
        $_SESSION['user_nombre_rol'] = $usuario['nombre_rol'] ?? 'N/A';
        $_SESSION['nombre_completo'] = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
        $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? null;

        // Obtener y guardar los permisos del usuario basados en su rol
        if (function_exists('buscar_permisos_por_rol_id')) {
            // Obtiene un array de strings con las claves de permiso
            $_SESSION['user_permissions'] = buscar_permisos_por_rol_id((int)$usuario['id_rol']);
        } else {
            $_SESSION['user_permissions'] = [];
            error_log("Advertencia: Función buscar_permisos_por_rol_id no encontrada al registrar sesión.");
        }

        session_regenerate_id(true);
    }
}

/**
 * Comprueba si hay una sesión de usuario activa verificando si existe 'user_id'.
 * @return bool True si el usuario parece estar logueado, False si no.
 */
function verificar_sesion()
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0; // Añadimos > 0 por si acaso
}

/**
 * Devuelve un array con los datos del usuario almacenados en la sesión.
 * @return array|null Array con datos o null si no hay sesión activa.
 */
function obtener_datos_sesion()
{
    if (verificar_sesion()) {
        return [
            'id'              => $_SESSION['user_id'] ?? null,
            'username'        => $_SESSION['username'] ?? null,
            'id_rol'          => $_SESSION['user_id_rol'] ?? null,
            'rol'             => $_SESSION['user_nombre_rol'] ?? null, // nombre_rol para uso general
            'nombre_completo' => $_SESSION['nombre_completo'] ?? null,
            'foto_perfil'     => $_SESSION['foto_perfil'] ?? null,
            'permissions'     => $_SESSION['user_permissions'] ?? [] // Array de claves de permiso
        ];
    }
    return null;
}


/**
 * Elimina todos los datos de la sesión actual, la cookie de sesión y destruye la sesión en el servidor.
 */
function destruir_sesion()
{
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    @session_destroy();
}


/**
 * Guarda un mensaje temporal (flash) en la sesión.
 * @param string $mensaje El texto del mensaje a mostrar.
 * @param string $tipo El tipo de alerta (ej: 'success', 'danger', 'info', 'warning').
 */
function mensaje_sesion($mensaje = "", $tipo = "info")
{
    if (!empty($mensaje)) {
        $_SESSION['mensaje_flash'] = [
            'mensaje' => $mensaje,
            'tipo' => $tipo
        ];
    }
}

/**
 * Comprueba si hay un mensaje flash, lo devuelve formateado como HTML (alerta Bootstrap) y lo elimina.
 * @return string Código HTML de la alerta o cadena vacía si no hay mensaje.
 */
function mostrar_mensaje_flash()
{
    if (isset($_SESSION['mensaje_flash'])) {
        $mensaje_data = $_SESSION['mensaje_flash'];
        unset($_SESSION['mensaje_flash']);

        $tipo_esc = htmlspecialchars($mensaje_data['tipo'], ENT_QUOTES, 'UTF-8');
        $mensaje_esc = htmlspecialchars($mensaje_data['mensaje'], ENT_QUOTES, 'UTF-8');
        // Clases estándar de Bootstrap para la alerta
        $clase_css = 'alert alert-' . $tipo_esc . ' alert-dismissible fade show';

        $html = "<div class=\"{$clase_css}\" role=\"alert\">";
        $html .= $mensaje_esc;
        $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>";
        $html .= "</div>";
        return $html;
    }
    return "";
}
