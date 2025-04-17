<?php
// includes/sql.php (Versión PDO - Actualizado para Roles y Permisos)

// Asume que db.php ($db) está incluido vía load.php

// -----------------------------------------------------------------------
// FUNCIONES CRUD PARA USUARIOS (MODIFICADAS)
// -----------------------------------------------------------------------

/**
 * Busca un usuario ACTIVO por su username O email, incluyendo nombre de rol.
 * @param string $identifier Puede ser username o email.
 * @return array|false Datos del usuario (incluyendo id_rol, nombre_rol) o false.
 */
function buscar_usuario_por_identificador($identifier)
{
    global $db;
    $sql = "SELECT u.id, u.username, u.password, u.email, u.nombre, u.apellido, 
                   u.id_rol, r.nombre_rol, u.activo, u.foto_perfil
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id 
            WHERE (u.username = :id_user OR u.email = :id_email) AND u.activo = 1 
            LIMIT 1";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_user'  => $identifier,
            ':id_email' => $identifier
        ]);
        $usuario = $stmt->fetch();
        if ($usuario && $usuario['nombre_rol'] === null && $usuario['id_rol'] !== null) {
            error_log("Advertencia: Usuario ID {$usuario['id']} tiene id_rol {$usuario['id_rol']} pero no se encontró rol correspondiente en la tabla roles.");
            $usuario['nombre_rol'] = 'Rol Inválido/Borrado';
        } elseif ($usuario && $usuario['id_rol'] === null) {
            $usuario['nombre_rol'] = 'Sin Rol Asignado';
        }
        return $usuario;
    } catch (\PDOException $e) {
        error_log("Error en buscar_usuario_por_identificador: " . $e->getMessage());
        return false;
    }
}

/**
 * Busca un usuario (activo o inactivo) por su ID, incluyendo nombre de rol y password hash.
 * @param int $user_id
 * @return array|false Datos del usuario o false si no se encuentra o hay error.
 */
function buscar_usuario_por_id($user_id)
{
    global $db;
    $sql = "SELECT u.id, u.username, u.password, u.email, u.nombre, u.apellido, 
                   u.id_rol, r.nombre_rol, u.activo, u.fecha_creacion, u.foto_perfil
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id 
            WHERE u.id = :id 
            LIMIT 1";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => (int)$user_id]);
        $usuario = $stmt->fetch();
        if ($usuario && $usuario['nombre_rol'] === null && $usuario['id_rol'] !== null) {
            error_log("Advertencia: Usuario ID {$usuario['id']} tiene id_rol {$usuario['id_rol']} pero no se encontró rol correspondiente en la tabla roles.");
            $usuario['nombre_rol'] = 'Rol Inválido/Borrado';
        } elseif ($usuario && $usuario['id_rol'] === null) {
            $usuario['nombre_rol'] = 'Sin Rol Asignado';
        }
        return $usuario;
    } catch (\PDOException $e) {
        error_log("Error en buscar_usuario_por_id: " . $e->getMessage());
        return false;
    }
}


/**
 * Crea un nuevo usuario. Espera 'id_rol' numérico.
 * @param array $datos_usuario Array con datos. 'password' hasheada. 'id_rol', 'nombre', 'apellido' obligatorios.
 * @return string|false El ID del nuevo usuario o false si falla.
 */
function crear_usuario($datos_usuario)
{
    global $db;

    $datos_usuario['activo'] = $datos_usuario['activo'] ?? 1; // Default activo
    $datos_usuario['nombre'] = $datos_usuario['nombre'] ?? '';
    $datos_usuario['apellido'] = $datos_usuario['apellido'] ?? '';
    $datos_usuario['email'] = isset($datos_usuario['email']) && $datos_usuario['email'] !== '' ? $datos_usuario['email'] : null;
    $datos_usuario['foto_perfil'] = $datos_usuario['foto_perfil'] ?? null;

    if (empty($datos_usuario['username']) || empty($datos_usuario['password']) || !isset($datos_usuario['id_rol']) || $datos_usuario['id_rol'] === '' || $datos_usuario['id_rol'] === null || empty($datos_usuario['nombre']) || empty($datos_usuario['apellido'])) {
        error_log("Error en crear_usuario: Faltan campos obligatorios (username, password, id_rol, nombre, apellido).");
        return false;
    }

    $sql = "INSERT INTO usuarios (
                username, password, email, nombre, apellido, id_rol, activo, foto_perfil, fecha_creacion, fecha_actualizacion
            ) VALUES (
                :username, :password, :email, :nombre, :apellido, :id_rol, :activo, :foto_perfil, NOW(), NOW() 
            )";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':username'    => $datos_usuario['username'],
            ':password'    => $datos_usuario['password'],
            ':email'       => $datos_usuario['email'],
            ':nombre'      => $datos_usuario['nombre'],
            ':apellido'    => $datos_usuario['apellido'],
            ':id_rol'      => (int)$datos_usuario['id_rol'],
            ':activo'      => (int)$datos_usuario['activo'],
            ':foto_perfil' => $datos_usuario['foto_perfil']
        ]);
        return $db->lastInsertId();
    } catch (\PDOException $e) {
        error_log("Error en crear_usuario (PDOException): " . $e->getMessage());
        return false;
    }
}

/**
 * Actualiza datos de un usuario. Espera 'id_rol' numérico si se incluye.
 * @param int $user_id ID del usuario a actualizar.
 * @param array $datos_usuario Array con campos a actualizar. Password debe venir hasheada si se incluye.
 * @return bool True en éxito, False en fallo.
 */
