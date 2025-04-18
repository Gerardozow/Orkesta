
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `permisos` (
  `id` int UNSIGNED NOT NULL,
  `clave_permiso` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion_permiso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permisos` (`id`, `clave_permiso`, `descripcion_permiso`) VALUES
(1, 'ver_dashboard', 'Acceso al dashboard principal'),
(2, 'ver_usuarios', 'Ver lista de usuarios'),
(3, 'crear_usuario', 'Crear nuevos usuarios'),
(4, 'editar_usuario', 'Editar usuarios existentes'),
(5, 'eliminar_usuario', 'Eliminar usuarios'),
(6, 'ver_roles', 'Ver lista de roles'),
(7, 'editar_roles', 'Editar roles y sus permisos'),
(8, 'gestionar_inventario', 'Gestionar inventario de almacén'),
(9, 'crear_orden_almacen', 'Crear órdenes de almacén'),
(10, 'ver_ordenes_almacen', 'Ver órdenes de almacén'),
(11, 'gestionar_ordenes_produccion', 'Gestionar órdenes de producción'),
(12, 'ver_ordenes_produccion', 'Ver órdenes de producción');

CREATE TABLE `roles` (
  `id` int UNSIGNED NOT NULL,
  `nombre_rol` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion_rol` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `nombre_rol`, `descripcion_rol`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Admin', 'Acceso total al sistema', '2025-04-14 05:30:37', '2025-04-14 05:30:37'),
(2, 'Supervisor Almacen', 'Gestión completa del módulo de almacén', '2025-04-14 05:30:37', '2025-04-14 05:47:50'),
(3, 'Usuario Almacen', 'Funciones básicas del módulo de almacén', '2025-04-14 05:30:37', '2025-04-14 05:30:37'),
(4, 'Supervisor Produccion', 'Gestión completa del módulo de producción', '2025-04-14 05:30:37', '2025-04-14 05:30:37'),
(5, 'Usuario Produccion', 'Funciones básicas del módulo de producción', '2025-04-14 05:30:37', '2025-04-14 05:30:37');

CREATE TABLE `rol_permisos` (
  `id_rol` int UNSIGNED NOT NULL,
  `id_permiso` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rol_permisos` (`id_rol`, `id_permiso`) VALUES
(2, 8),
(2, 9),
(2, 10),
(3, 10);

CREATE TABLE `usuarios` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_rol` int UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `foto_perfil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` (`id`, `username`, `password`, `email`, `nombre`, `apellido`, `id_rol`, `activo`, `foto_perfil`, `ultimo_login`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'admin', '$2y$10$rUWergXHmUwg8ZbejX6t9u1YDJJwrr8IvBR5NzXWSNsdnwRiEkDty', 'gerardo.reyes@franke.com', 'Gerardo', 'Reyes', 1, 1, 'user_3_1744608381.jpg', NULL, '2025-04-14 03:53:46', '2025-04-14 05:30:48'),
(4, 'Gerardo', '$2y$10$HUhTbHK2GDtcNq4EmjKn1OUKEk4T.by6zx33GK3uQ54YUBm5pway.', NULL, 'Gerardo', 'Reyes', 5, 1, NULL, NULL, '2025-04-14 04:41:49', '2025-04-14 06:16:06'),
(5, 'almacen', '$2y$10$Pp1mJdhx.fxBiTFbJkYCdONzlj2b3vep2loSCaT9AEu8n5GRFNYH2', NULL, 'Almacen', '1', 3, 0, NULL, NULL, '2025-04-15 00:05:44', '2025-04-15 00:05:44'),
(6, 'Moi', '$2y$10$v1qzPCcDmKoDWL0tlMWeieSoYjJT8x0RZZJ3opek3GxTmJhoAWtL.', NULL, 'Moises', 'Armenta', 3, 1, NULL, NULL, '2025-04-15 02:53:16', '2025-04-15 02:53:24'),
(7, 'Ana', '$2y$10$wu4WaK1RB2aL.tZQCD66NONKiraU6Mfs3MEQaEiCN.y4Tf6nJHRDi', NULL, 'Ana', 'Leija', 2, 1, NULL, NULL, '2025-04-15 04:07:23', '2025-04-15 04:07:32');

CREATE TABLE `workorders` (
  `workorder` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_parte` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workorder_historial` (
  `id` bigint UNSIGNED NOT NULL,
  `workorder` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario_accion` int UNSIGNED DEFAULT NULL,
  `tipo_accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle_accion` text COLLATE utf8mb4_unicode_ci,
  `fecha_accion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra el historial de eventos y cambios para las Work Orders';

CREATE TABLE `workorder_status` (
  `id` int UNSIGNED NOT NULL,
  `workorder` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requiere_pickeo` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=SI, 0=NO',
  `estado_aprobacion_almacen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, APROBADA',
  `estado_pickeo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, EN_PROCESO, COMPLETO',
  `id_usuario_asignado` int UNSIGNED DEFAULT NULL COMMENT 'ID del usuario de la tabla usuarios asignado al pickeo',
  `solicitada_produccion` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=NO, 1=SI',
  `estado_entrega` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, ENTREGADA',
  `fecha_estado_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Almacena el estado del flujo de trabajo para cada Work Order';


ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave_permiso` (`clave_permiso`),
  ADD KEY `idx_clave_permiso` (`clave_permiso`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`id_rol`,`id_permiso`),
  ADD KEY `fk_rolpermiso_permiso` (`id_permiso`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

ALTER TABLE `workorders`
  ADD PRIMARY KEY (`workorder`);

ALTER TABLE `workorder_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_historial_workorder` (`workorder`),
  ADD KEY `idx_historial_tipo_accion` (`tipo_accion`),
  ADD KEY `idx_historial_usuario` (`id_usuario_accion`);

ALTER TABLE `workorder_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unq_workorder` (`workorder`),
  ADD KEY `idx_estado_aprobacion` (`estado_aprobacion_almacen`),
  ADD KEY `idx_estado_pickeo` (`estado_pickeo`),
  ADD KEY `idx_solicitada` (`solicitada_produccion`),
  ADD KEY `idx_estado_entrega` (`estado_entrega`),
  ADD KEY `fk_status_usuario_asignado` (`id_usuario_asignado`);


ALTER TABLE `permisos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `roles`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `usuarios`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `workorder_historial`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `workorder_status`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `fk_rolpermiso_permiso` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rolpermiso_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `workorder_historial`
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario_accion`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_workorder` FOREIGN KEY (`workorder`) REFERENCES `workorders` (`workorder`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `workorder_status`
  ADD CONSTRAINT `fk_status_usuario_asignado` FOREIGN KEY (`id_usuario_asignado`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_status_workorder` FOREIGN KEY (`workorder`) REFERENCES `workorders` (`workorder`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
