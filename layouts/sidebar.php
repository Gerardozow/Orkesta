<?php
// layouts/sidebar.php - Barra lateral de navegación (Un solo nivel de submenús)

// Variables $active_page y $active_subpage definidas en el script principal

// --- Lógica de permisos ---
$es_admin = (function_exists('tiene_rol') && tiene_rol('Admin'));

// Roles que pueden ver la sección Almacén
$roles_permitidos_almacen = ['Admin', 'Supervisor Almacen', 'Usuario Almacen'];
$puede_ver_almacen = (function_exists('tiene_algun_rol') && tiene_algun_rol($roles_permitidos_almacen));

// Roles que pueden ver la sección Producción (Ajustar según necesidad)
$roles_permitidos_produccion = ['Admin', 'Supervisor Produccion', 'Usuario Produccion']; // Ejemplo
$puede_ver_produccion = (function_exists('tiene_algun_rol') && tiene_algun_rol($roles_permitidos_produccion));

// Roles que pueden ver la sección Reportes (Basado en la página entregas_dia.php)
$roles_permitidos_reportes = ['Admin', 'Supervisor Almacen', 'Supervisor Produccion'];
$puede_ver_reportes = (function_exists('tiene_algun_rol') && tiene_algun_rol($roles_permitidos_reportes));