function actualizar_usuario($user_id, $datos_usuario)
{
    global $db;

    $columnas_permitidas = ['username', 'email', 'nombre', 'apellido', 'id_rol', 'activo', 'password', 'foto_perfil'];

    $sets = [];
    $params = [];
    $params[':id'] = (int)$user_id;

    foreach ($datos_usuario as $columna => $valor) {
        if (in_array($columna, $columnas_permitidas)) {
            $placeholder = ":" . $columna;

            if ($columna === 'password') {
                if (!empty($valor)) {
                    $sets[] = "`password` = " . $placeholder;
                    $params[$placeholder] = $valor;
                }
            } elseif ($columna === 'email') {
                $sets[] = "`email` = " . $placeholder;
                $params[$placeholder] = ($valor === '' ? null : $valor);
            } elseif ($columna === 'id_rol' || $columna === 'activo') {
                $sets[] = "`" . $columna . "` = " . $placeholder;
                $params[$placeholder] = ($valor === null || $valor === '') ? null : (int)$valor; // Permitir NULL o convertir a INT
            } else {
                $sets[] = "`" . $columna . "` = " . $placeholder;
                $params[$placeholder] = $valor;
            }
        }
    }

    if (empty($sets)) return true;

    $sql = "UPDATE usuarios SET ";
    $sql .= join(', ', $sets);
    $sql .= ", fecha_actualizacion = NOW()";
    $sql .= " WHERE id = :id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (\PDOException $e) {
        error_log("Error en actualizar_usuario (PDOException): " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene todos los usuarios con su nombre de rol.
 * @return array Lista de usuarios o array vacío.
 */
function buscar_todos_los_usuarios()
{
    global $db;
    $sql = "SELECT u.id, u.username, u.nombre, u.apellido, u.email, u.id_rol, r.nombre_rol, u.activo 
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id
            ORDER BY u.nombre ASC, u.apellido ASC";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll();
        // Asegurar que 'nombre_rol' exista aunque sea null
        foreach ($usuarios as $key => $usuario) {
            if (!isset($usuario['nombre_rol'])) {
                $usuarios[$key]['nombre_rol'] = ($usuario['id_rol'] === null) ? 'Sin Rol Asignado' : 'Rol Inválido/Borrado';
            }
        }
        return $usuarios;
    } catch (\PDOException $e) {
        error_log("Error en buscar_todos_los_usuarios: " . $e->getMessage());
        return [];
    }
}


/**
 * Elimina un usuario (marcando como inactivo).
 * @param int $user_id El ID del usuario.
 * @return bool True si fue exitoso y al menos una fila fue afectada, False si no.
 */
function eliminar_usuario($user_id)
{
    global $db;
    $sql = "UPDATE usuarios SET activo = 0 WHERE id = :id";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => (int)$user_id]);
        return $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        error_log("Error en eliminar_usuario (PDOException): " . $e->getMessage());
        return false;
    }
}

// -----------------------------------------------------------------------
// FUNCIONES CRUD PARA ROLES (NUEVAS)
// -----------------------------------------------------------------------

/**
 * Obtiene todos los roles de la base de datos.
 * @return array Lista de roles o array vacío.
 */
function buscar_todos_los_roles()
{
    global $db;
    $sql = "SELECT id, nombre_rol, descripcion_rol FROM roles ORDER BY nombre_rol ASC";
    try {
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_todos_los_roles: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca un rol específico por su ID.
 * @param int $rol_id
 * @return array|false Datos del rol o false.
 */
function buscar_rol_por_id($rol_id)
{
    global $db;
    $sql = "SELECT id, nombre_rol, descripcion_rol FROM roles WHERE id = :id LIMIT 1";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => (int)$rol_id]);
        return $stmt->fetch();
    } catch (\PDOException $e) {
        error_log("Error en buscar_rol_por_id: " . $e->getMessage());
        return false;
    }
}

// Nota: Las funciones crear_rol, actualizar_rol, eliminar_rol se añadirían aquí
// cuando se creen las páginas de gestión de roles.

// -----------------------------------------------------------------------
// FUNCIONES PARA PERMISOS (NUEVAS)
// -----------------------------------------------------------------------

/**
 * Obtiene todas las claves de permiso asociadas a un ID de rol.
 * @param int $rol_id ID del rol.
 * @return array Lista de claves de permiso (strings) o array vacío.
 */
