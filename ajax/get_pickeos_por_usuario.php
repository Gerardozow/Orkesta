<?php
// ajax/get_pickeos_por_usuario.php
// Endpoint AJAX para obtener el conteo de pickeos completados por usuario.

header('Content-Type: application/json; charset=utf-8');
require_once('../includes/load.php');

$response = ['data' => [], 'error' => 'No autorizado o error inicial.'];

// 1. Verificar Sesión y Permisos (Admin / Supervisores)
if (!isset($_SESSION['user_id'])) { // Ajusta si usas otra clave de sesión
    http_response_code(401); echo json_encode($response); exit;
}
// Ajusta los roles según quién debe ver este reporte
$roles_permitidos = ['Admin', 'Supervisor Almacen'];
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol($roles_permitidos)) {
    http_response_code(403);
    $response['error'] = 'Permiso denegado.';
    echo json_encode($response);
    exit;
}

// 2. Obtener datos usando la nueva función SQL
if (function_exists('contar_pickeos_completos_por_usuario')) {
    try {
        $datos_pickeos = contar_pickeos_completos_por_usuario();

        if (is_array($datos_pickeos)) {
            // La función ya devuelve el formato [{nombre_usuario: X, total_pickeos: Y}, ...]
            // Solo necesitamos ponerlo dentro de la clave 'data'
            $response = ['data' => $datos_pickeos];
        } else {
            http_response_code(500);
            $response['error'] = 'Error al obtener los datos de pickeos de la base de datos.';
            error_log("AJAX Error: contar_pickeos_completos_por_usuario() no devolvió un array.");
        }
    } catch (\Exception $e) { // Captura PDOException u otras
        http_response_code(500);
        $response['error'] = 'Error del servidor al procesar la solicitud.';
        error_log("AJAX Exception en get_pickeos_por_usuario.php: " . $e->getMessage());
    }
} else {
    http_response_code(501);
    $response['error'] = 'Funcionalidad no implementada en el servidor (falta función SQL).';
    error_log("AJAX Error: Falta la función contar_pickeos_completos_por_usuario() en includes/sql.php.");
}

// 3. Devolver respuesta JSON
echo json_encode($response);
exit;
?>