<?php
// ajax/workorder_actions.php - Manejador AJAX para acciones de Work Order
// Fecha de última revisión: 16 de Abril de 2025
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
    responder_json(['success' => false, 'message' => 'Acceso denegado. Debes iniciar sesión.']);
}

// 2. Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    responder_json(['success' => false, 'message' => 'Error al obtener el estado actual de la Work Order.']);
}

// --- Procesar la Acción Específica ---
$update_data = []; // Array para los campos a actualizar en workorder_status
$tipo_historial = ''; // Tipo de acción para el historial
$detalle_historial = ''; // Detalle para el historial
$mensaje_exito = ''; // Mensaje para el usuario en caso de éxito
$mensaje_error = 'Error desconocido o acción no procesada.'; // Mensaje de error por defecto
$accion_permitida = false; // Flag para permiso/validación de estado
$accion_realizada_bd = false; // Flag para saber si se intentó y tuvo éxito la actualización BD

// Determinar roles para comprobaciones rápidas
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
            $mensaje_error = "WO ya no está pendiente.";
            break;
        }

        $update_data = ['estado_aprobacion_almacen' => 'APROBADA'];
        $detalle_historial = "WO Aprobada.";
        if ($estado_wo_actual['requiere_pickeo'] == 0) {
            // Si no requiere pickeo, marcar pickeo como completo al aprobar
            $update_data['estado_pickeo'] = 'COMPLETO';
            $detalle_historial .= " Pickeo -> COMPLETO (No Requerido).";
        }
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' aprobada.";
        break;

    // --- Caso: Iniciar Pickeo ---
    case 'iniciar_pickeo':
        $tipo_historial = 'PICKEO_INICIADO';
        if (!$permiso_general_almacen) {
            $mensaje_error = "Sin permiso para iniciar.";
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
            $mensaje_error = "Pickeo no pendiente.";
            break;
        }
        if ($estado_wo_actual['id_usuario_asignado'] !== null) {
            $mensaje_error = "WO ya asignada.";
            break;
        }

        $update_data = ['estado_pickeo' => 'EN_PROCESO', 'id_usuario_asignado' => $current_user_id];
        $detalle_historial = "Iniciado y asignado a: [" . htmlspecialchars($current_user_name) . "].";
        $accion_permitida = true;
        $mensaje_exito = "Pickeo iniciado para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- NUEVO Caso: Pausar Pickeo (Marcar como PARCIAL) ---
    case 'pausar_pickeo':
        $tipo_historial = 'PICKEO_PAUSADO';
        $puede_pausar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_pausar) {
            $mensaje_error = "Sin permiso (no asignado o rol).";
            break;
        }
        if ($estado_wo_actual['estado_pickeo'] !== 'EN_PROCESO') {
            $mensaje_error = "Solo pausar si está 'En Proceso'.";
            break;
        }
        if (empty($comentario)) {
            $mensaje_error = "Se requiere un comentario para pausar/marcar parcial.";
            break;
        }

        $update_data = ['estado_pickeo' => 'PARCIAL']; // Cambia a PARCIAL
        // Mantenemos la asignación actual
        $detalle_historial = "Pickeo pausado (Parcial) por: [" . htmlspecialchars($current_user_name) . "].";
        $accion_permitida = true;
        $mensaje_exito = "Pickeo marcado como parcial para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- NUEVO Caso: Reanudar Pickeo (Volver a EN_PROCESO) ---
    case 'reanudar_pickeo':
        $tipo_historial = 'PICKEO_REANUDADO';
        $puede_reanudar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_reanudar) {
            $mensaje_error = "Sin permiso (no asignado o rol).";
            break;
        }
        if ($estado_wo_actual['estado_pickeo'] !== 'PARCIAL') {
            $mensaje_error = "Solo reanudar si está 'Parcial'.";
            break;
        }

        $update_data = ['estado_pickeo' => 'EN_PROCESO']; // Vuelve a EN_PROCESO
        $detalle_historial = "Pickeo reanudado por: [" . htmlspecialchars($current_user_name) . "].";
        $accion_permitida = true;
        $mensaje_exito = "Pickeo reanudado para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- Caso: Completar Pickeo (Ahora desde EN_PROCESO o PARCIAL) ---
    case 'completar_pickeo':
        $tipo_historial = 'PICKEO_COMPLETADO';
        $puede_completar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_completar) {
            $mensaje_error = "Sin permiso para completar.";
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
            $mensaje_error = "Pickeo debe estar 'En Proceso' o 'Parcial'.";
            break;
        }
        if ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
            $mensaje_error = "WO ya entregada.";
            break;
        }

        $update_data = ['estado_pickeo' => 'COMPLETO', 'id_usuario_asignado' => null];
        $detalle_historial = "Completado por: " . htmlspecialchars($current_user_name) . ".";
        $accion_permitida = true;
        $mensaje_exito = "Pickeo completado para WO '" . htmlspecialchars($wo_afectada) . "'.";
        break;

    // --- Caso: Entregar WO (Ahora permite desde PARCIAL para Admin/Sup) ---
    case 'entregar_wo':
        $tipo_historial = 'ENTREGADO_PRODUCCION';
        $puede_entregar = false;
        $motivo_no_entrega = "No cumple requisitos.";
        if ($permiso_general_almacen) { // Rol general almacén requerido para entregar
            if ($estado_wo_actual['estado_aprobacion_almacen'] === 'APROBADA' && $estado_wo_actual['estado_entrega'] === 'PENDIENTE') {
                if ($estado_wo_actual['estado_pickeo'] === 'COMPLETO') {
                    $puede_entregar = true;
                }
                // Permite entrega si está EN_PROCESO o PARCIAL, pero solo por Admin/Sup
                elseif (in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL'])) {
                    if ($permiso_admin_sup_alm) {
                        $puede_entregar = true;
                    } else {
                        $motivo_no_entrega = "Solo Admin/Sup entregan pickeo parcial.";
                    }
                } else {
                    $motivo_no_entrega = "Pickeo pendiente.";
                }
            } else {
                $motivo_no_entrega = "No aprobada o ya entregada.";
            }
        } else {
            $motivo_no_entrega = "Sin permiso para entregar.";
        }

        if (!$puede_entregar) {
            $mensaje_error = $motivo_no_entrega;
            break;
        }

        $update_data = ['estado_entrega' => 'ENTREGADA', 'solicitada_produccion' => 0, 'id_usuario_asignado' => null];
        $detalle_historial = "Entregado por: " . htmlspecialchars($current_user_name) . ".";
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' entregada.";
        break;

    // --- Caso: Solicitar WO ---
    case 'solicitar_wo':
        $tipo_historial = 'SOLICITADO_PRODUCCION';
        $puede_solicitar = false;
        $motivo_no_solicitud = "Sin permiso.";
        if (tiene_algun_rol(['Admin', 'Supervisor Produccion'])) {
            $puede_solicitar = true;
        } elseif (tiene_rol('Usuario Produccion') && $estado_wo_actual['estado_pickeo'] === 'COMPLETO') {
            $puede_solicitar = true;
        } else {
            if (tiene_rol('Usuario Produccion')) $motivo_no_solicitud = "Solo solicitar si pickeo completo.";
        }
        if ($puede_solicitar) {
            if ($estado_wo_actual['solicitada_produccion'] == 1) {
                $motivo_no_solicitud = "WO ya solicitada.";
                $puede_solicitar = false;
            } elseif ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
                $motivo_no_solicitud = "WO ya entregada.";
                $puede_solicitar = false;
            }
        }
        if (!$puede_solicitar) {
            $mensaje_error = $motivo_no_solicitud;
            break;
        }

        $update_data = ['solicitada_produccion' => 1];
        $detalle_historial = "Solicitado por: " . htmlspecialchars($current_user_name) . ".";
        $accion_permitida = true;
        $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' marcada como solicitada.";
        break;

    // --- Caso: Cancelar Solicitud ---
    case 'cancelar_solicitud_wo':
        $tipo_historial = 'SOLICITUD_CANCELADA';
        if (!tiene_algun_rol(['Admin', 'Supervisor Produccion'])) {
            $mensaje_error = "Sin permiso.";
            break;
        }
        if ($estado_wo_actual['solicitada_produccion'] == 0) {
            $mensaje_error = "WO no solicitada.";
            break;
        }
        if ($estado_wo_actual['estado_entrega'] === 'ENTREGADA') {
            $mensaje_error = "No cancelar si ya entregada.";
            break;
        }

        $update_data = ['solicitada_produccion' => 0];
        $detalle_historial = "Cancelada por: " . htmlspecialchars($current_user_name) . ".";
        $accion_permitida = true;
        $mensaje_exito = "Solicitud para WO '" . htmlspecialchars($wo_afectada) . "' cancelada.";
        break;

    // --- Caso: Registrar Progreso Pickeo (Ahora también si está PARCIAL) ---
    case 'registrar_progreso_pickeo':
        $tipo_historial = 'PICKEO_PROGRESO';
        $puede_registrar = ($asignado_a_este_usuario || $permiso_admin_sup_alm);
        if (!$puede_registrar) {
            $mensaje_error = "Sin permiso (no asignado o rol).";
            break;
        }
        if (!in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL'])) {
            $mensaje_error = "Solo añadir notas si está 'En Proceso' o 'Parcial'.";
            break;
        }
        if (empty($comentario)) {
            $mensaje_error = "El comentario es obligatorio.";
            break;
        }

        $update_data = []; // No cambia estado
        $detalle_historial = "Nota de [" . htmlspecialchars($current_user_name) . "]: " . htmlspecialchars($comentario);
        $accion_permitida = true;
        $accion_realizada_bd = true; // Solo loguea
        $mensaje_exito = "Nota de progreso registrada.";
        break;

    // --- Caso: Asignar Pickeo (Desde página de asignación) ---
    case 'asignar_pickeo':
        $tipo_historial = 'ASIGNACION_PICKEO';
        if (!$permiso_admin_sup_alm) {
            $mensaje_error = "Sin permiso para asignar.";
            break;
        }
        if ($estado_wo_actual['estado_pickeo'] !== 'PENDIENTE' || $estado_wo_actual['id_usuario_asignado'] !== null) {
            $mensaje_error = "WO ya no está pendiente de asignación.";
            break;
        }
        if (empty($nuevo_usuario_id)) {
            $mensaje_error = "No se seleccionó usuario.";
            break;
        }

        $nuevo_usuario_valido = false;
        $nombre_nuevo_usuario = '?'; // Validar usuario
        global $lista_usuarios_asignables;
        if (empty($lista_usuarios_asignables)) $lista_usuarios_asignables = buscar_usuarios_para_asignacion();
        foreach ($lista_usuarios_asignables as $usr) {
            if ($usr['id'] == $nuevo_usuario_id) {
                $nuevo_usuario_valido = true;
                $nombre_nuevo_usuario = $usr['nombre_completo'];
                break;
            }
        }

        if (!$nuevo_usuario_valido) {
            $mensaje_error = "Usuario seleccionado inválido.";
            break;
        }

        $update_data = ['estado_pickeo' => 'EN_PROCESO', 'id_usuario_asignado' => $nuevo_usuario_id];
        $detalle_historial = "Asignado a: [" . htmlspecialchars($nombre_nuevo_usuario) . "] por: [" . htmlspecialchars($current_user_name) . "].";
        $accion_permitida = true;
        $mensaje_exito = "WO asignada a " . htmlspecialchars($nombre_nuevo_usuario) . ".";
        break;

    // --- Caso: Reasignar Pickeo ---
    case 'reasignar_pickeo':
        $tipo_historial = 'REASIGNACION_PICKEO';
        $usuario_asignado_actual_id = $estado_wo_actual['id_usuario_asignado'];
        if (!$permiso_admin_sup_alm) {
            $mensaje_error = "Sin permiso para reasignar.";
            break;
        }
        // Permitir reasignar si está EN_PROCESO o PARCIAL y tiene alguien asignado
        if (!in_array($estado_wo_actual['estado_pickeo'], ['EN_PROCESO', 'PARCIAL']) || $usuario_asignado_actual_id === null) {
            $mensaje_error = "Solo reasignar WOs EN PROCESO o PARCIAL y ya asignadas.";
            break;
        }
        if (empty($nuevo_usuario_id)) {
            $mensaje_error = "No se seleccionó nuevo usuario.";
            break;
        }
        if ($nuevo_usuario_id == $usuario_asignado_actual_id) {
            $mensaje_error = "WO ya asignada a ese usuario.";
            break;
        }

        $nuevo_usuario_valido = false;
        $nombre_nuevo_usuario = '?'; // Validar usuario
        // Cargar lista si no existe (puede pasar si el POST viene de otra página sin la lista)
        global $lista_usuarios_asignables;
        if (empty($lista_usuarios_asignables)) $lista_usuarios_asignables = buscar_usuarios_para_asignacion();
        foreach ($lista_usuarios_asignables as $usr) {
            if ($usr['id'] == $nuevo_usuario_id) {
                $nuevo_usuario_valido = true;
                $nombre_nuevo_usuario = $usr['nombre_completo'];
                break;
            }
        }

        if (!$nuevo_usuario_valido) {
            $mensaje_error = "Usuario seleccionado inválido.";
            break;
        }

        $update_data = ['id_usuario_asignado' => $nuevo_usuario_id]; // Solo cambia asignado
        $nombre_usuario_anterior = "ID:" . $usuario_asignado_actual_id; // Buscar nombre anterior
        if ($usuario_asignado_actual_id && function_exists('buscar_usuario_por_id')) {
            $u_ant = buscar_usuario_por_id($usuario_asignado_actual_id);
            if ($u_ant) $nombre_usuario_anterior = trim($u_ant['nombre'] . ' ' . $u_ant['apellido']);
        }
        $detalle_historial = "Reasignado de [" . htmlspecialchars($nombre_usuario_anterior) . "] a [" . htmlspecialchars($nombre_nuevo_usuario) . "] por: [" . htmlspecialchars($current_user_name) . "].";
        $accion_permitida = true;
        $mensaje_exito = "WO reasignada a " . htmlspecialchars($nombre_nuevo_usuario) . ".";
        break;

    // --- Acción: Auto-Asignar WO (Desde tabla Disponibles) ---
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
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' ya no tiene pickeo pendiente.";
        } elseif ($estado_wo_actual['id_usuario_asignado'] !== null) {
            $mensaje_error = "La WO '" . htmlspecialchars($wo_afectada) . "' ya fue asignada por otro usuario.";
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

            // 3. Intentar actualizar la base de datos
            if (actualizar_estado_wo($wo_afectada, $update_data)) {
                $accion_realizada_bd = true; // Marcar que la BD se actualizó
                $mensaje_exito = "WO '" . htmlspecialchars($wo_afectada) . "' asignada a ti."; // Mensaje de éxito
                // Preparar detalle para historial
                $detalle_historial = "Auto-asignado a: [" . htmlspecialchars($current_user_name) . "].";
            } else {
                // Si falla la actualización BD, marcar error
                $accion_permitida = false; // Consideramos que la acción falló si la BD falla
                $mensaje_error = "Error al intentar asignar la WO en la base de datos.";
            }
        }
        // Salir del switch para este caso
        break;
    // --- Fin Auto-Asignar ---
    // --- Caso por Defecto ---
    default:
        $mensaje_error = "Acción desconocida: " . htmlspecialchars($form_action);
        break;
} // Fin Switch

