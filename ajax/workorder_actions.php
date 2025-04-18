<?php
// ajax/workorder_actions.php - Manejador AJAX para acciones de Work Order
// Fecha de última revisión: 17 de Abril de 2025 // <-- Ajuste hipotético de fecha
// Versión con 4 estados de pickeo: PENDIENTE, EN_PROCESO, PARCIAL, COMPLETO

// Establecer zona horaria por si acaso (importante para logs y fechas)
date_default_timezone_set('America/Mexico_City'); // Ajusta a tu zona horaria

// Cargar configuración, funciones, sesión, conexión BD.
// La ruta es relativa a la ubicación de este archivo (ajax/)
require_once('../includes/load.php');

/**
 * Función auxiliar para enviar respuesta JSON estandarizada y terminar el script.
 * @param array $response_data Array asociativo con ['success' => true/false, 'message' => '...']
 */
function responder_json($response_data)
{
    // Limpiar cualquier salida anterior para asegurar JSON puro
    if (ob_get_level() > 0) ob_end_clean();
    // Establecer cabecera JSON
    header('Content-Type: application/json; charset=utf-8');
    // Imprimir JSON
    echo json_encode($response_data);
    exit; // Terminar script
}

// --- Seguridad y Validación Inicial ---

// 1. Verificar si el usuario está logueado
if (!esta_logueado()) {
    // Usamos 401 Unauthorized para el código HTTP en la respuesta
    http_response_code(401);
    responder_json(['success' => false, 'message' => 'Acceso denegado. Debes iniciar sesión.']);
}

// 2. Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Usamos 405 Method Not Allowed
    http_response_code(405);
    responder_json(['success' => false, 'message' => 'Método no permitido.']);
}

