<?php
// almacen/historial_wo.php - Muestra el historial de una Work Order

require_once('../includes/load.php');
requerir_login();

// --- Permisos: Quién puede ver el historial? ---
// Asumamos que cualquiera que pueda ver la gestión de órdenes puede ver el historial
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen', 'Usuario Almacen', 'Supervisor Produccion', 'Usuario Produccion'])) {
    mensaje_sesion("No tienes permiso para ver el historial de órdenes.", "danger");
    redirigir_a('../index.php');
    exit;
}
// --- Fin Permisos ---

// --- Obtener WO de la URL ---
$wo_param = trim($_GET['wo'] ?? '');
if (empty($wo_param)) {
    mensaje_sesion("No se especificó la Work Order para ver el historial.", "warning");
    redirigir_a('index.php'); // Volver a la lista principal de gestión
}

// --- Obtener Datos del Historial ---
$lista_historial = [];
if (function_exists('buscar_historial_por_wo')) {
    $lista_historial = buscar_historial_por_wo($wo_param);
} else { /* Manejo error si función no existe */
}


// --- Variables de Plantilla ---
$page_title = 'Historial Work Order: ' . htmlspecialchars($wo_param);
$active_page = 'almacen';
// $active_subpage = 'historial_wo'; // Podría no ser necesario si vienes de otra página

include_once('../layouts/header.php');
?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3"><strong><?php echo $page_title; ?></strong></h1>
                <?php // Botón para volver a la página anterior o a la lista principal 
                ?>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary">
                    <i class="align-middle" data-feather="arrow-left"></i> <span class="align-middle">Volver a Home</span>
                </a>
            </div>

            <div class="px-3 px-lg-4 mb-3">
                <?php echo mostrar_mensaje_flash(); ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Eventos Registrados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla-historial-wo" class="table table-sm table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Tipo Acción</th>
                                    <th>Detalle / Comentario</th>
                                    <th>Realizado Por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lista_historial)): ?>
                                    <?php foreach ($lista_historial as $evento): ?>
                                        <tr>
                                            <td class="dt-nowrap"><?php echo htmlspecialchars(formatear_fecha($evento['fecha_accion'], 'd/m/Y H:i:s') ?? '-'); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($evento['tipo_accion']); ?></span></td>
                                            <td><?php echo nl2br(htmlspecialchars($evento['detalle_accion'] ?? '-')); // nl2br para saltos de línea en comentarios largos 
                                                ?></td>
                                            <td class="dt-nowrap"><?php echo htmlspecialchars($evento['nombre_usuario_accion'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No hay historial registrado para esta Work Order.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include_once('../layouts/footer.php'); ?>
</div>

<?php // --- Scripts JS --- 
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php // DataTables y extensiones cargadas desde footer.php 
?>
<script>
    $(document).ready(function() {
        $('#tabla-historial-wo').DataTable({
            responsive: true,
            dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            language: {
                /* ... Español ... */
            },
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'Todos']
            ],
            order: [
                [0, 'desc']
            ] // Ordenar por fecha descendente (más reciente primero)
        });
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>