function buscar_permisos_por_rol_id($rol_id)
{
    global $db;
    $permisos = [];
    if (empty($rol_id) || !is_numeric($rol_id)) return []; // Evitar consulta si rol_id no es válido

    $sql = "SELECT p.clave_permiso 
            FROM rol_permisos rp
            JOIN permisos p ON rp.id_permiso = p.id
            WHERE rp.id_rol = :id_rol";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_rol' => (int)$rol_id]);
        $permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $permisos;
    } catch (\PDOException $e) {
        error_log("Error en buscar_permisos_por_rol_id: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene todos los permisos disponibles en el sistema.
 * @return array Lista de permisos (cada uno un array asociativo) o array vacío.
 */
function buscar_todos_los_permisos()
{
    global $db;
    $sql = "SELECT id, clave_permiso, descripcion_permiso FROM permisos ORDER BY clave_permiso ASC";
    try {
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_todos_los_permisos: " . $e->getMessage());
        return [];
    }
}


/**
 * Actualiza los permisos asociados a un rol.
 * Borra los permisos actuales y luego inserta los nuevos seleccionados.
 * @param int $rol_id ID del rol a actualizar.
 * @param array $nuevos_permisos_ids Array con los IDs de los permisos que debe tener el rol.
 * @return bool True si la operación fue exitosa, False si hubo algún error.
 */
function actualizar_permisos_rol($rol_id, $nuevos_permisos_ids = [])
{
    global $db;
    $rol_id_int = (int)$rol_id;

    if ($rol_id_int <= 0) return false; // ID de rol inválido

    $db->beginTransaction();
    try {
        // 1. Borrar permisos actuales
        $sql_delete = "DELETE FROM rol_permisos WHERE id_rol = :id_rol";
        $stmt_delete = $db->prepare($sql_delete);
        $stmt_delete->execute([':id_rol' => $rol_id_int]);

        // 2. Insertar nuevos permisos
        if (!empty($nuevos_permisos_ids)) {
            $sql_insert = "INSERT INTO rol_permisos (id_rol, id_permiso) VALUES (:id_rol, :id_permiso)";
            $stmt_insert = $db->prepare($sql_insert);

            foreach ($nuevos_permisos_ids as $permiso_id) {
                // Asegurar que el ID de permiso sea numérico válido
                if (is_numeric($permiso_id) && (int)$permiso_id > 0) {
                    $stmt_insert->execute([
                        ':id_rol' => $rol_id_int,
                        ':id_permiso' => (int)$permiso_id
                    ]);
                }
            }
        }

        $db->commit(); // Confirmar transacción
        return true;
    } catch (\PDOException $e) {
        $db->rollBack(); // Revertir en caso de error
        error_log("Error en actualizar_permisos_rol para rol ID {$rol_id_int}: " . $e->getMessage());
        return false;
    }
}


/**
 * Actualiza los datos de un rol existente.
 * @param int $rol_id ID del rol a actualizar.
 * @param array $data Array asociativo con los datos a actualizar (ej: ['nombre_rol' => 'Nuevo Nombre', 'descripcion_rol' => '...']).
 * @return bool True en éxito, False en fallo (ej. por nombre duplicado).
 */
function actualizar_rol($rol_id, $data)
{
    global $db;
    $rol_id_int = (int)$rol_id;
    // Validar entrada básica
    if ($rol_id_int <= 0 || empty($data) || !is_array($data)) {
        return false;
    }

    // Columnas permitidas para actualizar en la tabla 'roles'
    $allowed_cols = ['nombre_rol', 'descripcion_rol'];
    $sets = [];      // Para 'columna = :placeholder'
    $params = [':id' => $rol_id_int]; // Parámetros para PDO execute()

    // Construir dinámicamente la parte SET de la consulta
    foreach ($data as $col => $val) {
        if (in_array($col, $allowed_cols)) {
            $placeholder = ':' . $col; // Crear placeholder ej. :nombre_rol
            $sets[] = "`" . $col . "` = " . $placeholder; // Añadir a la lista SET ej. `nombre_rol` = :nombre_rol
            $params[$placeholder] = ($val === '') ? null : $val; // Asignar valor al parámetro, permitir NULL si está vacío
        }
    }

    // Si no hay campos válidos para actualizar, considerar éxito
    if (empty($sets)) {
        return true;
    }

    // Construir la consulta SQL final
    $sql = "UPDATE roles SET " . join(', ', $sets) . ", fecha_actualizacion = NOW() WHERE id = :id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        // execute() devuelve true en éxito o lanza excepción en fallo
        return true;
    } catch (\PDOException $e) {
        // Comprobar específicamente el error de duplicado (código 1062 en MySQL) para 'nombre_rol'
        // El índice UNIQUE en 'nombre_rol' causará este error si se intenta duplicar.
        if ($e->errorInfo[1] == 1062) {
            error_log("Error en actualizar_rol (Nombre duplicado): " . $e->getMessage());
        } else {
            error_log("Error en actualizar_rol (PDO): " . $e->getMessage());
        }
        return false; // Indicar fallo en la actualización
    }
}


/**
 * Inserta/Actualiza 'workorders' y asegura entrada en 'workorder_status'.
 * Registra historial SOLO si hubo inserción o cambio real en 'workorders'.
 * @param array $wo_data ['workorder', 'numero_parte', 'descripcion']. Opcional ['requiere_pickeo'].
 * @return bool True si éxito, False en error.
 */
function upsert_workorder($wo_data)
{
    global $db;

    if (empty($wo_data['workorder'])) {
        error_log("Error en upsert_workorder: Falta el número de Work Order.");
        return false;
    }

    $wo   = trim($wo_data['workorder']);
    $np   = isset($wo_data['numero_parte']) && trim($wo_data['numero_parte']) !== '' ? trim($wo_data['numero_parte']) : null;
    $desc = isset($wo_data['descripcion']) && trim($wo_data['descripcion']) !== '' ? trim($wo_data['descripcion']) : null;
    $req_p = isset($wo_data['requiere_pickeo']) ? (int)$wo_data['requiere_pickeo'] : 1;
    $estado_pickeo_inicial = 'PENDIENTE';
    $estado_aprobacion_inicial = 'PENDIENTE';
    $solicitada_inicial = 0;
    $estado_entrega_inicial = 'PENDIENTE';

    // $db->beginTransaction(); // Opcional: Iniciar transacción

    try {
        // 1. Upsert en la tabla `workorders`
        $sql_wo = "INSERT INTO workorders (workorder, numero_parte, descripcion, fecha_creacion, fecha_actualizacion)
                   VALUES (:workorder, :numero_parte, :descripcion, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE 
                      numero_parte = VALUES(numero_parte), 
                      descripcion = VALUES(descripcion),
                      fecha_actualizacion = NOW()";

        $stmt_wo = $db->prepare($sql_wo);
        $stmt_wo->execute([
            ':workorder'    => $wo,
            ':numero_parte' => $np,
            ':descripcion'  => $desc
        ]);

        // --- Capturar el número de filas afectadas por el upsert en workorders ---
        $rowCount_wo = $stmt_wo->rowCount();

        // 2. INSERT IGNORE en `workorder_status` (Siempre intentar asegurar que exista)
        $sql_status = "INSERT IGNORE INTO workorder_status (
                           workorder, requiere_pickeo, estado_aprobacion_almacen, 
                           estado_pickeo, solicitada_produccion, estado_entrega, 
                           fecha_estado_actualizacion
                       ) VALUES (
                           :workorder, :req_p, :est_aprob, 
                           :est_pick, :solic, :est_ent, 
                           NOW()
                       )";

        $stmt_status = $db->prepare($sql_status);
        $stmt_status->execute([
            ':workorder' => $wo,
            ':req_p' => $req_p,
            ':est_aprob' => $estado_aprobacion_inicial,
            ':est_pick' => $estado_pickeo_inicial,
            ':solic' => $solicitada_inicial,
            ':est_ent' => $estado_entrega_inicial
        ]);

        // --- Añadir Registro de Historial SOLO si hubo inserción o cambio (rowCount > 0) ---
        if ($rowCount_wo > 0) {
            if (function_exists('registrar_historial_wo')) {
                $detalle_historial = ($rowCount_wo == 1) ? 'WO nueva cargada.' : 'Datos básicos actualizados desde archivo/manual.';
                registrar_historial_wo(
                    $wo,
                    'CARGA_O_ACTUALIZACION_DATOS',
                    $detalle_historial,
                    null // ID Usuario null para carga sistema
                );
            }
        }
        // --- Fin Añadir Historial Condicional ---

        // $db->commit(); // Opcional: Confirmar transacción
        return true;
    } catch (\PDOException $e) {
        // $db->rollBack(); // Opcional: Revertir transacción
        error_log("Error en upsert_workorder (PDOException): " . $e->getMessage() . " | WO: " . $wo);
        return false;
    }
}



/**
 * Obtiene todas las Work Orders de la base de datos.
 * @return array Lista de WOs o array vacío en caso de error.
 */
function buscar_todas_las_workorders()
{
    global $db;
    // Seleccionamos las columnas relevantes, ordenamos por WO ascendentemente
    $sql = "SELECT workorder, numero_parte, descripcion, fecha_actualizacion 
            FROM workorders 
            ORDER BY workorder ASC";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_todas_las_workorders: " . $e->getMessage());
        return [];
    }
}


