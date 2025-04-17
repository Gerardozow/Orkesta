<?php
// ajax/get_dashboard_counts.php - Devuelve los conteos para las tarjetas del dashboard

// Carga inicial (subiendo un nivel)
require_once('../includes/load.php');

// Función auxiliar para responder JSON
function responder_json_counts($response_data)
{
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response_data);
    exit;
}

// Verificar login y permiso para ver la sección (ajusta roles según sea necesario)
if (!esta_logueado() || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen', 'Supervisor Produccion'])) {
    // Devolver ceros o un error si no tiene permiso? Devolvamos ceros.
    responder_json_counts([
        'success' => false, // Opcional, indica si la obtención fue permitida/exitosa
        'message' => 'Permiso denegado o sesión no válida.', // Mensaje opcional
        'pendientes' => 0,
        'en_proceso' => 0,
        'en_espera' => 0,
        'entregadas_hoy' => 0
    ]);
}

// Calcular los conteos llamando a las funciones SQL
$counts = [
    'success' => true, // Indicar que la obtención fue exitosa
    'pendientes' => function_exists('contar_wos_pendientes_pickeo') ? contar_wos_pendientes_pickeo() : 0,
    'en_proceso' => function_exists('contar_wos_en_proceso_pickeo') ? contar_wos_en_proceso_pickeo() : 0,
    'en_espera' => function_exists('contar_wos_en_espera_entrega') ? contar_wos_en_espera_entrega() : 0,
    'entregadas_hoy' => function_exists('contar_wos_entregadas_hoy') ? contar_wos_entregadas_hoy() : 0
];

// Enviar respuesta JSON
responder_json_counts($counts);