// 3. Recuperar y validar datos POST básicos
$form_action = $_POST['form_action'] ?? null;
$wo_afectada = $_POST['workorder'] ?? null;
$comentario = trim($_POST['accion_comentario'] ?? ''); // Comentario opcional/requerido según acción
$nuevo_usuario_id = isset($_POST['nuevo_usuario_id']) ? filter_var($_POST['nuevo_usuario_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null; // ID para asignar/reasignar, validar que sea entero positivo

// 4. Obtener datos del usuario actual
$datos_sesion = obtener_datos_sesion();
$current_user_id = $datos_sesion['id'] ?? null;
$current_user_rol = $datos_sesion['rol'] ?? null;
$current_user_name = $datos_sesion['nombre_completo'] ?? ('Usuario ID:' . $current_user_id); // Nombre para historial

// Validar que tenemos los datos mínimos para proceder
if (empty($form_action) || empty($wo_afectada) || $current_user_id === null) {
    // Usamos 400 Bad Request
    http_response_code(400);
    responder_json(['success' => false, 'message' => 'Faltan datos necesarios (acción, WO o usuario).']);
}

// --- Lógica Principal ---

// Obtener estado actual completo de la WO afectada ANTES de procesar la acción
$estado_wo_actual = null;
$db_error_flag = false;
try {
    global $db;
    // Seleccionar todas las columnas de estado relevantes de workorder_status
    $sql_st = "SELECT requiere_pickeo, estado_aprobacion_almacen, estado_pickeo, solicitada_produccion, estado_entrega, id_usuario_asignado
               FROM workorder_status WHERE workorder = :wo LIMIT 1";
    $stmt_st = $db->prepare($sql_st);
    $stmt_st->execute([':wo' => $wo_afectada]);
    $estado_wo_actual = $stmt_st->fetch();
    if (!$estado_wo_actual) {
        // Si no existe registro de estado, podría ser una WO recién cargada que falló en el INSERT IGNORE
        // O simplemente no existe la WO. Tratamos como error.
        throw new Exception("No se encontró registro de estado para la WO '$wo_afectada'.");
    }
} catch (\PDOException | \Exception $e) {
    error_log("AJAX Error buscando estado WO {$wo_afectada}: " . $e->getMessage());
    $estado_wo_actual = false;
    $db_error_flag = true; // Marcar que hubo error de BD
}

// Si no se encontró la WO o hubo error de BD, no continuar
if ($estado_wo_actual === false) {
    // Usamos 500 Internal Server Error o 404 Not Found si no se encontró
    http_response_code($db_error_flag ? 500 : 404);
    responder_json(['success' => false, 'message' => $db_error_flag ? 'Error al obtener el estado actual de la Work Order.' : 'Work Order no encontrada.']);
}

// --- Procesar la Acción Específica ---
$update_data = []; // Array para los campos a actualizar en workorder_status
$tipo_historial = ''; // Tipo de acción para el historial
$detalle_historial = ''; // Detalle para el historial
$mensaje_exito = ''; // Mensaje para el usuario en caso de éxito
$mensaje_error = 'Error desconocido o acción no procesada.'; // Mensaje de error por defecto
$accion_permitida = false; // Flag para permiso/validación de estado
$accion_realizada_bd = false; // Flag para saber si se intentó y tuvo éxito la actualización BD

// Determinar roles para comprobaciones rápidas (usando funciones de permissions.php)
$permiso_general_almacen = tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen']);
$permiso_admin_sup_alm = tiene_algun_rol(['Admin', 'Supervisor Almacen']);
$permiso_produccion = tiene_algun_rol(['Admin', 'Supervisor Produccion', 'Usuario Produccion']); // Roles que pueden solicitar/cancelar
$permiso_sup_prod = tiene_algun_rol(['Admin', 'Supervisor Produccion']); // Roles que pueden cancelar o solicitar cualquier WO

// Variable para saber si el usuario actual es el asignado al pickeo
$asignado_a_este_usuario = (($estado_wo_actual['id_usuario_asignado'] ?? null) == $current_user_id);

// --- Switch principal para manejar cada acción ---
switch ($form_action) {

    // --- Caso: Aprobar WO ---
    case 'aprobar_wo':
        $tipo_historial = 'APROBACION_ALMACEN';
        if (!$permiso_admin_sup_alm) {
            $mensaje_error = "Sin permiso para aprobar.";
            break;
        }
        if ($estado_wo_actual['estado_aprobacion_almacen'] !== 'PENDIENTE') {
            $mensaje_error = "WO ya no está pendiente de aprobación.";
            break;
        }

        $update_data = ['estado_aprobacion_almacen' => 'APROBADA'];
        $detalle_historial = "WO Aprobada.";
        if ($estado_wo_actual['requiere_pickeo'] == 0) {
            // Si no requiere pickeo, marcar pickeo como completo al aprobar
            $update_data['estado_pickeo'] = 'COMPLETO';
            $detalle_historial .= " Pickeo -> COMPLETO (No Requerido).";
        }
        // Añadir comentario si existe
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' aprobada.";
        break;

    // ************************************************
    // --- NUEVO CASO: Restablecer Aprobación WO ---
    // ************************************************
    case 'resetear_wo':
        $tipo_historial = 'APROBACION_RESETEADA';
        // Permiso: Solo Admin o Supervisor de Almacén pueden restablecer
        if (!$permiso_admin_sup_alm) {
            $mensaje_error = "Sin permiso para restablecer la aprobación.";
            break;
        }
        // Estado: Solo se puede restablecer si está APROBADA
        if ($estado_wo_actual['estado_aprobacion_almacen'] !== 'APROBADA') {
            $mensaje_error = "Solo se puede restablecer una WO que esté APROBADA.";
            break;
        }
        // Opcional: Podrías añadir una validación para no resetear si ya está ENTREGADA, aunque el estado de aprobación ya no sería APROBADA usualmente.
        // if ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
        //     $mensaje_error = "No se puede restablecer una WO ya entregada.";
        //     break;
        // }

        // Datos a actualizar para el reset
        $update_data = [
            'estado_aprobacion_almacen' => 'PENDIENTE',
            'estado_pickeo'             => 'PENDIENTE', // Volver pickeo a pendiente
            'id_usuario_asignado'       => null,      // Quitar asignación
            'estado_entrega'            => 'PENDIENTE', // Asegurar que entrega esté pendiente
            'solicitada_produccion'     => 0          // Quitar solicitud si la tuviera? Opcional
        ];
        $detalle_historial = "Aprobación restablecida a Pendiente.";
        // Añadir comentario si se envía (aunque el form actual no lo pide)
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' restablecida a pendiente.";
        break; // Fin case 'resetear_wo'

    // --- Caso: Iniciar Pickeo ---
    case 'iniciar_pickeo':
        $tipo_historial = 'PICKEO_INICIADO';
        if (!$permiso_general_almacen) {
            $mensaje_error = "Sin permiso para iniciar pickeo.";
            break;
        }
        if ($estado_wo_actual['requiere_pickeo'] != 1) {
            $mensaje_error = "WO no requiere pickeo.";
            break;
        }
        if ($estado_wo_actual['estado_aprobacion_almacen'] !== 'APROBADA') {
            $mensaje_error = "WO no aprobada.";
            break;
        }
        if ($estado_wo_actual['estado_pickeo'] !== 'PENDIENTE') {
            $mensaje_error = "Pickeo no está pendiente.";
            break;
        }
        if ($estado_wo_actual['id_usuario_asignado'] !== null) {
            $mensaje_error = "WO ya tiene un usuario asignado.";
            break;
        }

        $update_data = ['estado_pickeo' => 'EN_PROCESO', 'id_usuario_asignado' => $current_user_id];
        $detalle_historial = "Iniciado y asignado a: [" . htmlspecialchars($current_user_name) . "].";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "Pickeo iniciado para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- Caso: Pausar Pickeo (Marcar como PARCIAL) ---
    case 'pausar_pickeo':
        $tipo_historial = 'PICKEO_PAUSADO';
        $puede_pausar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_pausar) {
            $mensaje_error = "Sin permiso para pausar (no asignado o rol inválido).";
            break;
        }
        if ($estado_wo_actual['estado_pickeo'] !== 'EN_PROCESO') {
            $mensaje_error = "Solo se puede pausar un pickeo que esté 'En Proceso'.";
            break;
        }
        // Ahora el comentario es OBLIGATORIO para pausar y marcar como PARCIAL
        if (empty($comentario)) {
            $mensaje_error = "Se requiere un comentario para pausar/marcar como parcial.";
            break;
        }

        $update_data = ['estado_pickeo' => 'PARCIAL']; // Cambia a PARCIAL
        // Mantenemos la asignación actual
        $detalle_historial = "Pickeo pausado (Parcial) por: [" . htmlspecialchars($current_user_name) . "]. Motivo: " . htmlspecialchars($comentario);
        $accion_permitida = true;
        $mensaje_exito = "Pickeo marcado como parcial para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- Caso: Reanudar Pickeo (Volver a EN_PROCESO desde PARCIAL) ---
    case 'reanudar_pickeo':
        $tipo_historial = 'PICKEO_REANUDADO';
        $puede_reanudar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_reanudar) {
            $mensaje_error = "Sin permiso para reanudar (no asignado o rol inválido).";
            break;
        }
        if ($estado_wo_actual['estado_pickeo'] !== 'PARCIAL') {
            $mensaje_error = "Solo se puede reanudar un pickeo que esté 'Parcial'.";
            break;
        }

        $update_data = ['estado_pickeo' => 'EN_PROCESO']; // Vuelve a EN_PROCESO
        $detalle_historial = "Pickeo reanudado por: [" . htmlspecialchars($current_user_name) . "].";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "Pickeo reanudado para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- Caso: Completar Pickeo (Ahora desde EN_PROCESO o PARCIAL) ---
    case 'completar_pickeo':
        $tipo_historial = 'PICKEO_COMPLETADO';
        $puede_completar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_completar) {
            $mensaje_error = "Sin permiso para completar pickeo.";
            break;
        }
        if ($estado_wo_actual['requiere_pickeo'] != 1) {
            $mensaje_error = "WO no requiere pickeo.";
            break;
        }
        if ($estado_wo_actual['estado_aprobacion_almacen'] !== 'APROBADA') {
            $mensaje_error = "WO no aprobada.";
            break;
        }
        if (!in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL'])) {
            $mensaje_error = "Pickeo debe estar 'En Proceso' o 'Parcial' para ser completado.";
            break;
        }
        if ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
            $mensaje_error = "WO ya fue entregada.";
            break;
        }

        // Al completar, el estado cambia y se desasigna el usuario
        $update_data = ['estado_pickeo' => 'COMPLETO', 'id_usuario_asignado' => null];
        $detalle_historial = "Pickeo completado por: " . htmlspecialchars($current_user_name) . ".";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "Pickeo completado para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- Caso: Entregar WO (Permite desde COMPLETO, o PARCIAL/EN_PROCESO para Admin/Sup) ---
    case 'entregar_wo':
        $tipo_historial = 'ENTREGADO_PRODUCCION';
        $puede_entregar = false;
        $motivo_no_entrega = "No cumple requisitos.";

        // ¿Tiene permiso base para entregar?
        if ($permiso_general_almacen) {
            // ¿Está aprobada y pendiente de entrega?
            if ($estado_wo_actual['estado_aprobacion_almacen'] === 'APROBADA' && $estado_wo_actual['estado_entrega'] === 'PENDIENTE') {
                // ¿Pickeo Completo? -> Todos pueden entregar
                if ($estado_wo_actual['estado_pickeo'] === 'COMPLETO') {
                    $puede_entregar = true;
                }
                // ¿Pickeo En Proceso o Parcial? -> Solo Admin/Sup
                elseif (in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL'])) {
                    if ($permiso_admin_sup_alm) {
                        $puede_entregar = true;
                        $detalle_historial = "Entregado (Pickeo " . $estado_wo_actual['estado_pickeo'] . ") por: " . htmlspecialchars($current_user_name) . "."; // Nota especial
                    } else {
                        $motivo_no_entrega = "Solo Admin/Sup. Almacén pueden entregar WOs con pickeo parcial o en proceso.";
                    }
                } else { // Estado de pickeo PENDIENTE
                    $motivo_no_entrega = "El pickeo aún está pendiente.";
                }
            } else {
                $motivo_no_entrega = $estado_wo_actual['estado_aprobacion_almacen'] !== 'APROBADA' ? "La WO no está aprobada." : "La WO ya fue entregada.";
            }
        } else {
            $motivo_no_entrega = "Sin permiso para entregar.";
        }

        if (!$puede_entregar) {
            $mensaje_error = $motivo_no_entrega;
            break;
        }

        // Al entregar, estado entrega cambia, solicitud se anula, se desasigna usuario
        $update_data = ['estado_entrega' => 'ENTREGADA', 'solicitada_produccion' => 0, 'id_usuario_asignado' => null];
        // Usar detalle específico si se generó antes, si no, el genérico
        if (empty($detalle_historial)) {
            $detalle_historial = "Entregado por: " . htmlspecialchars($current_user_name) . ".";
        }
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' entregada.";
        break;

    // --- Caso: Solicitar WO ---
    case 'solicitar_wo':
        $tipo_historial = 'SOLICITADO_PRODUCCION';
        $puede_solicitar = false;
        $motivo_no_solicitud = "Sin permiso.";

        if ($permiso_sup_prod) { // Admin o Supervisor Producción pueden solicitar siempre que no esté entregada
            $puede_solicitar = true;
        } elseif (tiene_rol('Usuario Produccion') && $estado_wo_actual['estado_pickeo'] === 'COMPLETO') { // Usuario Producción solo si pickeo completo
            $puede_solicitar = true;
        } else {
            if (tiene_rol('Usuario Produccion')) $motivo_no_solicitud = "Como Usuario Producción, solo puedes solicitar si el pickeo está completo.";
        }

        // Validaciones adicionales si tiene permiso base
        if ($puede_solicitar) {
            if ($estado_wo_actual['solicitada_produccion'] == 1) {
                $motivo_no_solicitud = "La WO ya está solicitada.";
                $puede_solicitar = false;
            } elseif ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
                $motivo_no_solicitud = "La WO ya fue entregada.";
                $puede_solicitar = false;
            }
        }

        if (!$puede_solicitar) {
            $mensaje_error = $motivo_no_solicitud;
            break;
        }

        $update_data = ['solicitada_produccion' => 1];
        $detalle_historial = "Solicitado por Producción: [" . htmlspecialchars($current_user_name) . "].";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' marcada como solicitada.";
        break;

    // --- Caso: Cancelar Solicitud ---
    case 'cancelar_solicitud_wo':
        $tipo_historial = 'SOLICITUD_CANCELADA';
        // Solo Admin o Supervisor de Producción pueden cancelar
        if (!$permiso_sup_prod) {
            $mensaje_error = "Sin permiso para cancelar solicitud.";
            break;
        }
        if ($estado_wo_actual['solicitada_produccion'] == 0) {
            $mensaje_error = "La WO no está actualmente solicitada.";
            break;
        }
        // No se puede cancelar si ya fue entregada
        if ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
            $mensaje_error = "No se puede cancelar la solicitud de una WO ya entregada.";
            break;
        }

        $update_data = ['solicitada_produccion' => 0];
        $detalle_historial = "Solicitud cancelada por: [" . htmlspecialchars($current_user_name) . "].";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "Solicitud para WO '" . htmlspecialchars($wo_afectada) . "' cancelada.";
        break;

    // --- Caso: Registrar Progreso Pickeo (Ahora también si está PARCIAL) ---
    case 'registrar_progreso_pickeo':
        $tipo_historial = 'PICKEO_PROGRESO';
        $puede_registrar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_registrar) {
            $mensaje_error = "Sin permiso para registrar progreso (no asignado o rol inválido).";
            break;
        }
        // Permitir añadir notas si está EN_PROCESO o PARCIAL
        if (!in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL'])) {
            $mensaje_error = "Solo se pueden añadir notas si el pickeo está 'En Proceso' o 'Parcial'.";
            break;
        }
        if (empty($comentario)) {
            $mensaje_error = "El comentario es obligatorio para registrar progreso.";
            break;
        }

        $update_data = []; // Esta acción NO cambia el estado en workorder_status
        $detalle_historial = "Nota de [" . htmlspecialchars($current_user_name) . "]: " . htmlspecialchars($comentario);
        $accion_permitida = true;
        $accion_realizada_bd = true; // Se considera éxito porque solo registra historial
        $mensaje_exito = "Nota de progreso registrada.";
        break;

    // --- Caso: Asignar Pickeo (Desde página de asignación) ---
    case 'asignar_pickeo':
        $tipo_historial = 'ASIGNACION_PICKEO';
        // Solo Admin/Sup pueden asignar desde esta interfaz
        if (!$permiso_admin_sup_alm) {
            $mensaje_error = "Sin permiso para asignar WOs.";
            break;
        }
        // Validaciones de estado: PENDIENTE pickeo y SIN asignar
        if ($estado_wo_actual['estado_pickeo'] !== 'PENDIENTE' || $estado_wo_actual['id_usuario_asignado'] !== null) {
            $mensaje_error = "La WO ya no está pendiente de asignación (estado: " . $estado_wo_actual['estado_pickeo'] . ", asignado: " . ($estado_wo_actual['id_usuario_asignado'] ?? 'Nadie') . ").";
            break;
        }
        // Validar que se recibió un ID de usuario válido
        if ($nuevo_usuario_id === null) { // Usa la variable ya filtrada/validada
            $mensaje_error = "No se seleccionó un usuario válido para asignar.";
            break;
        }

        // Validar que el usuario seleccionado exista y sea asignable
        $nuevo_usuario_valido = false;
        $nombre_nuevo_usuario = '?';
        // Usar la función de sql.php para obtener usuarios asignables
        $lista_usuarios_asignables = buscar_usuarios_para_asignacion();
        foreach ($lista_usuarios_asignables as $usr) {
            if ($usr['id'] == $nuevo_usuario_id) {
                $nuevo_usuario_valido = true;
                $nombre_nuevo_usuario = $usr['nombre_completo'];
                break;
            }
        }

        if (!$nuevo_usuario_valido) {
            $mensaje_error = "El usuario seleccionado (ID: {$nuevo_usuario_id}) no es válido o no tiene permiso para pickeo.";
            break;
        }

        // Al asignar, cambia estado pickeo a EN_PROCESO y asigna usuario
        $update_data = ['estado_pickeo' => 'EN_PROCESO', 'id_usuario_asignado' => $nuevo_usuario_id];
        $detalle_historial = "Asignado a: [" . htmlspecialchars($nombre_nuevo_usuario) . "] por: [" . htmlspecialchars($current_user_name) . "].";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' asignada a " . htmlspecialchars($nombre_nuevo_usuario) . ".";
        break;

    // --- Caso: Reasignar Pickeo (Desde página de asignación, ahora permite desde PARCIAL) ---
    case 'reasignar_pickeo':
        $tipo_historial = 'REASIGNACION_PICKEO';
        $usuario_asignado_actual_id = $estado_wo_actual['id_usuario_asignado']; // Guardar ID actual

        // Solo Admin/Sup pueden reasignar
        if (!$permiso_admin_sup_alm) {
            $mensaje_error = "Sin permiso para reasignar WOs.";
            break;
        }
        // Validar estado: debe estar EN_PROCESO o PARCIAL y tener a alguien asignado
        if (!in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL']) || $usuario_asignado_actual_id === null) {
            $mensaje_error = "Solo se pueden reasignar WOs que estén 'En Proceso' o 'Parcial' y tengan un usuario asignado.";
            break;
        }
        // Validar que se recibió un nuevo usuario válido
        if ($nuevo_usuario_id === null) {
            $mensaje_error = "No se seleccionó un nuevo usuario válido para reasignar.";
            break;
        }
        // Validar que no se esté reasignando al mismo usuario
        if ($nuevo_usuario_id == $usuario_asignado_actual_id) {
            $mensaje_error = "La WO ya está asignada a este usuario.";
            // Podríamos responder con éxito parcial o info, pero lo tratamos como error para no hacer cambios.
            break;
        }

        // Validar que el nuevo usuario exista y sea asignable
        $nuevo_usuario_valido = false;
        $nombre_nuevo_usuario = '?';
        $lista_usuarios_asignables = buscar_usuarios_para_asignacion(); // Obtener lista fresca
        foreach ($lista_usuarios_asignables as $usr) {
            if ($usr['id'] == $nuevo_usuario_id) {
                $nuevo_usuario_valido = true;
                $nombre_nuevo_usuario = $usr['nombre_completo'];
                break;
            }
        }

        if (!$nuevo_usuario_valido) {
            $mensaje_error = "El nuevo usuario seleccionado (ID: {$nuevo_usuario_id}) no es válido o no tiene permiso para pickeo.";
            break;
        }

        // Al reasignar, SOLO cambia el ID del usuario asignado. El estado (EN_PROCESO/PARCIAL) no cambia.
        $update_data = ['id_usuario_asignado' => $nuevo_usuario_id];

        // Obtener nombre del usuario anterior para el historial
        $nombre_usuario_anterior = "ID:" . $usuario_asignado_actual_id;
        if ($usuario_asignado_actual_id && function_exists('buscar_usuario_por_id')) {
            $u_ant = buscar_usuario_por_id($usuario_asignado_actual_id);
            if ($u_ant) $nombre_usuario_anterior = trim($u_ant['nombre'] . ' ' . $u_ant['apellido']);
        }
        $detalle_historial = "Reasignado de [" . htmlspecialchars($nombre_usuario_anterior) . "] a [" . htmlspecialchars($nombre_nuevo_usuario) . "] por: [" . htmlspecialchars($current_user_name) . "].";
        if (!empty($comentario)) {
            $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' reasignada a " . htmlspecialchars($nombre_nuevo_usuario) . ".";
        break;

    // --- Acción: Auto-Asignar WO (Desde tabla Disponibles en mis_pickeos.php) ---
    case 'auto_asignar_wo':
        $tipo_historial = 'ASIGNACION_PICKEO';
        $accion_permitida = false; // Empezar asumiendo que no se permite
        $accion_realizada_bd = false; // Empezar asumiendo que no hay cambio BD

        // 1. Doble verificación del estado justo antes de asignar
        // ¿Requiere Pickeo? ¿Está Aprobada? ¿Pickeo Pendiente? ¿Sin asignar? ¿No entregada?
        if ($estado_wo_actual['requiere_pickeo'] != 1) {
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' no requiere pickeo.";
        } elseif ($estado_wo_actual['estado_aprobacion_almacen'] !== 'APROBADA') {
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' no está aprobada.";
        } elseif ($estado_wo_actual['estado_pickeo'] !== 'PENDIENTE') {
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' ya no tiene pickeo pendiente (Estado actual: " . $estado_wo_actual['estado_pickeo'] . ").";
        } elseif ($estado_wo_actual['id_usuario_asignado'] !== null) {
            // Obtener nombre si es posible para el mensaje
            $u_asignado_info = buscar_usuario_por_id($estado_wo_actual['id_usuario_asignado']);
            $u_asignado_nombre = $u_asignado_info ? trim($u_asignado_info['nombre'] . ' ' . $u_asignado_info['apellido']) : ('ID:' . $estado_wo_actual['id_usuario_asignado']);
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' ya fue asignada a [" . htmlspecialchars($u_asignado_nombre) . "].";
        } elseif ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' ya fue entregada.";
        } else {
            // Si pasa todas las verificaciones, la acción ES permitida
            $accion_permitida = true;

            // 2. Preparar datos para la actualización
            $update_data = [
                'estado_pickeo'       => 'EN_PROCESO',
                'id_usuario_asignado' => $current_user_id // Asignar al usuario actual
            ];

            // 3. Intentar actualizar la base de datos (se hace después del switch)
            $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' asignada a ti."; // Mensaje de éxito
            $detalle_historial = "Auto-asignado a: [" . htmlspecialchars($current_user_name) . "].";
            if (!empty($comentario)) {
                $detalle_historial .= " Comentario: " . htmlspecialchars($comentario);
            }
        }
        // Salir del switch para este caso
        break; // Fin Auto-Asignar

    // --- Caso por Defecto ---
    default:
        $mensaje_error = "Acción desconocida: " . htmlspecialchars($form_action);
        // Usamos 400 Bad Request para acción desconocida
        http_response_code(400);
        break;
} // Fin Switch

