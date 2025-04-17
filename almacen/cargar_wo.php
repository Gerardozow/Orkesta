<?php
// almacen/cargar_wo.php - Página para cargar Work Orders (Manual y CSV)

// Carga inicial (subiendo un nivel para encontrar la carpeta includes)
require_once('../includes/load.php');

// Asegura que el usuario esté logueado
if (function_exists('requerir_login')) {
    requerir_login();
} else {
    die("Error: Sistema de autenticación no disponible.");
}

// --- Verificación de Rol: Solo Admin y Supervisor Almacen pueden acceder ---
if (!function_exists('tiene_algun_rol') || !tiene_algun_rol(['Admin', 'Supervisor Almacen'])) {
    if (function_exists('mensaje_sesion')) mensaje_sesion("No tienes permiso para acceder a la carga de Work Orders.", "danger");
    if (function_exists('redirigir_a')) redirigir_a('../index.php'); // Redirigir a la raíz
    exit;
}
// --- Fin Verificación de Rol ---

// --- Obtener Work Orders existentes para la tabla ---
$lista_wos = [];
if (function_exists('buscar_todas_las_workorders')) {
    $lista_wos = buscar_todas_las_workorders(); // Función de sql.php
} else {
    error_log("Error: Función buscar_todas_las_workorders() no encontrada.");
    if (function_exists('mensaje_sesion')) mensaje_sesion("Error al cargar la lista de Work Orders existentes.", "danger");
}

// Variables para la plantilla
$page_title = 'Cargar Work Orders';
$active_page = 'almacen'; // Para el menú principal del sidebar
$active_subpage = 'cargar_wo'; // Para el submenú
$form_action = $_POST['form_action'] ?? null; // Identifica qué formulario se envió