/**
 * Obtiene todas las Work Orders junto con sus datos de estado y un indicador
 * si existen comentarios de progreso de pickeo en el historial.
 * @return array Lista de WOs con datos combinados o array vacío.
 */
function buscar_todas_las_workorders_con_estado()
{
    global $db;
    // Añadimos una subconsulta EXISTS para verificar comentarios de progreso
    $sql = "SELECT 
                w.workorder, w.numero_parte, w.descripcion, 
                w.fecha_creacion AS wo_fecha_creacion, 
                w.fecha_actualizacion AS wo_fecha_actualizacion,
                ws.requiere_pickeo, 
                ws.estado_aprobacion_almacen, 
                ws.estado_pickeo, 
                ws.solicitada_produccion, 
                ws.estado_entrega,
                ws.id_usuario_asignado, -- Necesario para lógica de botones
                ws.fecha_estado_actualizacion,
                -- Subconsulta: Devuelve 1 si existe al menos un historial con tipo 'PICKEO_PROGRESO', 0 si no.
                (EXISTS (SELECT 1 
                         FROM workorder_historial wh 
                         WHERE wh.workorder = w.workorder 
                           AND wh.tipo_accion = 'PICKEO_PROGRESO' 
                )) AS tiene_comentario_progreso 
            FROM workorders w
            LEFT JOIN workorder_status ws ON w.workorder = ws.workorder
            ORDER BY w.workorder ASC"; // O por fecha_estado_actualizacion DESC?

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        // Rellenar estados por defecto si faltan (por si acaso)
        foreach ($results as $key => $row) {
            if (!isset($row['requiere_pickeo'])) $results[$key]['requiere_pickeo'] = 1;
            if (!isset($row['estado_aprobacion_almacen'])) $results[$key]['estado_aprobacion_almacen'] = 'PENDIENTE';
            if (!isset($row['estado_pickeo'])) $results[$key]['estado_pickeo'] = 'PENDIENTE';
            if (!isset($row['solicitada_produccion'])) $results[$key]['solicitada_produccion'] = 0;
            if (!isset($row['estado_entrega'])) $results[$key]['estado_entrega'] = 'PENDIENTE';
            if (!isset($row['tiene_comentario_progreso'])) $results[$key]['tiene_comentario_progreso'] = 0; // Asegurar que exista
        }
        return $results;
    } catch (\PDOException $e) {
        error_log("Error en buscar_todas_las_workorders_con_estado: " . $e->getMessage());
        return [];
    }
}

