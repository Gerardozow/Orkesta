<?php
// includes/functions.php

/**
 * Redirige al usuario a una URL específica.
 * @param string $url La URL a la que redirigir.
 */
function redirigir_a($url)
{
    header("Location: " . $url);
    exit; // Asegura que el script se detenga después de la redirección
}

/**
 * Sanitiza una cadena para prevenir ataques XSS (Cross-Site Scripting).
 * @param string $input La cadena de entrada.
 * @return string La cadena sanitizada.
 */
function sanitizar_html($input)
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitiza una cadena específicamente para salida en atributos HTML.
 * @param string $input La cadena de entrada.
 * @return string La cadena sanitizada para atributos.
 */
function sanitizar_atributo_html($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


/**
 * Formatea una fecha/hora a un formato legible.
 * @param string|int $timestamp La fecha/hora (puede ser timestamp o cadena compatible con strtotime).
 * @param string $formato El formato deseado (ver documentación de date() en PHP).
 * @return string La fecha formateada o un mensaje de error.
 */
function formatear_fecha($timestamp, $formato = 'd/m/Y H:i:s')
{
    if (is_numeric($timestamp)) {
        // Asume que es un timestamp Unix
        return date($formato, $timestamp);
    } else {
        // Intenta convertir una cadena a timestamp
        $time = strtotime($timestamp);
        if ($time === false) {
            return 'Fecha inválida';
        }
        return date($formato, $time);
    }
}


/**
 * Registra un evento en el historial de una Work Order.
 * * @param string $workorder El número de la Work Order afectada.
 * @param string $tipo_accion Clave que identifica la acción (ej: 'APROBADO', 'ASIGNADO').
 * @param string|null $detalle_accion Descripción detallada o comentario (opcional).
 * @param int|null $id_usuario_accion ID del usuario que realiza la acción. Si es null, intenta obtenerlo de la sesión actual.
 * @return bool True si el registro fue exitoso, False en caso de error.
 */
function registrar_historial_wo($workorder, $tipo_accion, $detalle_accion = null, $id_usuario_accion = null)
{
    global $db; // Acceso a la conexión PDO

    // Validar datos básicos
    if (empty($workorder) || empty($tipo_accion)) {
        error_log("Error registrar_historial_wo: Faltan workorder o tipo_accion.");
        return false;
    }

    // Si no se proporciona ID de usuario, intentar obtenerlo de la sesión
    if ($id_usuario_accion === null) {
        if (function_exists('obtener_datos_sesion')) {
            $datos_sesion = obtener_datos_sesion();
            $id_usuario_accion = $datos_sesion['id'] ?? null; // Puede ser null si no hay sesión
        }
    }

    $sql = "INSERT INTO workorder_historial 
                (workorder, id_usuario_accion, tipo_accion, detalle_accion, fecha_accion) 
            VALUES 
                (:wo, :id_user, :tipo, :detalle, NOW())";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':wo'      => $workorder,
            ':id_user' => ($id_usuario_accion ? (int)$id_usuario_accion : null), // Guardar NULL si no hay usuario
            ':tipo'    => $tipo_accion,
            ':detalle' => ($detalle_accion === '') ? null : $detalle_accion // Guardar NULL si detalle está vacío
        ]);
        return true; // Éxito
    } catch (\PDOException $e) {
        error_log("Error registrar_historial_wo (PDOException): " . $e->getMessage() .
            " | WO: " . $workorder . " | Acción: " . $tipo_accion);
        return false; // Fallo
    }
}