// --- Procesar Formularios POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $should_redirect = true; // Asumimos redirección, la cancelamos si hay error de validación manual

    // --- A. Procesar Carga Manual ---
    if ($form_action === 'manual_entry') {
        $wo_manual = [
            'workorder'     => trim($_POST['manual_wo'] ?? ''),
            'numero_parte'  => trim($_POST['manual_np'] ?? ''),
            'descripcion'   => trim($_POST['manual_desc'] ?? '')
        ];

        if (empty($wo_manual['workorder'])) {
            if (function_exists('mensaje_sesion')) mensaje_sesion("El campo Work Order es obligatorio para la carga manual.", "warning");
            $should_redirect = false; // No redirigir para mostrar error
        } else {
            if (upsert_workorder($wo_manual)) { // Llama a la función de sql.php
                if (function_exists('mensaje_sesion')) mensaje_sesion("Work Order '" . htmlspecialchars($wo_manual['workorder']) . "' procesada manualmente.", "success");
            } else {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Error al procesar manualmente la WO '" . htmlspecialchars($wo_manual['workorder']) . "'.", "danger");
            }
        }
    } // --- Fin Carga Manual ---

    // --- B. Procesar Subida de CSV ---
    // Este bloque va dentro del if ($_SERVER['REQUEST_METHOD'] === 'POST')
    // $form_action debe haber sido definido antes (ej. $form_action = $_POST['form_action'] ?? null;)
    // $should_redirect debe existir y ser true por defecto para este bloque

    elseif ($form_action === 'upload_csv') {

        // Verificar si se subió el archivo y si no hubo errores iniciales de PHP/Servidor
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {

            // Obtener información básica del archivo subido
            $file_tmp_path = $_FILES['csv_file']['tmp_name']; // Ruta temporal donde PHP guarda el archivo
            $file_name     = $_FILES['csv_file']['name'];     // Nombre original del archivo
            $file_ext      = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)); // Extensión en minúsculas

            // Validar que la extensión sea .csv
            if ($file_ext !== 'csv') {
                if (function_exists('mensaje_sesion')) mensaje_sesion("Error: El archivo debe ser de tipo CSV.", "danger");
                // $should_redirect se mantiene true, se redirigirá para mostrar el mensaje
            } else {
                // La extensión es correcta, intentar procesar el archivo
                $contador_filas_procesadas = 0; // Filas que intentamos guardar
                $exitos = 0;                    // Filas guardadas/actualizadas con éxito
                $errores = 0;                   // Filas con error (WO vacía, no empieza con número, fallo BD)
                $fila_actual = 0;               // Contador de líneas leídas del archivo
                $lineas_a_saltar = 21;           // Líneas de encabezado/basura a ignorar al inicio - AJUSTA SI CAMBIA TU ARCHIVO

                // Intentar abrir el archivo CSV en modo lectura ('r')
                if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {

                    // Saltar las líneas iniciales no deseadas
                    for ($i = 0; $i < $lineas_a_saltar; $i++) {
                        if (fgets($handle) === false) {
                            $errores++; // Marcar error si el archivo es muy corto
                            error_log("Error: Archivo CSV (" . $file_name . ") demasiado corto, no se pudieron saltar las líneas de encabezado.");
                            break;
                        }
                        $fila_actual++;
                    }

                    // Leer el resto del archivo línea por línea, interpretando como CSV
                    // Continuar solo si no hubo error al saltar encabezados
                    while ($errores == 0 && ($datos_fila = fgetcsv($handle, 2000, ",")) !== FALSE) { // Lee hasta 2000 caracteres por línea, delimitador coma
                        $fila_actual++;

                        // Omitir filas totalmente vacías que fgetcsv podría devolver
                        if (empty(array_filter($datos_fila))) continue;

                        // Obtener los valores de las columnas específicas (A=0, C=2, F=5) y limpiar espacios
                        $wo_val   = trim($datos_fila[0] ?? '');
                        $np_val   = trim($datos_fila[2] ?? '');
                        $desc_val = trim($datos_fila[5] ?? '');

                        // Condición de parada: Si la WO está vacía o es la fila de totales "All"
                        if (empty($wo_val) || strtolower($wo_val) === 'all') {
                            break; // Terminar de leer el archivo
                        }

                        // Validación: Asegurar que la Work Order empieza con un dígito numérico
                        if (!preg_match('/^\d/', $wo_val)) {
                            $errores++;
                            error_log("Error: Work Order en línea CSV $fila_actual no empieza con número y fue omitida: '" . $wo_val . "'");
                            continue; // Saltar esta fila e ir a la siguiente
                        }

                        // Si pasa las validaciones, contarla como procesada
                        $contador_filas_procesadas++;

                        // Preparar datos para la base de datos
                        $wo_data = [
                            'workorder'     => $wo_val,
                            'numero_parte'  => $np_val,
                            'descripcion'   => $desc_val
                        ];

                        // Intentar insertar o actualizar en la BD usando la función upsert
                        if (function_exists('upsert_workorder') && upsert_workorder($wo_data)) {
                            $exitos++; // Contar éxito
                        } else {
                            $errores++; // Contar error
                            error_log("Error procesando WO (upsert) del CSV en línea aprox. $fila_actual: " . $wo_val);
                        }
                    } // Fin del bucle while fgetcsv

                    fclose($handle); // Cerrar el archivo CSV

                    // Crear mensaje de resumen para el usuario
                    if ($contador_filas_procesadas > 0 || $errores > 0) {
                        $msg = "Archivo CSV procesado. Filas de datos intentadas: $contador_filas_procesadas. ";
                        $msg .= "Éxitos (Insert/Update): $exitos. Errores/Omitidas: $errores.";
                        if ($errores > 0) {
                            if (function_exists('mensaje_sesion')) mensaje_sesion($msg . " Revisa los logs de errores para más detalles.", "warning");
                        } else {
                            if (function_exists('mensaje_sesion')) mensaje_sesion($msg, "success");
                        }
                    } elseif ($errores == 0) {
                        // No se procesó nada y no hubo error (ej. archivo vacío después de encabezados)
                        if (function_exists('mensaje_sesion')) mensaje_sesion("No se encontraron filas de datos válidas en el archivo CSV.", "info");
                    }
                    // Si hubo error al saltar encabezados, $errores > 0 y se mostrará mensaje warning

                } else {
                    // Error al intentar abrir el archivo subido
                    if (function_exists('mensaje_sesion')) mensaje_sesion("Error: No se pudo abrir el archivo CSV subido.", "danger");
                    error_log("Error fopen() en cargar_wo.php para archivo: " . $file_tmp_path);
                }
            } // Fin validación extensión .csv

            // --- Manejo de otros errores de subida de PHP ---
        } elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] != UPLOAD_ERR_NO_FILE) {
            if (function_exists('mensaje_sesion')) mensaje_sesion("Error durante la subida del archivo CSV. Código PHP: " . $_FILES['csv_file']['error'], "danger");
            error_log("Error de subida PHP en cargar_wo.php: Code " . $_FILES['csv_file']['error']);
        } else {
            // No se seleccionó ningún archivo
            if (function_exists('mensaje_sesion')) mensaje_sesion("Por favor, selecciona un archivo CSV para subir.", "warning");
        }

        // La redirección se maneja fuera de este bloque elseif (al final del bloque POST)
        // $should_redirect sigue siendo true aquí por defecto

    } // --- Fin elseif ($form_action === 'upload_csv') ---



    // --- Redirección final ---
    if ($should_redirect) {
        if (function_exists('redirigir_a')) redirigir_a('cargar_wo.php'); // Redirige a sí mismo para mostrar mensaje
        exit;
    }
} // --- Fin POST ---


