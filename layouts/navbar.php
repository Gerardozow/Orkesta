<?php
// layouts/navbar.php - Barra de navegación superior

$usuario_actual = (function_exists('obtener_datos_sesion')) ? obtener_datos_sesion() : null;
$nombre_usuario = $usuario_actual['nombre_completo'] ?? 'Usuario';
$id_usuario = $usuario_actual['id'] ?? null;

// --- Lógica para determinar la URL de la foto de perfil ---
$nombre_archivo_foto = $usuario_actual['foto_perfil'] ?? null;
$url_foto_perfil = '';
$url_foto_default = (defined('BASE_URL') ? BASE_URL : '/') . 'uploads/users/default.png';
if (!empty($nombre_archivo_foto)) {
    $url_foto_especifica = (defined('BASE_URL') ? BASE_URL : '/') . 'uploads/users/' . htmlspecialchars($nombre_archivo_foto);
    $ruta_fisica_foto = dirname(__DIR__) . '/uploads/users/' . $nombre_archivo_foto;
    $url_foto_perfil = file_exists($ruta_fisica_foto) ? $url_foto_especifica : $url_foto_default;
} else {
    $url_foto_perfil = $url_foto_default;
}

// --- Placeholders para Notificaciones / Mensajes ---
$num_notificaciones = 0; // Implementar lógica real
$num_mensajes = 0; // Implementar lógica real

?>
<nav class="navbar navbar-expand navbar-light navbar-bg">
    <a class="sidebar-toggle js-sidebar-toggle">
        <i class="hamburger align-self-center"></i>
    </a>

    <div class="navbar-collapse collapse">
        <ul class="navbar-nav navbar-align">

            <?php // --- Dropdown Notificaciones --- 
            ?>
            <li class="nav-item dropdown">
                <a class="nav-icon dropdown-toggle" href="#" id="alertsDropdown" data-bs-toggle="dropdown">
                    <div class="position-relative">
                        <i class="align-middle" data-feather="bell"></i>
                        <?php if ($num_notificaciones > 0) : ?>
                            <span class="indicator"><?php echo $num_notificaciones; ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="alertsDropdown">
                    <div class="dropdown-menu-header">
                        <?php echo $num_notificaciones; ?> Nuevas Notificaciones
                    </div>
                    <div class="list-group">
                        <span class="list-group-item">No hay notificaciones</span>
                    </div>
                    <div class="dropdown-menu-footer">
                        <a href="#" class="text-muted">Ver todas</a>
                    </div>
                </div>
            </li>

            <?php // --- Dropdown Mensajes --- 
            ?>
            <li class="nav-item dropdown">
                <a class="nav-icon dropdown-toggle" href="#" id="messagesDropdown" data-bs-toggle="dropdown">
                    <div class="position-relative">
                        <i class="align-middle" data-feather="message-square"></i>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="messagesDropdown">
                    <div class="dropdown-menu-header">
                        <div class="position-relative">
                            <?php echo $num_mensajes; ?> Nuevos Mensajes
                        </div>
                    </div>
                    <div class="list-group">
                        <span class="list-group-item">No hay mensajes</span>
                    </div>
                    <div class="dropdown-menu-footer">
                        <a href="#" class="text-muted">Ver todos los mensajes</a>
                    </div>
                </div>
            </li>

            <?php // --- Dropdown Perfil Usuario --- 
            ?>
            <li class="nav-item dropdown">
                <a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown">
                    <i class="align-middle" data-feather="settings"></i>
                </a>
                <a class="nav-link dropdown-toggle d-none d-sm-inline-block" href="#" data-bs-toggle="dropdown">
                    <img src="<?php echo $url_foto_perfil; ?>" class="avatar img-fluid rounded me-1" alt="<?php echo htmlspecialchars($nombre_usuario); ?>" />
                    <span class="text-dark"><?php echo htmlspecialchars($nombre_usuario); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>perfil.php">
                        <i class="align-middle me-1" data-feather="user"></i> Mi Perfil
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>logout.php">
                        <i class="align-middle me-1" data-feather="log-out"></i> Cerrar Sesión
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>