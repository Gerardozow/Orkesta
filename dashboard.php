<?php
require_once('includes/load.php');

// Lógica de permisos (ejemplo)
// requerir_login(); 
// if (function_exists('tiene_permiso') && !tiene_permiso('ver_dashboard')) { 
//     if(function_exists('mensaje_sesion')) mensaje_sesion('Acceso denegado al dashboard.', 'danger');
//     if(function_exists('redirigir_a')) redirigir_a('home.php'); 
//     exit;
// }

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

            <?php
            if (function_exists('mostrar_mensaje_flash')) {
                echo mostrar_mensaje_flash();
            }
            ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            ¡Bienvenido al portal! Aquí irá el contenido principal del dashboard.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include_once('layouts/footer.php'); ?>


    <?php // Scripts JS específicos para esta página van aquí 
    ?>

    <?php // Fin del archivo 
    ?>