// Incluir layout (ajustando rutas ../)
include_once('../layouts/header.php');
?>
<?php // Añadir CSS de DataTables aquí si NO está en header.php global 
?>
<?php /*
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"> 
*/ ?>

<?php include_once('../layouts/sidebar.php'); ?>

<div class="main">
    <?php include_once('../layouts/navbar.php'); ?>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong><?php echo htmlspecialchars($page_title); ?></strong></h1>

            <div class="row">
                <div class="col-12">
                    <div class="px-0 px-lg-1 mb-3"> <?php // Quitado padding extra si la función ya no lo tiene 
                                                    ?>
                        <?php
                        if (function_exists('mostrar_mensaje_flash')) {
                            echo mostrar_mensaje_flash();
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <div class="card h-100"> <?php // h-100 para intentar igualar alturas 
                                                ?>
                        <div class="card-header">
                            <h5 class="card-title">Carga Manual</h5>
                            <h6 class="card-subtitle text-muted">Añade o actualiza una Work Order individualmente.</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="cargar_wo.php">
                                <input type="hidden" name="form_action" value="manual_entry">

                                <div class="mb-3">
                                    <label for="manual_wo" class="form-label">Work Order <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="manual_wo" id="manual_wo" required value="<?php // No repoblar WO en carga manual por si hubo error 
                                                                                                                            ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="manual_np" class="form-label">Número de Parte</label>
                                    <input type="text" class="form-control" name="manual_np" id="manual_np" value="<?php // No repoblar 
                                                                                                                    ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="manual_desc" class="form-label">Descripción</label>
                                    <input type="text" class="form-control" name="manual_desc" id="manual_desc" value="<?php // No repoblar 
                                                                                                                        ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Guardar WO Manual</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card h-100"> <?php // h-100 para intentar igualar alturas 
                                                ?>
                        <div class="card-header">
                            <h5 class="card-title">Carga Masiva desde CSV</h5>
                            <h6 class="card-subtitle text-muted">Columnas: Workorder, NumeroParte, Descripcion (en ese orden). Se ignora la primera fila (encabezado).</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="cargar_wo.php" enctype="multipart/form-data">
                                <input type="hidden" name="form_action" value="upload_csv">
                                <div class="mb-3">
                                    <label for="csv_file_upload" class="form-label">Archivo CSV <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="csv_file" id="csv_file_upload" accept=".csv" required>
                                    <small class="form-text text-muted">Delimitado por comas (,). Codificación UTF-8 recomendada.</small>
                                </div>
                                <button type="submit" class="btn btn-success">Subir y Procesar CSV</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="">
                                <h5 class="card-title">Work Orders Cargadas</h5>
                                <h6 class="card-subtitle text-muted">Lista de Work Orders registradas.</h6>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabla-workorders" class="table table-striped table-hover" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Work Order</th>
                                            <th>Número Parte</th>
                                            <th>Descripción</th>
                                            <th>Última Actualización</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($lista_wos)): ?>
                                            <?php foreach ($lista_wos as $wo): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($wo['workorder']); ?></td>
                                                    <td><?php echo htmlspecialchars($wo['numero_parte'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($wo['descripcion'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars(function_exists('formatear_fecha') ? formatear_fecha($wo['fecha_actualizacion'], 'd/m/Y H:i:s') : $wo['fecha_actualizacion']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No hay Work Orders cargadas todavía.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include_once('../layouts/footer.php'); // Incluir Footer 
    ?>
</div>
<?php // Al final de almacen/cargar_wo.php, DESPUÉS del footer y los includes JS de DataTables/Buttons/Responsive 
?>

<script>
    $(document).ready(function() {
        // Inicializar DataTable SIN la 'B' de botones en el DOM por defecto
        var table = $('#tabla-workorders').DataTable({
            responsive: true,
            dom: 'lfrtip', // Quitamos la 'B': l=Length, f=filtro, r=procesando, t=tabla, i=info, p=paginación
            language: { // Español
                lengthMenu: "Mostrar _MENU_ registros",
                zeroRecords: "No se encontraron Work Orders",
                info: "Página _PAGE_ de _PAGES_ (_TOTAL_ registros)",
                infoEmpty: "No hay registros",
                infoFiltered: "(filtrado de _MAX_ registros)",
                search: "Buscar:",
                paginate: {
                    first: "Primero",
                    last: "Último",
                    next: "Siguiente",
                    previous: "Anterior"
                },
                aria: {
                    sortAscending: ": Ordenar ascendente",
                    sortDescending: ": Ordenar descendente"
                }
            },
            lengthMenu: [ // Opciones de longitud
                [10, 25, 50, -1],
                ['10 registros', '25 registros', '50 registros', 'Mostrar Todos']
            ],
            order: [
                [3, 'desc']
            ] // Orden inicial
            // Ya NO definimos los botones aquí
        });

        // Inicializar los Botones por separado y asociarlos a la tabla 'table'
        new $.fn.dataTable.Buttons(table, {
            buttons: [ // Definimos los botones aquí
                {
                    extend: 'excelHtml5',
                    text: '<i class="align-middle" data-feather="file"></i> Excel',
                    className: 'btn btn-success btn-sm ms-1', // ms-1 añade un pequeño margen izquierdo
                    titleAttr: 'Exportar a Excel'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="align-middle" data-feather="file-text"></i> PDF',
                    className: 'btn btn-danger btn-sm ms-1',
                    titleAttr: 'Exportar a PDF',
                    orientation: 'landscape'
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="align-middle" data-feather="save"></i> CSV',
                    className: 'btn btn-secondary btn-sm ms-1',
                    titleAttr: 'Exportar a CSV'
                }
                // Podrías añadir 'copy', 'print' aquí si quieres
            ]
        });

        // Mover el contenedor de botones al card-header deseado
        // Selecciona el div que contiene la tabla, sube al .card, busca el .card-header dentro de él y añade los botones.
        var buttonContainer = table.buttons().container();
        buttonContainer.appendTo($('#tabla-workorders').closest('.card').find('.card-header'));

        // Añadir clase float-end de Bootstrap para alinear a la derecha
        buttonContainer.addClass('float-end');

        // Volver a inicializar los iconos Feather (importante para los iconos en los botones nuevos)
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

    }); // Fin $(document).ready
</script>

<?php // Fin del archivo almacen/cargar_wo.php 
?>