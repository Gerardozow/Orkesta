<?php
// ajax/get_historial_entregas.php
// Endpoint AJAX para obtener TODO el historial de Work Orders entregadas.

header('Content-Type: application/json; charset=utf-8');
require_once('../includes/load.php');

$response = ['data' => [], 'error' => 'No autorizado o error inicial.'];

// 1. Verificar Sesión y Permisos (Similar al anterior endpoint)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode($response);
    exit;
}
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Supervisor Produccion'])) {
    http_response_code(403);
    $response['error'] = 'No tienes permiso para acceder a estos datos.';
    echo json_encode($response);
    exit;
}

// 3. Obtener datos usando la NUEVA función SQL
if (function_exists('buscar_historial_todas_las_entregas')) {
    try {
        $historial_entregas = buscar_historial_todas_las_entregas();

        if (is_array($historial_entregas)) {
            $response = ['data' => $historial_entregas];
        } else {
            http_response_code(500);
            $response['error'] = 'Error al obtener el historial de entregas de la base de datos.';
            error_log("AJAX Error: buscar_historial_todas_las_entregas() no devolvió un array.");
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        $response['error'] = 'Error de base de datos al obtener historial.';
        error_log("AJAX PDOException en get_historial_entregas.php: " . $e->getMessage());
    } catch (\Exception $e) {
        http_response_code(500);
        $response['error'] = 'Error inesperado en el servidor.';
        error_log("AJAX Exception en get_historial_entregas.php: " . $e->getMessage());
    }
} else {
    http_response_code(501);
    $response['error'] = 'Funcionalidad no implementada en el servidor (falta función SQL: buscar_historial_todas_las_entregas).';
    error_log("AJAX Error: Falta la función buscar_historial_todas_las_entregas() en includes/sql.php.");
}

// 4. Devolver respuesta JSON
echo json_encode($response);
exit;
?>