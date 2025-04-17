# Lista de Tareas Pendientes (TODO) - Portal WorkOrders

Este archivo registra las tareas, mejoras y funcionalidades pendientes para el proyecto.
_Última actualización: 14 de Abril de 2025_

## Completado / Parcialmente Completado

- [x] **Base de Datos Roles/Permisos:** Tablas `roles`, `permisos`, `rol_permisos` creadas; tabla `usuarios` modificada (`id_rol`, `rol` eliminada).
- [x] **Backend Roles/Permisos:** Funciones básicas en `sql.php`, `session.php`, `permissions.php` actualizadas/creadas para manejar `id_rol` y permisos en sesión.
- [x] **Gestión Usuarios:** Formularios (`usuarios_crear.php`, `usuarios_editar.php`) adaptados para usar desplegable de roles desde BD. Página `usuarios_editar.php` creada. Página `usuarios_ver.php` creada con activación/desactivación.
- [x] **Interfaz Roles (Vista/Edición Básica):** Creada `roles_ver.php` para listar roles. Creada `roles_editar.php` para editar nombre/descripción.
- [x] **Almacén - Carga WO:**
  - [x] Creada página `almacen/cargar_wo.php`.
  - [x] Creada tabla `workorders`.
  - [x] Implementada carga manual y por CSV (usando `upsert_workorder` con `ON DUPLICATE KEY UPDATE`).
  - [x] Añadida tabla DataTables para visualizar WOs cargadas.
  - [x] Añadidas extensiones DataTables: Botones (Excel, PDF, CSV), Responsive, LengthMenu.
  - [x] Posicionados botones de exportación en `card-header` (top-right).
  - [x] Ajustados permisos de acceso (Admin, Supervisor Almacen).
- [x] **Perfil de Usuario:** Funcionalidad completa (ver info, editar info, subir foto, cambiar contraseña).

## Seguridad y Permisos

- [ ] **Implementar Verificación de Permisos (Refinar):** Aplicar consistentemente `requerir_permiso()` o `tiene_permiso()` donde sea necesario (más allá de roles básicos).
  - [ ] Permisos específicos para acciones CRUD en usuarios (editar, activar/desactivar, eliminar).
  - [ ] Permisos para secciones/acciones futuras de Almacén y Producción.
  - [ ] Refinar visibilidad de botones y acciones en `usuarios_ver.php` (usar `tiene_permiso`).
  - [ ] Refinar visibilidad de menú/submenús en `layouts/sidebar.php` (usar `tiene_permiso`).
- [ ] **Protección CSRF:** Añadir tokens CSRF a todos los formularios POST que modifican datos.
- [ ] **Validación de Entradas (Mejorar):** Reforzar validación en servidor (formatos, complejidad contraseña, validación CSV más robusta, etc.).
- [ ] **Validación Cliente:** Añadir validación JavaScript a formularios.
- [ ] **Control de Acceso Fino:** Revisar lógica para prevenir edición/desactivación/eliminación de roles/usuarios críticos (ej. ID 1) o auto-acciones.

## Gestión de Usuarios

- [ ] **Implementar Eliminación de Usuarios:**
  - [ ] Crear `usuarios_eliminar.php` (script de procesamiento).
  - [ ] Confirmar estrategia (Desactivar vs. Borrar Físico - actualmente desactiva).
  - [ ] Añadir botón/formulario funcional en `usuarios_ver.php`.
- [ ] **Eliminar Foto de Perfil:** Añadir opción en `perfil.php` para volver a la imagen por defecto.
- [ ] **Reset de Contraseña (Admin):** Considerar si un Admin puede resetear la contraseña de otro usuario desde `usuarios_editar.php`.
- [ ] **Reset de Contraseña (Usuario):** Implementar flujo "Olvidé mi contraseña" (requiere envío de email, tokens).

## Gestión de Roles y Permisos (Interfaz)

- [ ] **Crear `roles_crear.php`:** Formulario para añadir nuevos roles (nombre, descripción).
- [ ] **Completar `roles_editar.php`:** Añadir la funcionalidad para asignar/desasignar permisos al rol (usando checkboxes/multi-select poblados desde `permisos` y guardando en `rol_permisos`).
- [ ] **Implementar Eliminación de Roles:**
  - [ ] Crear `roles_eliminar.php`.
  - [ ] Añadir lógica para verificar si el rol está en uso antes de permitir borrar.
  - [ ] Activar/modificar botón de eliminar en `roles_ver.php`.
- [ ] **Crear `permisos_ver.php` (Opcional):** Página para listar todos los permisos definidos.

## Módulo Almacén

- [ ] **Carga WO - Mejoras:**
  - [ ] Implementar lógica "no actualizar si no cambió" en `upsert_workorder` (requiere `SELECT` previo).
  - [ ] Mejorar feedback/manejo de errores en carga CSV (ej. indicar qué filas fallaron).
  - [ ] Añadir validación más específica para datos de WO (ej. formato `numero_parte`).
- [ ] **Inventario:** Crear página y lógica para gestionar inventario.
- [ ] **Órdenes Almacén:** Crear página y lógica para ver/gestionar órdenes específicas de almacén.

## Módulo Producción

- [ ] **Órdenes Producción:** Crear página y lógica para ver/gestionar órdenes de producción.

## Interfaz de Usuario (UI/UX)

- [ ] **Navbar Dinámico:** Hacer funcionales las secciones de Notificaciones y Mensajes.
- [ ] **Páginas Dedicadas (Notifications/Messages):** Crear páginas para ver históricos.
- [ ] **Confirmación Mejorada:** Usar Modales de Bootstrap en lugar de `confirm()` de JS.
- [ ] **Indicador "Cargando":** Añadir feedback visual durante acciones POST (especialmente CSV).

## Otros

- [ ] **Logging Robusto:** Implementar sistema de logging formal.
- [ ] **Actualizar `ultimo_login`:** Añadir lógica en `auth.php` -> `intentar_login`.
- [ ] **Poblar Tabla `permisos`:** Asegurarse de que la tabla `permisos` contenga todas las claves necesarias para la aplicación (ej. `'cargar_orden_almacen'`, `'ver_usuarios'`, etc.).
- [ ] **Asignar Permisos Iniciales:** Asegurarse de que los roles iniciales (Admin, Supervisores, etc.) tengan los permisos correctos asignados en la tabla `rol_permisos`.
