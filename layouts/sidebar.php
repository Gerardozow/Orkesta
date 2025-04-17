<?php
// layouts/sidebar.php - Barra lateral de navegación (Un solo nivel de submenús)

// Variables $active_page y $active_subpage definidas en el script principal

// Lógica de permisos (ejemplo)
$es_admin = (function_exists('tiene_rol') && tiene_rol('Admin'));
// $puede_ver_almacen = (function_exists('tiene_algun_rol') && tiene_algun_rol(['Admin', 'Supervisor Almacen'])); // Ejemplo
// ... definir otras ...

?>
<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>index.php">

            <img class="w-100" src="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>assets/img/logo.png" alt="Logo" class="logo">
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-header">
                Navegación
            </li>

            <li class="sidebar-item <?php echo (isset($active_page) && $active_page == 'dashboard') ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>index.php">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>

            <?php if ($es_admin): ?>
                <?php // La sección Usuarios está activa si $active_page es 'usuarios' 
                ?>
                <?php $is_user_section_active = (isset($active_page) && $active_page == 'usuarios'); ?>
                <li class="sidebar-item <?php echo $is_user_section_active ? 'active' : ''; ?>">
                    <a data-bs-target="#usuarios-nav" data-bs-toggle="collapse" class="sidebar-link <?php echo !$is_user_section_active ? 'collapsed' : ''; ?>">
                        <i class="align-middle" data-feather="users"></i> <span class="align-middle">Usuarios</span>
                    </a>

                    <ul id="usuarios-nav" class="sidebar-dropdown list-unstyled collapse <?php echo $is_user_section_active ? 'show' : ''; ?>" data-bs-parent="#sidebar">

                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'ver_usuarios') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>usuarios_ver.php">Ver Usuarios</a>
                        </li>
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'crear_usuario') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>usuarios_crear.php">Añadir Usuario</a>
                        </li>
                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'ver_roles') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>roles_ver.php">Roles</a>
                        </li>
                        <?php // Si creas roles_crear.php o roles_editar.php, puedes añadir enlaces aquí también 
                        ?>
                    </ul>
                </li>
            <?php endif; // Fin if $es_admin 
            ?>


            <?php // Implementa lógica de permisos 
            ?>
            <?php $roles_permitidos_almacen = ['Admin', 'Supervisor Almacen', 'Usuario Almacen']; ?>
            <?php if (function_exists('tiene_algun_rol') && tiene_algun_rol($roles_permitidos_almacen)): ?>
                <?php
                // Determinar si la sección o una subpágina está activa
                $subpages_almacen = ['cargar_wo', 'gestionar_ordenes', 'asignacion_pickeo', 'mis_pickeos'];
                $is_almacen_section_active = (isset($active_page) && $active_page == 'almacen') || (isset($active_subpage) && in_array($active_subpage, $subpages_almacen));
                ?>
                <li class="sidebar-item <?php echo $is_almacen_section_active ? 'active' : ''; // Activo si la sección o subpágina lo está 
                                        ?>">
                    <a data-bs-target="#almacen-nav"
                        data-bs-toggle="collapse"
                        class="sidebar-link <?php echo !$is_almacen_section_active ? 'collapsed' : ''; // No colapsado si está activo 
                                            ?>">
                        <i class="align-middle" data-feather="archive"></i> <span class="align-middle">Almacén</span>
                    </a>
                    <ul id="almacen-nav"
                        class="sidebar-dropdown list-unstyled collapse <?php echo $is_almacen_section_active ? 'show' : ''; // Mostrar si está activo 
                                                                        ?>"
                        data-bs-parent="#sidebar">

                        <?php // Enlaces para Admin/Supervisor 
                        ?>
                        <?php if (tiene_algun_rol(['Admin', 'Supervisor Almacen'])): ?>
                            <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'cargar_wo') ? 'active' : ''; ?>">
                                <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>almacen/cargar_wo.php">Cargar Work Orders</a>
                            </li>
                            <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'gestionar_ordenes') ? 'active' : ''; ?>">
                                <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>almacen/gestionar_ordenes.php">Gestionar Órdenes</a>
                            </li>
                            <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'asignacion_pickeo') ? 'active' : ''; ?>">
                                <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>almacen/asignacion_pickeo.php">Asignación Pickeo</a>
                            </li>
                        <?php endif; // Fin enlaces Admin/Supervisor 
                        ?>

                        <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'mis_pickeos') ? 'active' : ''; ?>">
                            <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>almacen/mis_pickeos.php">Mis Pickeos</a>
                        </li>

                        <?php // Aquí irían otros enlaces futuros de almacén 
                        ?>

                    </ul>
                </li>
            <?php endif; // Fin Sección Almacén 
            ?>


            <?php // Implementa lógica de permisos 
            ?>
            <?php // if (...): 
            ?>
            <?php $is_prod_section_active = (isset($active_page) && $active_page == 'produccion'); ?>
            <li class="sidebar-item <?php echo $is_prod_section_active ? 'active' : ''; ?>">
                <a data-bs-target="#produccion-nav" data-bs-toggle="collapse" class="sidebar-link <?php echo !$is_prod_section_active ? 'collapsed' : ''; ?>">
                    <i class="align-middle" data-feather="tool"></i> <span class="align-middle">Producción</span>
                </a>
                <ul id="produccion-nav" class="sidebar-dropdown list-unstyled collapse <?php echo $is_prod_section_active ? 'show' : ''; ?>" data-bs-parent="#sidebar">
                    <li class="sidebar-item <?php echo (isset($active_subpage) && $active_subpage == 'ordenes_prod') ? 'active' : ''; ?>">
                        <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>produccion_ordenes.php">Órdenes Prod.</a>
                    </li>
                    <?php // Otros enlaces de producción... 
                    ?>
                </ul>
            </li>
            <?php // endif; // Fin Sección Producción 
            ?>

            <li class="sidebar-item <?php echo (isset($active_page) && $active_page == 'perfil') ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>perfil.php">
                    <i class="align-middle" data-feather="user"></i> <span class="align-middle">Mi Perfil</span>
                </a>
            </li>

        </ul>
    </div>
</nav>