<?php
// ajax/get_andon_data.php - Devuelve datos para la pantalla Andon (Lista Única)

require_once('../includes/load.php');
date_default_timezone_set('America/Mexico_City'); // Ajusta

function responder_json_andon($data)
{
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    echo json_encode($data);
    exit;
}

$response = [
    'success' => false,
    'data' => [], // Solo tendremos una clave 'data'
    'error' => null
];

try {
    // Usar la función SQL que ya ordena Parciales primero, luego Completos
    if (function_exists('buscar_wos_para_andon')) {
        $lista_wos_andon = buscar_wos_para_andon();

        // Limpiar datos antes de enviar
        foreach ($lista_wos_andon as &$wo) { // Pasar por referencia para modificar
            foreach ($wo as $key => $value) {
                // Convertir null a '' y escapar HTML como medida extra
                $wo[$key] = $value === null ? '' : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        unset($wo); // Romper referencia

        $response['data'] = $lista_wos_andon; // Poner resultados en 'data'
        $response['success'] = true;
    } else {
        throw new Exception("Función de búsqueda no disponible.");
    }
} catch (Exception $e) {
    error_log("Error en get_andon_data.php: " . $e->getMessage());
    $response['error'] = "Error al obtener datos.";
    // http_response_code(500); // Opcional
}

// Enviar respuesta
responder_json_andon($response);
