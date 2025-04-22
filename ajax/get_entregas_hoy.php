<?php
// ajax/get_entregas_hoy.php
// Endpoint AJAX para obtener las Work Orders entregadas hoy.

// Establecer encabezado JSON
header('Content-Type: application/json; charset=utf-8');

// Cargar dependencias y configuraciones
require_once('../includes/load.php');

// Respuesta por defecto en caso de error temprano
$response = ['data' => [], 'error' => 'No autorizado o error inicial.'];

// 1. Verificar Sesión de Usuario
//    Usamos una verificación simple, asumiendo que 'load.php' maneja la sesión.
//    'requerir_login()' normalmente redirige, lo cual no es ideal para AJAX.
//    Podríamos usar una función como 'esta_logueado()' si existe, o chequear la sesión directamente.
if (!isset($_SESSION['user_id'])) { // O la clave que uses para identificar al usuario logueado
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// 2. Verificar Permisos (Opcional pero recomendado para AJAX)
//    Replicamos la lógica de permisos de la página para seguridad adicional.
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Supervisor Produccion'])) {
    http_response_code(403); // Forbidden
    $response['error'] = 'No tienes permiso para acceder a estos datos.';
    echo json_encode($response);
    exit;
}

// 3. Obtener datos de la base de datos
//    Llamamos a la función SQL específica que debe existir en includes/sql.php
//    ¡¡ASEGÚRATE DE QUE LA FUNCIÓN buscar_wos_entregadas_hoy() EXISTA!!
if (function_exists('buscar_wos_entregadas_hoy')) {
    try {
        $entregas_hoy = buscar_wos_entregadas_hoy();

        // Verificar si la función devolvió datos válidos (un array)
        if (is_array($entregas_hoy)) {
            // Formato esperado por DataTables (objeto con clave 'data')
            $response = ['data' => $entregas_hoy];
        } else {
            // Si la función SQL falló internamente y devolvió false o algo inesperado
            http_response_code(500); // Internal Server Error
            $response['error'] = 'Error al obtener los datos de la base de datos.';
            // Opcionalmente, loguear el error real si la función lo permite
            error_log("AJAX Error: La función buscar_wos_entregadas_hoy() no devolvió un array.");
        }

    } catch (\PDOException $e) {
        // Capturar excepciones PDO si están habilitadas
        http_response_code(500);
        $response['error'] = 'Error de base de datos al obtener entregas.';
        error_log("AJAX PDOException en get_entregas_hoy.php: " . $e->getMessage());
    } catch (\Exception $e) {
        // Capturar otras excepciones generales
        http_response_code(500);
        $response['error'] = 'Error inesperado en el servidor.';
        error_log("AJAX Exception en get_entregas_hoy.php: " . $e->getMessage());
    }
} else {
    // Si la función necesaria ni siquiera existe
    http_response_code(501); // Not Implemented
    $response['error'] = 'Funcionalidad no implementada en el servidor (falta función SQL).';
    error_log("AJAX Error: Falta la función buscar_wos_entregadas_hoy() en includes/sql.php.");
}

// 4. Devolver respuesta JSON
//    Incluso si hubo un error antes y ya se estableció un código de respuesta,
//    devolvemos el estado final en formato JSON.
echo json_encode($response);
exit;



?>