// --- Ejecutar Actualización BD (si aplica y fue permitida) ---
if ($accion_permitida && !$accion_realizada_bd && !empty($update_data)) {
    if (actualizar_estado_wo($wo_afectada, $update_data)) {
        $accion_realizada_bd = true;
    } else {
        $accion_realizada_bd = false;
        $mensaje_exito = ''; // Anular mensaje de éxito si falla BD
        if (empty($mensaje_error)) $mensaje_error = "Error al actualizar estado en BD para $form_action.";
    }
} elseif ($accion_permitida && empty($update_data) && $form_action === 'registrar_progreso_pickeo') {
    $accion_realizada_bd = true; // Se considera éxito porque solo era registrar historial
} elseif (!$accion_permitida && empty($mensaje_error)) {
    $mensaje_error = "La acción $form_action no pudo ser completada (estado o permiso inválido).";
}


// --- Registrar Historial (si la acción fue válida y BD OK) ---
if ($accion_permitida && $accion_realizada_bd && !empty($tipo_historial)) {
    // Añadir comentario del usuario si no se añadió antes al detalle específico
    if (!empty($comentario) && strpos($detalle_historial, 'Comentario:') === false) {
        $detalle_historial .= ($detalle_historial ? " Comentario: " : "Comentario: ") . htmlspecialchars($comentario);
    }
    if (function_exists('registrar_historial_wo')) {
        registrar_historial_wo($wo_afectada, $tipo_historial, $detalle_historial, $current_user_id);
    }
}

// --- Preparar Respuesta JSON ---
if ($accion_permitida && $accion_realizada_bd) {
    responder_json(['success' => true, 'message' => $mensaje_exito]);
} else {
    responder_json(['success' => false, 'message' => $mensaje_error]);
}
