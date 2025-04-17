<?php
// ajax/get_dashboard_data.php - Devuelve datos para las tablas DataTables vía AJAX
// Fecha de última revisión: 16 de Abril de 2025

// Establecer zona horaria consistente
date_default_timezone_set('America/Mexico_City'); // Ajusta a tu zona horaria

// Carga inicial (desde la carpeta ajax/ subimos un nivel a la raíz)
require_once('../includes/load.php');

/**
 * Función auxiliar para asegurar valores por defecto en filas de datos para JS.
 * @param array $fila Array de datos de una fila (pasado por referencia).
 * @param string $tipo_tabla Identificador de la tabla para saber qué campos esperar.
 */
function asegurar_campos_dt(&$fila, $tipo_tabla)
{
    // Define los campos esperados por cada tabla en el JS de DataTables y sus defaults
    $campos_esperados = [];

    if ($tipo_tabla === 'pickeos_activos') { // Tabla #tabla-wos-activas en index.php
        $campos_esperados = [
            'workorder' => '',
            'numero_parte' => null,
            'estado_pickeo' => 'PENDIENTE',
            'nombre_asignado' => null,
            'solicitada_produccion' => 0,
            'ultimo_detalle_historial' => null,
            'fecha_estado_actualizacion' => null,
            'tiene_comentario_progreso' => 0
        ];
    } elseif ($tipo_tabla === 'pendientes_entrega') { // Tabla #tabla-wos-pend-entrega en index.php
        $campos_esperados = [
            'workorder' => '',
            'numero_parte' => null,
            'descripcion' => null,
            'estado_pickeo' => 'PENDIENTE',
            'nombre_asignado' => null,
            'solicitada_produccion' => 0,
            'fecha_estado_actualizacion' => null,
            'estado_aprobacion_almacen' => 'PENDIENTE' // Necesario para lógica de botón en JS
        ];
    } elseif ($tipo_tabla === 'gestion_ordenes') { // Tabla #tabla-gestion-wo en gestionar_ordenes.php
        $campos_esperados = [
            'workorder' => '',
            'numero_parte' => null,
            'requiere_pickeo' => 1,
            'estado_aprobacion_almacen' => 'PENDIENTE',
            'estado_pickeo' => 'PENDIENTE',
            'nombre_asignado' => null,
            'solicitada_produccion' => 0,
            'estado_entrega' => 'PENDIENTE',
            'fecha_estado_actualizacion' => null,
            'id_usuario_asignado' => null,
            'tiene_comentario_progreso' => 0 // Asegurar si la consulta lo trae
        ];
    } elseif ($tipo_tabla === 'mis_pickeos') { // Tabla #tabla-mis-pickeos en mis_pickeos.php
        $campos_esperados = [
            'workorder' => '',
            'numero_parte' => null,
            'descripcion' => null,
            'estado_pickeo' => 'EN_PROCESO',
            'solicitada_produccion' => 0,
            'fecha_estado_actualizacion' => null,
            'tiene_comentario_progreso' => 0
        ];
    } elseif ($tipo_tabla === 'wos_disponibles') { // Tabla #tabla-wos-disponibles en mis_pickeos.php
        $campos_esperados = [
            'workorder' => '',
            'numero_parte' => null,
            'descripcion' => null,
            'fecha_estado_actualizacion' => null
        ];
    }

    // Rellenar defaults si falta alguna clave
    foreach ($campos_esperados as $clave => $default) {
        if (!isset($fila[$clave])) {
            $fila[$clave] = $default;
        } elseif ($fila[$clave] === null && $default !== null) {
            // Opcional: convertir null a default si no se quiere null en JS, 
            // pero es mejor manejar null en JS (ej. con defaultContent en DT)
            // $fila[$clave] = $default; 
        }
    }
}

/**
 * Función auxiliar para enviar respuesta JSON estandarizada y terminar el script.
 * @param array $response_data Array asociativo con ['success' => true/false, 'message' => '...'] o ['data' => ...] o ['error' => ...]
 */
function responder_json($response_data)
{
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response_data);
    exit;
}

// --- Seguridad y Validación Inicial ---
if (!esta_logueado()) {
    http_response_code(401);
    responder_json(['error' => 'No autorizado. Debes iniciar sesión.']);
}

$datos_sesion = obtener_datos_sesion();
$current_user_id = $datos_sesion['id'] ?? null;
$current_user_rol = $datos_sesion['rol'] ?? null;

if ($current_user_id === null) {
    http_response_code(403);
    responder_json(['error' => 'Sesión inválida o usuario no identificado.']);
}

// --- Determinar qué tabla se solicita ---
$tabla_solicitada = $_GET['tabla'] ?? null;
$data = [];
$output = []; // Para la respuesta final