/**
 * Actualiza una o más columnas de estado para una Work Order específica
 * en la tabla workorder_status.
 * @param string $workorder La WO a actualizar.
 * @param array $data Array asociativo con columnas de estado a actualizar y sus nuevos valores.
 * @return bool True en éxito, False en fallo.
 */
function actualizar_estado_wo($workorder, $data)
{
    global $db;

    if (empty($workorder) || empty($data) || !is_array($data)) {
        error_log("Error actualizar_estado_wo: Input inválido.");
        return false;
    }

    // --- Añadir 'id_usuario_asignado' a las columnas permitidas ---
    $allowed_cols = [
        'requiere_pickeo',
        'estado_aprobacion_almacen',
        'estado_pickeo',
        'solicitada_produccion',
        'estado_entrega',
        'id_usuario_asignado' // <-- Añadido aquí
    ];

    $sets = [];
    $params = [':workorder' => $workorder];

    foreach ($data as $col => $val) {
        if (in_array($col, $allowed_cols)) {
            $placeholder = ':' . $col;
            $sets[] = "`" . $col . "` = " . $placeholder;

            // Ajustar el tipo o permitir NULL
            if ($col === 'id_usuario_asignado') {
                // Permitir NULL si el valor es null, 0, o vacío, de lo contrario convertir a int
                $params[$placeholder] = ($val === null || $val === 0 || $val === '') ? null : (int)$val;
            } elseif (is_bool($val)) {
                $params[$placeholder] = (int)$val;
            } elseif ($col === 'requiere_pickeo' || $col === 'solicitada_produccion') {
                $params[$placeholder] = (int)$val;
            } else {
                $params[$placeholder] = ($val === '') ? null : $val;
            }
        }
    }

    if (empty($sets)) {
        error_log("Error actualizar_estado_wo: No hay columnas válidas para actualizar.");
        return false;
    }

    $sql = "UPDATE workorder_status SET ";
    $sql .= join(', ', $sets);
    $sql .= ", fecha_estado_actualizacion = NOW()";
    $sql .= " WHERE workorder = :workorder";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (\PDOException $e) {
        error_log("Error en actualizar_estado_wo (PDOException): " . $e->getMessage() . " | WO: " . $workorder);
        return false;
    }
}


/**
 * Busca Work Orders que están listas para ser asignadas para pickeo.
 * Criterios: Requiere pickeo, Aprobada, Pendiente Pickeo, No Asignada.
 * @return array Lista de WOs listas para asignar (con datos básicos) o array vacío.
 */
function buscar_wos_para_asignar()
{
    global $db;
    // Selecciona WOs que cumplen todos los criterios y une con workorders para datos básicos
    $sql = "SELECT 
                ws.workorder, w.numero_parte, w.descripcion, 
                ws.fecha_estado_actualizacion 
            FROM workorder_status ws
            JOIN workorders w ON ws.workorder = w.workorder
            WHERE 
                ws.requiere_pickeo = 1 
                AND ws.estado_aprobacion_almacen = 'APROBADA' 
                AND ws.estado_pickeo = 'PENDIENTE' 
                AND ws.id_usuario_asignado IS NULL
                AND ws.estado_entrega = 'PENDIENTE' -- Asegurar que no esté ya entregada
            ORDER BY ws.fecha_estado_actualizacion ASC"; // O por prioridad, o WO? Ordenar por fecha de estado ASC para las más antiguas primero

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_para_asignar: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca usuarios activos que pueden realizar/ser asignados a tareas de pickeo.
 * (Roles: Admin, Supervisor Almacen, Usuario Almacen).
 * @return array Lista de usuarios (id, nombre_completo) o array vacío.
 */
function buscar_usuarios_para_asignacion()
{
    global $db;
    // Selecciona usuarios activos con los roles especificados
    // Une con la tabla roles para filtrar por nombre_rol
    $sql = "SELECT u.id, CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo
            FROM usuarios u
            JOIN roles r ON u.id_rol = r.id
            WHERE u.activo = 1 
              AND r.nombre_rol IN ('Admin', 'Supervisor Almacen', 'Usuario Almacen')
            ORDER BY nombre_completo ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_usuarios_para_asignacion: " . $e->getMessage());
        return [];
    }
}


/**
 * Busca Work Orders asignadas a un usuario específico que están EN PROCESO o PARCIAL de pickeo.
 * Incluye un indicador si existen comentarios de progreso en el historial.
 * @param int $user_id ID del usuario asignado.
 * @return array Lista de WOs asignadas al usuario o array vacío.
 */
function buscar_wos_asignadas_a_usuario($user_id)
{
    global $db;

    if (empty($user_id) || !is_numeric($user_id)) return [];

    // Modificamos WHERE para incluir estado 'PARCIAL'
    $sql = "SELECT 
                w.workorder, w.numero_parte, w.descripcion, 
                ws.estado_aprobacion_almacen, ws.estado_pickeo, 
                ws.solicitada_produccion, ws.estado_entrega,
                ws.id_usuario_asignado, 
                ws.fecha_estado_actualizacion,
                (EXISTS (SELECT 1 
                         FROM workorder_historial wh 
                         WHERE wh.workorder = w.workorder 
                           AND wh.tipo_accion = 'PICKEO_PROGRESO'
                )) AS tiene_comentario_progreso 
            FROM workorder_status ws
            JOIN workorders w ON ws.workorder = w.workorder
            WHERE 
                ws.id_usuario_asignado = :user_id
                AND ws.estado_pickeo IN ('EN_PROCESO', 'PARCIAL') -- Incluir ambos estados
                AND ws.estado_entrega = 'PENDIENTE' 
            ORDER BY ws.fecha_estado_actualizacion ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => (int)$user_id]);
        $results = $stmt->fetchAll();
        // Rellenar datos por si acaso
        foreach ($results as $key => $row) {
            if (!isset($row['tiene_comentario_progreso'])) $results[$key]['tiene_comentario_progreso'] = 0;
        }
        return $results;
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_asignadas_a_usuario: " . $e->getMessage());
        return [];
    }
}