// --- Ejecutar Actualización BD (si aplica y fue permitida) ---
// Solo actualiza si $accion_permitida es true Y hay datos en $update_data
if ($accion_permitida && !empty($update_data)) {
    if (actualizar_estado_wo($wo_afectada, $update_data)) {
        $accion_realizada_bd = true;
    } else {
        $accion_realizada_bd = false;
        $mensaje_exito = ''; // Anular mensaje de éxito si falla BD
        if (empty($mensaje_error)) $mensaje_error = "Error crítico: Falló la actualización en la base de datos para la acción '$form_action'.";
        http_response_code(500); // Internal Server Error si falla BD
    }
} elseif ($accion_permitida && $form_action === 'registrar_progreso_pickeo') {
    // Caso especial: registrar progreso solo loguea, no actualiza BD, se considera éxito si $accion_permitida=true
    $accion_realizada_bd = true;
}

// --- Registrar Historial (si la acción fue válida y BD OK o no necesaria) ---
if ($accion_permitida && $accion_realizada_bd && !empty($tipo_historial)) {
    // El detalle ya debería tener el comentario incluido si era relevante
    if (function_exists('registrar_historial_wo')) {
        if (!registrar_historial_wo($wo_afectada, $tipo_historial, $detalle_historial, $current_user_id)) {
            // Opcional: Loguear si falla el registro de historial, pero no necesariamente fallar la acción principal
            error_log("Advertencia: Falló el registro de historial para WO {$wo_afectada}, Acción {$tipo_historial}.");
        }
    } else {
        error_log("Error crítico: La función registrar_historial_wo no está definida.");
        // Podrías incluso revertir la acción si el historial es mandatorio, pero usualmente solo se loguea.
    }
}

// --- Preparar Respuesta JSON Final ---
if ($accion_permitida && $accion_realizada_bd) {
    responder_json(['success' => true, 'message' => $mensaje_exito]);
} else {
    // Si no se estableció un código de error antes, usar 400 (Bad Request) o 403 (Forbidden) si fue permiso
    if (http_response_code() === 200) { // Si no se cambió antes
        if (strpos(strtolower($mensaje_error), 'permiso') !== false) {
            http_response_code(403); // Forbidden
        } else {
            http_response_code(400); // Bad Request (error de estado, etc.)
        }
    }
    responder_json(['success' => false, 'message' => $mensaje_error]);
}
