<?php
// includes/permissions.php (Actualizado para Roles y Permisos)

// Depende de session.php y auth.php (cargados vía load.php)

/**
 * Obtiene el NOMBRE del rol del usuario actualmente logueado desde la sesión.
 * @return string|null El nombre del rol ('Admin', 'Supervisor Almacen', etc.) o null.
 */
function obtener_rol_usuario_actual()
{
    $usuario_sesion = obtener_datos_sesion(); // Función de session.php
    // Usamos la clave 'rol' que contiene el nombre del rol en el array devuelto
    return $usuario_sesion['rol'] ?? null;
}

/**
 * Verifica si el usuario actual tiene un rol específico (comparando nombres de rol).
 * @param string $rol_requerido El NOMBRE del rol a verificar (ej: 'Admin').
 * @return bool True si el usuario tiene el rol, False en caso contrario.
 */
function tiene_rol($rol_requerido)
{
    $rol_actual = obtener_rol_usuario_actual();
    return ($rol_actual !== null && $rol_actual === $rol_requerido);
}

/**
 * Verifica si el usuario actual tiene alguno de los roles proporcionados.
 * @param array $roles_permitidos Array con los NOMBRES de los roles permitidos.
 * @return bool True si tiene al menos uno de los roles, False si no.
 */
function tiene_algun_rol(array $roles_permitidos)
{
    $rol_actual = obtener_rol_usuario_actual();
    if ($rol_actual === null) {
        return false;
    }
    return in_array($rol_actual, $roles_permitidos, true);
}


/**
 * Verifica si el usuario actual tiene un permiso específico.
 * Trata al rol 'Admin' como si tuviera todos los permisos.
 * Busca el permiso en el array de permisos guardado en la sesión.
 * @param string $permiso_requerido La CLAVE del permiso a verificar (ej: 'ver_usuarios').
 * @return bool True si tiene el permiso (o es Admin), False si no.
 */
function tiene_permiso($permiso_requerido)
{
    // Requiere estar logueado para tener permisos
    if (!function_exists('esta_logueado') || !esta_logueado()) {
        return false;
    }

    // El rol 'Admin' tiene acceso a todo (simplificación común)
    if (tiene_rol('Admin')) {
        return true;
    }

    // Obtener los permisos de la sesión
    $datos_sesion = obtener_datos_sesion(); // Función de session.php
    // El array 'permissions' contiene las claves de permiso como strings
    $permisos_usuario = $datos_sesion['permissions'] ?? [];

    // Verificar si la clave del permiso requerido está en el array
    return in_array($permiso_requerido, $permisos_usuario, true); // true para comparación estricta
}


/**
 * Requiere que el usuario tenga un rol específico o un conjunto de roles para acceder.
 * Redirige si no cumple la condición.
 * @param string|array $roles_requeridos Nombre de rol o array de nombres de roles permitidos.
 * @param string $redirigir_a URL a la que redirigir si no tiene el rol (opcional).
 */
function requerir_rol($roles_requeridos, $redirigir_a = null)
{
    // Primero asegurar que está logueado (esta función también redirige si no lo está)
    if (function_exists('requerir_login')) {
        requerir_login();
    } else {
        die("Error: Sistema de autenticación no disponible.");
    }


    // Verificar si tiene el rol o uno de los roles
    $tiene_acceso = false;
    if (is_array($roles_requeridos)) {
        $tiene_acceso = tiene_algun_rol($roles_requeridos);
    } else {
        $tiene_acceso = tiene_rol($roles_requeridos);
    }

    // Si no tiene acceso, preparar redirección
    if (!$tiene_acceso) {
        if ($redirigir_a === null) {
            global $BASE_URL;
            $redirigir_a = ($BASE_URL ?? '/') . 'index.php'; // Por defecto a inicio o 'acceso_denegado.php'
        }
        if (function_exists('mensaje_sesion')) mensaje_sesion('No tienes el rol necesario para acceder a esta página.', 'danger');
        if (function_exists('redirigir_a')) redirigir_a($redirigir_a);
        exit; // Detener script
    }
    // Si tiene acceso, la función simplemente termina
}

/**
 * Requiere que el usuario tenga un permiso específico para acceder.
 * Redirige si no cumple la condición.
 * @param string $permiso_requerido La CLAVE del permiso necesario.
 * @param string $redirigir_a URL a la que redirigir si no tiene el permiso (opcional).
 */
function requerir_permiso($permiso_requerido, $redirigir_a = null)
{
    if (function_exists('requerir_login')) {
        requerir_login();
    } else {
        die("Error: Sistema de autenticación no disponible.");
    }

    // Verificar si tiene el permiso usando la nueva función
    if (!tiene_permiso($permiso_requerido)) {
        if ($redirigir_a === null) {
            global $BASE_URL;
            $redirigir_a = ($BASE_URL ?? '/') . 'index.php';
        }
        if (function_exists('mensaje_sesion')) mensaje_sesion('No tienes los permisos necesarios para realizar esta acción.', 'danger');
        if (function_exists('redirigir_a')) redirigir_a($redirigir_a);
        exit;
    }
    // Si tiene el permiso, la función termina
}
