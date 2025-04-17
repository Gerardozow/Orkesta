<?php
// usuarios_estado.php - Cambia el estado activo/inactivo de un usuario

require_once('includes/load.php');
requerir_login(); // Primero logueado

// --- Verificación de Rol: Solo Admin puede cambiar estados ---
if (!function_exists('tiene_rol') || !tiene_rol('Admin')) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para modificar el estado de los usuarios.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php'); // Devolver a la lista
    exit;
}
// --- Fin Verificación de Rol ---

// --- Validar Método y Datos POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, redirigir
    if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php');
    exit;
}

// Validar que recibimos los datos necesarios
$user_id_to_change = $_POST['user_id'] ?? null;
$new_status_raw = $_POST['new_status'] ?? null; // Recibir como podría venir del form

// Validar ID y estado
if ($user_id_to_change === null || $new_status_raw === null || !in_array($new_status_raw, ['0', '1'], true)) { // Usar true para comparación estricta de string
    if (function_exists('mensaje_sesion')) mensaje_sesion("Solicitud inválida para cambiar estado (faltan datos o estado inválido).", "danger");
    if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php');
    exit;
}

// Convertir a enteros después de validar que son '0' o '1'
$user_id_to_change = (int)$user_id_to_change;
$new_status = (int)$new_status_raw;

// --- Prevenir Auto-Desactivación ---
$datos_sesion = obtener_datos_sesion();
$current_user_id = $datos_sesion['id'] ?? null;

if ($user_id_to_change === $current_user_id && $new_status === 0) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Operación no permitida: No puedes desactivar tu propia cuenta.", "warning");
    if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php');
    exit;
}

// --- Actualizar Base de Datos ---
$update_data = ['activo' => $new_status];
if (actualizar_usuario($user_id_to_change, $update_data)) {
    $accion = ($new_status === 1) ? 'activado' : 'desactivado';
    if (function_exists('mensaje_sesion')) mensaje_sesion("Usuario ha sido {$accion} con éxito.", "success");
} else {
    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al actualizar el estado del usuario en la base de datos.", "danger");
    // Podrías loguear el error de actualizar_usuario aquí si esa función no lo hace
}

// Redirigir siempre de vuelta a la lista
if (function_exists('redirigir_a')) redirigir_a('usuarios_ver.php');
exit;
