<?php
// andon/andon_pick_status.php - Vista pública AJAX para pantalla Andon de Pickeo (Estilo Lista)
// Fecha de última revisión: 16 de Abril de 2025

require_once('../includes/load.php');
$page_title = 'Andon - Estado Pickeo';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?php echo htmlspecialchars($page_title); ?></title>

    <?php // Cargamos app.css para fuentes y CSS base de Bootstrap si es necesario 
    ?>
    <link href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>assets/css/app.css" rel="stylesheet">
    <link href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>assets/css/andon.css" rel="stylesheet">

</head>

<body class="andon-body">

    <div class="container-fluid p-3">
        <h1 class="display-5 text-center andon-title"><?php echo htmlspecialchars($page_title); ?></h1>

        <table id="andon-table">
            <thead>
                <tr>
                    <th class="col-wo">Work Order</th>
                    <th class="col-np">Num. Parte</th>
                    <th class="col-desc">Descripción</th>
                    <th class="col-status">Estado Pickeo</th>
                </tr>
            </thead>
            <tbody id="andon-table-body">
                <tr>
                    <td colspan="4">
                        <div id="andon-loader" class="andon-loader">Cargando datos...</div>
                    </td>
                </tr>
            </tbody>
        </table>

    </div> <?php // Incluir jQuery si no está globalmente o usar Fetch API puro 
            ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        /**
         * Escapa HTML para evitar XSS al insertar en el DOM.
         * @param {string} unsafe String potencialmente inseguro.
         * @returns {string} String escapado o '-' si es null/undefined.
         */
        const escapeHtml = (unsafe) => {
            if (unsafe === null || unsafe === undefined || unsafe === '') return '-';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        /**
         * Función para generar el HTML de una fila de la tabla Andon.
         * @param {object} wo Objeto con datos de la Work Order.
         * @returns {string} HTML de la fila <tr>.
         */
        function crearFilaAndon(wo) {
            let statusClass = '';
            let statusText = wo.estado_pickeo || 'DESCONOCIDO'; // Default

            if (statusText === 'PARCIAL') {
                statusClass = 'status-parcial';
                statusText = 'PARCIAL'; // Texto a mostrar
            } else if (statusText === 'COMPLETO') {
                statusClass = 'status-completo';
                statusText = 'COMPLETO'; // Texto a mostrar
            } else {
                statusClass = 'status-otro'; // Por si acaso
            }

            // Acortar descripción si es muy larga
            let descCorta = wo.descripcion || '';
            if (descCorta.length > 80) { // Ajusta el límite
                descCorta = descCorta.substring(0, 80) + '...';
            }

            return `
                <tr>
                    <td class="col-wo">${escapeHtml(wo.workorder)}</td>
                    <td class="col-np">${escapeHtml(wo.numero_parte)}</td>
                    <td class="col-desc" title="${escapeHtml(wo.descripcion)}">${escapeHtml(descCorta)}</td>
                    <td class="col-status ${statusClass}">${escapeHtml(statusText)}</td>
                </tr>
            `;
        }

        /**
         * Función principal para obtener datos y actualizar la tabla Andon.
         */
        function actualizarAndon() {
            const url = '../ajax/get_andon_data.php'; // Ruta al script PHP
            const andonTableBody = document.getElementById('andon-table-body');
            const loaderRow = `<tr><td colspan="4"><div class="andon-loader">Actualizando...</div></td></tr>`; // Loader para refresco

            // Mostrar loader temporalmente mientras se actualiza
            if (andonTableBody) andonTableBody.innerHTML = loaderRow;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        // Limpiar tabla
                        if (andonTableBody) andonTableBody.innerHTML = '';

                        // Llenar con nuevas filas
                        if (data.data.length > 0) {
                            data.data.forEach(wo => {
                                if (andonTableBody) andonTableBody.innerHTML += crearFilaAndon(wo);
                            });
                        } else {
                            // Mensaje si no hay WOs en estado Parcial o Completo
                            if (andonTableBody) andonTableBody.innerHTML = '<tr><td colspan="4" class="text-center p-4">No hay Work Orders con pickeo Parcial o Completo pendientes de entrega.</td></tr>';
                        }

                        // Reemplazar iconos Feather si se usaran
                        // if (typeof feather !== 'undefined') { feather.replace(); }

                    } else {
                        console.error("Error recibido del servidor Andon:", data.error || "Respuesta no exitosa");
                        if (andonTableBody) andonTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger p-4">Error al cargar datos.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error en fetch para Andon:', error);
                    if (andonTableBody) andonTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger p-4">Error de conexión. No se pudo actualizar.</td></tr>';
                });
        }

        // --- Ejecución al cargar y periódica ---
        document.addEventListener('DOMContentLoaded', function() {
            actualizarAndon(); // Cargar datos inmediatamente
            setInterval(actualizarAndon, 5000); // Actualizar cada 5 segundos
        });
    </script>

</body>

</html>