/**
 * Busca WOs que están listas para asignar pickeo o están en proceso.
 * Criterios: Aprobada, No Entregada, Pickeo es Pendiente o En Proceso.
 * Incluye el nombre del usuario asignado actualmente.
 * @return array Lista de WOs para gestión de asignación o array vacío.
 */
function buscar_wos_para_gestion_asignacion()
{
    global $db;
    $sql = "SELECT 
                ws.workorder, 
                w.numero_parte, 
                w.descripcion, 
                ws.estado_pickeo, 
                ws.id_usuario_asignado,
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario_asignado, -- Nombre del usuario asignado
                ws.fecha_estado_actualizacion
            FROM workorder_status ws
            JOIN workorders w ON ws.workorder = w.workorder
            LEFT JOIN usuarios u ON ws.id_usuario_asignado = u.id -- LEFT JOIN para obtener nombre si está asignado
            WHERE 
                ws.requiere_pickeo = 1                 -- Solo las que requieren pickeo
                AND ws.estado_aprobacion_almacen = 'APROBADA'  -- Deben estar aprobadas
                AND ws.estado_pickeo IN ('PENDIENTE', 'EN_PROCESO') -- Pickeo pendiente o en proceso
                AND ws.estado_entrega = 'PENDIENTE'          -- No deben estar entregadas
            ORDER BY 
                CASE ws.estado_pickeo                    -- Ordenar: Pendientes primero, luego por fecha
                    WHEN 'PENDIENTE' THEN 1
                    WHEN 'EN_PROCESO' THEN 2
                    ELSE 3
                END ASC,
                ws.fecha_estado_actualizacion ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_para_gestion_asignacion: " . $e->getMessage());
        return [];
    }
}

/**
 * Cuenta las Work Orders que requieren pickeo, están aprobadas y pendientes de iniciar pickeo.
 * @return int El número de WOs pendientes de pickeo.
 */
function contar_wos_pendientes_pickeo()
{
    global $db;
    $sql = "SELECT COUNT(workorder) as total 
            FROM workorder_status 
            WHERE requiere_pickeo = 1 
              AND estado_aprobacion_almacen = 'APROBADA' 
              AND estado_pickeo = 'PENDIENTE' 
              AND estado_entrega = 'PENDIENTE'"; // Asegurar que no estén entregadas
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0); // Devolver 0 si hay error o no hay
    } catch (\PDOException $e) {
        error_log("Error en contar_wos_pendientes_pickeo: " . $e->getMessage());
        return 0;
    }
}

/**
 * Cuenta las Work Orders que requieren pickeo, están aprobadas y con pickeo en proceso.
 * @return int El número de WOs con pickeo en proceso.
 */
function contar_wos_en_proceso_pickeo()
{
    global $db;
    $sql = "SELECT COUNT(workorder) as total 
            FROM workorder_status 
            WHERE requiere_pickeo = 1 
              AND estado_aprobacion_almacen = 'APROBADA' 
              AND estado_pickeo = 'EN_PROCESO' 
              AND estado_entrega = 'PENDIENTE'"; // Asegurar que no estén entregadas
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (\PDOException $e) {
        error_log("Error en contar_wos_en_proceso_pickeo: " . $e->getMessage());
        return 0;
    }
}

/**
 * Cuenta las Work Orders marcadas como entregadas en el historial durante el día de hoy.
 * @return int El número de WOs entregadas hoy.
 */
function contar_wos_entregadas_hoy()
{
    global $db;
    // Contamos WOs distintas en el historial con la acción 'ENTREGADO_PRODUCCION' para la fecha actual (CURDATE())
    // CURDATE() usa la fecha del servidor de base de datos.
    $sql = "SELECT COUNT(DISTINCT workorder) as total 
            FROM workorder_historial 
            WHERE tipo_accion = 'ENTREGADO_PRODUCCION' 
              AND DATE(fecha_accion) = CURDATE()";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (\PDOException $e) {
        error_log("Error en contar_wos_entregadas_hoy: " . $e->getMessage());
        return 0;
    }
}


/**
 * Cuenta las WOs que están Aprobadas, con pickeo Completo o En Proceso, 
 * y pendientes de ser entregadas. (Área de Espera)
 * @return int El número de WOs en espera de entrega.
 */
function contar_wos_en_espera_entrega()
{
    global $db;
    $sql = "SELECT COUNT(workorder) as total 
            FROM workorder_status 
            WHERE estado_aprobacion_almacen = 'APROBADA' 
              AND estado_pickeo IN ('COMPLETO')
              AND estado_entrega = 'PENDIENTE'";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (\PDOException $e) {
        error_log("Error en contar_wos_en_espera_entrega: " . $e->getMessage());
        return 0;
    }
}


/**
 * Busca WOs APROBADAS, NO ENTREGADAS y con PICKEO PENDIENTE o EN PROCESO.
 * Recupera el detalle del último evento en su historial.
 * @return array Lista de WOs o array vacío.
 */