// Helper para obtener BASE_URL o default
$base_url = (defined('BASE_URL') ? BASE_URL : '/');
?>
<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand" href="<?php echo $base_url; ?>index.php">
            <img class="w-100 p-2" src="<?php echo $base_url; ?>assets/img/logo.png" alt="Logo">
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-header">
                Navegación
            </li>

            <li class="sidebar-item <?php echo (isset($active_page) && $active_page == 'dashboard') ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $base_url; ?>index.php">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>

            <?php // --- Sección Usuarios (Solo Admin) --- ?>
            <?php if ($es_admin): ?>
                <?php $is_user_section_active = (isset($active_page) && $active_page == 'usuarios'); ?>
                <li class="sidebar-item <?php echo $is_user_section_active ? 'active' : ''; ?>">
                    <a data-bs-target="#usuarios-nav" data-bs-toggle="collapse" class="sidebar-link <?php echo !$is_user_section_active ? 'collapsed' : ''; ?>" aria-expanded="<?php echo $is_user_section_active ? 'true' : 'false'; ?>">
                        <i class="align-middle" data-feather="users"></i> <span class="align-middle">Usuarios</span>
                    </a>
                    <ul id="usuarios-nav" class="sidebar-dropdown list-unstyled collapse <?php echo $is_user_section_active ? 'show' : ''; ?>" data-bs-parent="#sidebar">
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'ver_usuarios') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo $base_url; ?>usuarios_ver.php">Ver Usuarios</a>
                        </li>
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'crear_usuario') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo $base_url; ?>usuarios_crear.php">Añadir Usuario</a>
                        </li>
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'ver_roles') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo $base_url; ?>roles_ver.php">Roles y Permisos</a>
                        </li>
                    </ul>
                </li>
            <?php endif; // Fin if $es_admin ?>

            <?php // --- Sección Almacén --- ?>
            <?php if ($puede_ver_almacen): ?>
                <?php
                // Subpáginas que activan la sección Almacén
                $subpages_almacen = ['cargar_wo', 'gestionar_ordenes', 'asignacion_pickeo', 'mis_pickeos'];
                // La sección está activa si la página activa es 'almacen' o si la subpágina activa pertenece a esta sección
                $is_almacen_section_active = (isset($active_page) && $active_page == 'almacen') || (isset($active_subpage) && in_array($active_subpage, $subpages_almacen));
                ?>
                <li class="sidebar-item <?php echo $is_almacen_section_active ? 'active' : ''; ?>">
                    <a data-bs-target="#almacen-nav" data-bs-toggle="collapse" class="sidebar-link <?php echo !$is_almacen_section_active ? 'collapsed' : ''; ?>" aria-expanded="<?php echo $is_almacen_section_active ? 'true' : 'false'; ?>">
                        <i class="align-middle" data-feather="archive"></i> <span class="align-middle">Almacén</span>
                    </a>
                    <ul id="almacen-nav" class="sidebar-dropdown list-unstyled collapse <?php echo $is_almacen_section_active ? 'show' : ''; ?>" data-bs-parent="#sidebar">

                        <?php // Enlaces visibles solo para Admin/Supervisor de Almacén ?>
                        <?php if (function_exists('tiene_algun_rol') && tiene_algun_rol(['Admin', 'Supervisor Almacen'])): ?>
                            <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'cargar_wo') ? 'active' : ''; ?>">
                                <a class="sidebar-link" href="<?php echo $base_url; ?>almacen/cargar_wo.php">Cargar Work Orders</a>
                            </li>
                            <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'gestionar_ordenes') ? 'active' : ''; ?>">
                                <a class="sidebar-link" href="<?php echo $base_url; ?>almacen/gestionar_ordenes.php">Gestionar Órdenes</a>
                            </li>
                            <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'asignacion_pickeo') ? 'active' : ''; ?>">
                                <a class="sidebar-link" href="<?php echo $base_url; ?>almacen/asignacion_pickeo.php">Asignación Pickeo</a>
                            </li>
                        <?php endif; ?>

                        <?php // Enlace visible para todos los roles de almacén ?>
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'mis_pickeos') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo $base_url; ?>almacen/mis_pickeos.php">Mis Pickeos</a>
                        </li>
                        <?php // Otros enlaces futuros de almacén ?>
                    </ul>
                </li>
            <?php endif; // Fin Sección Almacén ?>

            <?php // --- Sección Producción --- ?>
            <?php if ($puede_ver_produccion): ?>
                 <?php
                 $subpages_produccion = ['ordenes_prod']; // Añade aquí otras subpáginas de producción
                 $is_prod_section_active = (isset($active_page) && $active_page == 'produccion') || (isset($active_subpage) && in_array($active_subpage, $subpages_produccion));
                 ?>
                <li class="sidebar-item <?php echo $is_prod_section_active ? 'active' : ''; ?>">
                    <a data-bs-target="#produccion-nav" data-bs-toggle="collapse" class="sidebar-link <?php echo !$is_prod_section_active ? 'collapsed' : ''; ?>" aria-expanded="<?php echo $is_prod_section_active ? 'true' : 'false'; ?>">
                        <i class="align-middle" data-feather="tool"></i> <span class="align-middle">Producción</span>
                    </a>
                    <ul id="produccion-nav" class="sidebar-dropdown list-unstyled collapse <?php echo $is_prod_section_active ? 'show' : ''; ?>" data-bs-parent="#sidebar">
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'ordenes_prod') ? 'active' : ''; ?>">
                            <?php // Asegúrate que el archivo se llame produccion_ordenes.php o ajusta el href ?>
                            <a class="sidebar-link" href="<?php echo $base_url; ?>produccion/solicitar_material.php">Solicitar Material</a>
                        </li>
                        <?php // Otros enlaces de producción... ?>
                    </ul>
                </li>
            <?php endif; // Fin Sección Producción ?>

            <?php // --- Sección Reportes --- (NUEVA SECCIÓN) ?>
            <?php if ($puede_ver_reportes): ?>
                 <?php
                 $subpages_reportes = ['entregas_dia']; // Añade aquí otras subpáginas de reportes
                 $is_reportes_section_active = (isset($active_page) && $active_page == 'reportes') || (isset($active_subpage) && in_array($active_subpage, $subpages_reportes));
                 ?>
                <li class="sidebar-item <?php echo $is_reportes_section_active ? 'active' : ''; ?>">
                    <a data-bs-target="#reportes-nav" data-bs-toggle="collapse" class="sidebar-link <?php echo !$is_reportes_section_active ? 'collapsed' : ''; ?>" aria-expanded="<?php echo $is_reportes_section_active ? 'true' : 'false'; ?>">
                        <i class="align-middle" data-feather="bar-chart-2"></i> <span class="align-middle">Reportes</span>
                    </a>
                    <ul id="reportes-nav" class="sidebar-dropdown list-unstyled collapse <?php echo $is_reportes_section_active ? 'show' : ''; ?>" data-bs-parent="#sidebar">
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'entregas_dia') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo $base_url; ?>reportes/entregas_dia.php">Entregas del Día</a>
                        </li>
                        <?php // Otros enlaces de reportes... ?>
                        <?php // Ejemplo: <li class="sidebar-item ..."><a href="...">Otro Reporte</a></li> ?>
                    </ul>
                </li>
            <?php endif; // Fin Sección Reportes ?>


            <li class="sidebar-header">
                Cuenta
            </li>

            <li class="sidebar-item <?php echo (isset($active_page) && $active_page == 'perfil') ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $base_url; ?>perfil.php">
                    <i class="align-middle" data-feather="user"></i> <span class="align-middle">Mi Perfil</span>
                </a>
            </li>

             <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $base_url; ?>logout.php">
                    <i class="align-middle" data-feather="log-out"></i> <span class="align-middle">Cerrar Sesión</span>
                </a>
            </li>

        </ul>
    </div>
</nav>