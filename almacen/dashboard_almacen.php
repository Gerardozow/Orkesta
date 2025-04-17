<?php
// En index.php (o tu página de dashboard)

require_once('includes/load.php');
requerir_login();

// Obtener rol para mostrar contenido condicional
$current_user_rol = obtener_rol_usuario_actual();

// Variables para las tarjetas (inicializar)
$count_pendientes = 0;
$count_en_proceso = 0;
$count_entregadas_hoy = 0;
$mostrar_tarjetas_almacen = false; // Flag para saber si mostrar las tarjetas

// Verificar si el usuario tiene rol de almacén para cargar los datos
if (tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen'])) {
    $mostrar_tarjetas_almacen = true;
    // Llamar a las funciones de conteo
    if (function_exists('contar_wos_pendientes_pickeo')) $count_pendientes = contar_wos_pendientes_pickeo();
    if (function_exists('contar_wos_en_proceso_pickeo')) $count_en_proceso = contar_wos_en_proceso_pickeo();
    if (function_exists('contar_wos_entregadas_hoy')) $count_entregadas_hoy = contar_wos_entregadas_hoy();
}

// --- Variables de Plantilla ---
$page_title = 'Dashboard Principal';
$active_page = 'dashboard';

include_once('layouts/header.php');
?>

<?php include_once('layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong>Dashboard</strong> Principal</h1>

            <?php // Mostrar mensajes flash si los hay 
            ?>
            <div class="px-3 px-lg-4 mb-3">
                <?php if (function_exists('mostrar_mensaje_flash')) echo mostrar_mensaje_flash(); ?>
            </div>

            <?php // --- Mostrar Tarjetas de Almacén (si aplica) --- 
            ?>
            <?php if ($mostrar_tarjetas_almacen): ?>
                <div class="row">
                    <div class="col-sm-6 col-lg-4"> <?php // Ajusta clases de columna según prefieras (ej. col-md-4) 
                                                    ?>
                        <?php // Incluir componente card (la variable $count_pendientes ya está definida) 
                        ?>
                        <?php include_once('layouts/components/cards/card_pendientes_pickeo.php'); ?>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <?php // Incluir componente card (la variable $count_en_proceso ya está definida) 
                        ?>
                        <?php include_once('layouts/components/cards/card_en_proceso_pickeo.php'); ?>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <?php // Incluir componente card (la variable $count_entregadas_hoy ya está definida) 
                        ?>
                        <?php include_once('layouts/components/cards/card_entregadas_hoy.php'); ?>
                    </div>
                </div><?php endif; ?>
            <?php // --- Fin Tarjetas Almacén --- 
            ?>


            <?php // --- Aquí puedes añadir otras tarjetas o contenido del dashboard --- 
            ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            Contenido principal del dashboard...
                        </div>
                    </div>
                </div>
            </div>
            <?php // --- Fin otro contenido --- 
            ?>


        </div>
    </main>

    <?php include_once('layouts/footer.php'); ?>
</div> <?php // Fin .main 
        ?>

<?php // Scripts específicos de esta página (si los hubiera) 
?>