function buscar_wos_activas_con_ultimo_historial()
{
    global $db;

    $sql = "SELECT 
                w.workorder, w.numero_parte, w.descripcion, 
                ws.requiere_pickeo, 
                ws.estado_aprobacion_almacen, 
                ws.estado_pickeo, 
                ws.solicitada_produccion, 
                ws.estado_entrega,
                ws.id_usuario_asignado,
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_asignado, 
                ws.fecha_estado_actualizacion,
                (SELECT wh.detalle_accion 
                 FROM workorder_historial wh 
                 WHERE wh.workorder = w.workorder 
                 ORDER BY wh.fecha_accion DESC 
                 LIMIT 1
                ) AS ultimo_detalle_historial 
            FROM workorders w
            LEFT JOIN workorder_status ws ON w.workorder = ws.workorder
            LEFT JOIN usuarios u ON ws.id_usuario_asignado = u.id 
            WHERE 
                ws.estado_entrega = 'PENDIENTE'             -- No entregada
                AND ws.estado_aprobacion_almacen = 'APROBADA' -- Aprobada
                AND ws.estado_pickeo IN ('PENDIENTE', 'EN_PROCESO') -- NUEVO: Solo Pendiente o En Proceso
            ORDER BY 
                ws.fecha_estado_actualizacion DESC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        // Rellenar valores por defecto (sin cambios aquí)
        foreach ($results as $key => $row) { /* ... */
        }
        return $results;
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_activas_con_ultimo_historial: " . $e->getMessage());
        return [];
    }
}


/**
 * Busca todo el historial para una Work Order específica, incluyendo el nombre del usuario que realizó la acción.
 * @param string $workorder El número de la Work Order.
 * @return array Lista de registros de historial ordenados por fecha descendente, o array vacío.
 */
function buscar_historial_por_wo($workorder)
{
    global $db;

    if (empty($workorder)) return [];

    // Seleccionamos historial y unimos con usuarios para obtener el nombre
    $sql = "SELECT 
                h.id, h.workorder, h.id_usuario_accion, 
                h.tipo_accion, h.detalle_accion, h.fecha_accion,
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario_accion,
                u.username AS username_accion -- Opcional: incluir username también
            FROM workorder_historial h
            LEFT JOIN usuarios u ON h.id_usuario_accion = u.id -- LEFT JOIN por si el usuario fue borrado o es acción de sistema (NULL)
            WHERE h.workorder = :wo
            ORDER BY h.fecha_accion DESC"; // Mostrar lo más reciente primero

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':wo' => $workorder]);
        $results = $stmt->fetchAll();
        // Asignar 'Sistema' si no se encontró usuario
        foreach ($results as $key => $row) {
            if ($row['id_usuario_accion'] !== null && empty($row['nombre_usuario_accion'])) {
                $results[$key]['nombre_usuario_accion'] = "Usuario Borrado (ID: {$row['id_usuario_accion']})";
            } elseif ($row['id_usuario_accion'] === null) {
                $results[$key]['nombre_usuario_accion'] = "Sistema";
            }
        }
        return $results;
    } catch (\PDOException $e) {
        error_log("Error en buscar_historial_por_wo: " . $e->getMessage() . " | WO: " . $workorder);
        return [];
    }
}


/**
 * Busca WOs que están aprobadas, con pickeo completo o en proceso, 
 * y pendientes de entrega (listas en área de espera).
 * Incluye nombre asignado y si está solicitada.
 * @return array Lista de WOs pendientes de entrega o array vacío.
 */
function buscar_wos_pendientes_entrega()
{
    global $db;
    $sql = "SELECT 
                w.workorder, w.numero_parte, w.descripcion, 
                ws.estado_pickeo, 
                ws.solicitada_produccion, -- <<< ¡ASEGÚRATE QUE ESTA LÍNEA ESTÉ!
                ws.id_usuario_asignado,
                ws.estado_aprobacion_almacen, 
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario_asignado, 
                ws.fecha_estado_actualizacion
            FROM workorder_status ws
            JOIN workorders w ON ws.workorder = w.workorder
            LEFT JOIN usuarios u ON ws.id_usuario_asignado = u.id 
            WHERE 
                ws.estado_aprobacion_almacen = 'APROBADA' 
                AND ws.estado_pickeo IN ('COMPLETO', 'EN_PROCESO', 'PARCIAL') 
                AND ws.estado_entrega = 'PENDIENTE'
            ORDER BY 
                CASE ws.estado_pickeo                    
                    WHEN 'COMPLETO' THEN 1
                    WHEN 'EN_PROCESO' THEN 2
                    WHEN 'PARCIAL' THEN 3 
                    ELSE 4
                END ASC,
                ws.fecha_estado_actualizacion ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        // Bucle para asegurar valores default (debe incluir solicitada_produccion)
        foreach ($results as $key => $row) {
            if (!isset($row['nombre_usuario_asignado'])) $results[$key]['nombre_usuario_asignado'] = 'N/A';
            if (!isset($row['estado_aprobacion_almacen'])) $results[$key]['estado_aprobacion_almacen'] = 'PENDIENTE'; // O APROBADA si la query filtra
            if (!isset($row['estado_pickeo'])) $results[$key]['estado_pickeo'] = 'PENDIENTE';
            if (!isset($row['solicitada_produccion'])) $results[$key]['solicitada_produccion'] = 0; // Asegurar default
        }
        return $results;
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_pendientes_entrega: " . $e->getMessage());
        return [];
    }
}


