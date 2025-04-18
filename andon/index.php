<?php
// andon/index.php - Vista pública AJAX para pantalla Andon de Pickeo (Estilo Lista)
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
        <h1 class="display-5 text-center andon-title">
            <?php echo htmlspecialchars($page_title); ?>
        </h1>
         <div class="row" id="andon-content">
            <div id="andon-loader" class="andon-loader">Cargando datos...</div>
            <div class="col-md-4" id="andon-column-completa-1"></div>
            <div class="col-md-4" id="andon-column-completa-2"></div>
            <div class="col-md-4" id="andon-column-parcial">
                   
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
                <div class="andon-wo-item">
                    <div class="andon-wo">${escapeHtml(wo.workorder)}</div>
                    <div class="andon-np">${escapeHtml(wo.numero_parte)}</div>
                    <div class="andon-desc" title="${escapeHtml(wo.descripcion)}">${escapeHtml(descCorta)}</div>
                    <div class="andon-status ${statusClass}">${escapeHtml(statusText)}</div>
                </div>
            `;
        }
        /**
         * Función principal para obtener datos y actualizar la tabla Andon.
         */
        function actualizarAndon() {
            const url = '../ajax/get_andon_data.php'; // Ruta al script PHP
            const andonContent = document.getElementById('andon-content');
            const completa1 = document.getElementById('andon-column-completa-1');
            const completa2 = document.getElementById('andon-column-completa-2');
            const parcial = document.getElementById('andon-column-parcial');
             // Limpiar las columnas antes de agregar nuevos datos
            completa1.innerHTML = '';
            completa2.innerHTML = '';
            parcial.innerHTML = '';
             // Mostrar loader
             andonContent.innerHTML = '<div id="andon-loader" class="andon-loader">Cargando datos...</div>';
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
            })
                .then(data => {
                        // Ocultar el loader
                        document.getElementById('andon-loader')?.remove();

                        if (data.success && Array.isArray(data.data)) {
                        if (data.data.length === 0) {
                            andonContent.innerHTML = '<div class="text-center p-4">No hay Work Orders con pickeo Parcial o Completo pendientes de entrega.</div>';
                            return; // Salir temprano si no hay datos
                        }
                        let completa1Count = 0;
                        let completa2Count = 0;
                        data.data.forEach(wo => {
                            const fila = crearFilaAndon(wo);
                            if (wo.estado_pickeo === 'COMPLETO') {
                                if (completa1Count <= completa2Count) {
                                    completa1.innerHTML += fila;
                                    completa1Count++;
                                } else {
                                    completa2.innerHTML += fila;
                                    completa2Count++;
                                }
                            } else if (wo.estado_pickeo === 'PARCIAL') {
                                parcial.innerHTML += fila;
                            }
                            });
                            }else {
                                console.error("Error recibido del servidor Andon:", data.error || "Respuesta no exitosa");
                                andonContent.innerHTML = '<div class="text-center text-danger p-4">Error al cargar datos.</div>';
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