if ($tabla_solicitada) {
    try {
        // Usar un switch para manejar las diferentes tablas
        switch ($tabla_solicitada) {

            // Para: index.php -> #tabla-wos-activas
            case 'pickeos_activos':
                // Primero, verificar si la función SQL existe
                if (!function_exists('buscar_wos_en_pickeo_para_supervisores')) {
                    throw new Exception("Función SQL no encontrada: buscar_wos_en_pickeo_para_supervisores");
                }

                // Obtener TODAS las WOs en pickeo activo (para Admin/Sup)
                $data_completa = buscar_wos_en_pickeo_para_supervisores();

                // --- Filtrado por Rol ---
                // Verificar si el usuario NO es Admin NI Supervisor Almacen
                if (!tiene_algun_rol(['Admin', 'Supervisor Almacen'])) {
                    // Si es otro rol (ej. Usuario Almacen), filtrar para mostrar solo las suyas
                    $data = array_filter($data_completa, function ($wo) use ($current_user_id) {
                        // Mantener solo las filas donde el ID asignado coincide con el usuario actual
                        return isset($wo['id_usuario_asignado']) && $wo['id_usuario_asignado'] == $current_user_id;
                    });
                    $data = array_values($data); // Reindexar array
                } else {
                    // Si es Admin o Supervisor, mostrar todos los resultados obtenidos
                    $data = $data_completa;
                }
                // --- Fin Filtrado por Rol ---

                // Asegurar que los campos necesarios para JS existan
                foreach ($data as $key => $row) {
                    if (!isset($row['nombre_asignado']) && isset($row['id_usuario_asignado'])) $data[$key]['nombre_asignado'] = "ID:" . $row['id_usuario_asignado'];
                    elseif (empty($row['nombre_asignado'])) $data[$key]['nombre_asignado'] = 'N/A';
                    if (!isset($row['tiene_comentario_progreso'])) $data[$key]['tiene_comentario_progreso'] = 0;
                    if (!isset($row['ultimo_detalle_historial'])) $data[$key]['ultimo_detalle_historial'] = null;
                    // Añadir otros defaults si son necesarios para las columnas JS
                    if (!isset($row['numero_parte'])) $data[$key]['numero_parte'] = null;
                    if (!isset($row['estado_pickeo'])) $data[$key]['estado_pickeo'] = 'PENDIENTE';
                    if (!isset($row['solicitada_produccion'])) $data[$key]['solicitada_produccion'] = 0;
                    if (!isset($row['fecha_estado_actualizacion'])) $data[$key]['fecha_estado_actualizacion'] = null;
                }
                break;

            // Para: index.php -> #tabla-wos-pend-entrega
            case 'pendientes_entrega':
                if (function_exists('buscar_wos_pendientes_entrega')) {
                    $data_completa = buscar_wos_pendientes_entrega();
                    // Filtrar si el rol NO es Admin ni Supervisor Almacen
                    if (!tiene_algun_rol(['Admin', 'Supervisor Almacen'])) {
                        $data = array_filter($data_completa, function ($wo) {
                            // Mantener solo las que tienen estado_pickeo COMPLETO
                            return ($wo['estado_pickeo'] ?? '') === 'COMPLETO';
                        });
                        $data = array_values($data); // Reindexar array numéricamente
                    } else {
                        // Admin/Sup ven todo (Completas y Parciales/En Proceso)
                        $data = $data_completa;
                    }
                    // Asegurar que estado_aprobacion_almacen esté presente para JS
                    foreach ($data as $key => $row) {
                        if (!isset($row['estado_aprobacion_almacen'])) $data[$key]['estado_aprobacion_almacen'] = 'APROBADA';
                    }
                } else {
                    throw new Exception("Función SQL no encontrada: buscar_wos_pendientes_entrega");
                }
                break;

            // Para: almacen/gestionar_ordenes.php -> #tabla-gestion-wo
            case 'gestion_ordenes':
                if (!tiene_algun_rol(['Admin', 'Supervisor Almacen'])) {
                    throw new Exception("Permiso denegado para ver gestión de órdenes.");
                }
                if (function_exists('buscar_todas_las_workorders_con_estado')) {
                    $data = buscar_todas_las_workorders_con_estado();
                } else {
                    throw new Exception("Función SQL no encontrada: buscar_todas_las_workorders_con_estado");
                }
                break;

            // Para: almacen/mis_pickeos.php -> #tabla-mis-pickeos
            case 'mis_pickeos':
                if (function_exists('buscar_wos_asignadas_a_usuario')) {
                    $data = buscar_wos_asignadas_a_usuario($current_user_id);
                } else {
                    throw new Exception("Función SQL no encontrada: buscar_wos_asignadas_a_usuario");
                }
                break;

            // Para: almacen/mis_pickeos.php -> #tabla-wos-disponibles
            case 'wos_disponibles':
                if (function_exists('buscar_wos_para_asignar')) {
                    $data = buscar_wos_para_asignar();
                } else {
                    throw new Exception("Función SQL no encontrada: buscar_wos_para_asignar");
                }
                break;

            default:
                throw new Exception("Tabla solicitada no válida: " . htmlspecialchars($tabla_solicitada));
                break;
        } // Fin Switch

        // Asegurar que todos los campos esperados existan en cada fila
        if (!empty($data)) {
            foreach ($data as &$fila) { // Usar & para modificar el array original
                asegurar_campos_dt($fila, $tabla_solicitada);
            }
            unset($fila); // Romper referencia
        }

        // Preparar la respuesta JSON final con éxito
        $output = ["data" => $data];
    } catch (Exception $e) {
        // Capturar cualquier excepción y devolver error JSON
        error_log("Error en get_dashboard_data.php: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        $output = ["error" => "Ocurrió un error al procesar la solicitud: " . $e->getMessage(), "data" => []]; // Devolver data vacía
        // En producción, usar un mensaje genérico:
        // $output = ["error" => "Ocurrió un error interno al procesar la solicitud.", "data" => []]; 
    }
} else {
    // Si no se especificó la tabla
    http_response_code(400); // Bad Request
    $output = ["error" => "No se especificó la tabla de datos a cargar.", "data" => []]; // Devolver data vacía
}

// --- Enviar Respuesta JSON ---
responder_json($output); // Usa la función auxiliar