/**
 * Busca WOs para la vista de supervisión: Aprobadas, No entregadas y 
 * con Pickeo EN PROCESO o PARCIAL.
 * Incluye nombre asignado y último historial.
 * @return array Lista de WOs en pickeo activo o array vacío.
 */
function buscar_wos_en_pickeo_para_supervisores()
{
    global $db;

    $sql = "SELECT 
                w.workorder, w.numero_parte, w.descripcion, 
                ws.requiere_pickeo, 
                ws.estado_aprobacion_almacen, 
                ws.estado_pickeo, 
                ws.solicitada_produccion, 
                ws.estado_entrega,
                ws.id_usuario_asignado,
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_asignado, 
                ws.fecha_estado_actualizacion,
                -- Subconsulta para verificar comentarios de progreso (necesaria para icono)
                (EXISTS (SELECT 1 
                         FROM workorder_historial wh 
                         WHERE wh.workorder = w.workorder 
                           AND wh.tipo_accion = 'PICKEO_PROGRESO'
                )) AS tiene_comentario_progreso,
                -- *** ESTA ES LA SUBCONSULTA CLAVE QUE FALTABA O ESTABA MAL ***
                (SELECT wh.detalle_accion 
                 FROM workorder_historial wh 
                 WHERE wh.workorder = w.workorder 
                 ORDER BY wh.fecha_accion DESC 
                 LIMIT 1
                ) AS ultimo_detalle_historial 
            FROM workorders w
            LEFT JOIN workorder_status ws ON w.workorder = ws.workorder
            LEFT JOIN usuarios u ON ws.id_usuario_asignado = u.id 
            WHERE 
                ws.estado_entrega = 'PENDIENTE'             
                AND ws.estado_aprobacion_almacen = 'APROBADA' 
                AND ws.estado_pickeo IN ('EN_PROCESO', 'PARCIAL') 
            ORDER BY 
                ws.fecha_estado_actualizacion ASC"; // Ordenar por fecha más antigua primero

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        // Rellenar valores por defecto
        foreach ($results as $key => $row) {
            // Asegurar que las claves esperadas por JS existan aunque sean null
            if (!isset($row['nombre_asignado'])) $results[$key]['nombre_asignado'] = null; // O 'N/A'
            if (!isset($row['tiene_comentario_progreso'])) $results[$key]['tiene_comentario_progreso'] = 0;
            if (!isset($row['ultimo_detalle_historial'])) $results[$key]['ultimo_detalle_historial'] = null; // Asegurar que existe, aunque sea null
            // Añadir otros defaults si fallan otras columnas
            if (!isset($row['numero_parte'])) $results[$key]['numero_parte'] = null;
            if (!isset($row['estado_pickeo'])) $results[$key]['estado_pickeo'] = 'PENDIENTE'; // O el default apropiado
            if (!isset($row['solicitada_produccion'])) $results[$key]['solicitada_produccion'] = 0;
            if (!isset($row['fecha_estado_actualizacion'])) $results[$key]['fecha_estado_actualizacion'] = null;
        }
        return $results;
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_en_pickeo_para_supervisores: " . $e->getMessage());
        return [];
    }
}



/**
 * Busca WOs para la pantalla Andon de Pickeo.
 * Criterios: Aprobada, No Entregada, Pickeo Parcial o Completo.
 * Selecciona solo los campos necesarios para visualización pública.
 * @return array Lista de WOs para Andon o array vacío.
 */
function buscar_wos_para_andon()
{
    global $db;
    // Seleccionamos solo WO, NP, Desc y Estado Pickeo
    // Unimos con workorders para obtener NP y Desc
    $sql = "SELECT 
                ws.workorder, 
                w.numero_parte, 
                w.descripcion, 
                ws.estado_pickeo
            FROM workorder_status ws
            JOIN workorders w ON ws.workorder = w.workorder
            WHERE 
                ws.estado_aprobacion_almacen = 'APROBADA'  
                AND ws.estado_pickeo IN ('PARCIAL', 'COMPLETO') -- Solo Parcial o Completo
                AND ws.estado_entrega = 'PENDIENTE'          
            ORDER BY 
                CASE ws.estado_pickeo   -- Mostrar Parciales primero
                    WHEN 'PARCIAL' THEN 1
                    WHEN 'COMPLETO' THEN 2
                    ELSE 3
                END ASC,
                w.workorder ASC"; // Luego ordenar por WO

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error en buscar_wos_para_andon: " . $e->getMessage());
        return [];
    }
}

/**
 * Cuenta WOs Aprobadas, No Entregadas, con Pickeo PARCIAL.
 * @return int Count.
 */
function contar_wos_pickeo_parcial_aprobadas()
{
    global $db;
    $sql = "SELECT COUNT(workorder) as total 
            FROM workorder_status 
            WHERE estado_aprobacion_almacen = 'APROBADA' 
              AND estado_pickeo = 'PARCIAL' 
              AND estado_entrega = 'PENDIENTE'";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (\PDOException $e) { /* Log error */
        return 0;
    }
}

/**
 * Cuenta WOs Aprobadas, No Entregadas, con Pickeo COMPLETO.
 * @return int Count.
 */
function contar_wos_pickeo_completo_aprobadas()
{
    global $db;
    $sql = "SELECT COUNT(workorder) as total 
            FROM workorder_status 
            WHERE estado_aprobacion_almacen = 'APROBADA' 
              AND estado_pickeo = 'COMPLETO' 
              AND estado_entrega = 'PENDIENTE'";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (\PDOException $e) { /* Log error */
        return 0;
    }
}
