<?php
// andon/andon_pick_status.php - Vista pública AJAX para pantalla Andon de Pickeo
// Estilo: Grid 4 Columnas, Layout Interno Horizontal Compacto
// Fecha de última revisión: 21 de Abril de 2025

require_once('../includes/load.php');
$page_title = 'Andon - Estado Pickeo';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php // Opcional: Refresco fallback si JS falla. El intervalo JS es preferible. 
    ?>
    <?php // <meta http-equiv="refresh" content="5"> 
    ?>

    <title><?php echo htmlspecialchars($page_title); ?></title>

    <?php // Carga CSS base y específico de Andon 
    ?>
    <link href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>assets/css/app.css" rel="stylesheet">
    <link href="<?php echo (defined('BASE_URL') ? BASE_URL : '/'); ?>assets/css/andon.css" rel="stylesheet">

</head>

<body class="andon-body">

    <div class="container-fluid p-3">
        <?php // Título principal 
        ?>
        <h1 class="display-4 text-center andon-title mb-3"><?php echo htmlspecialchars($page_title); ?></h1>

        <?php // Contenedor principal para las tarjetas del Andon (usará CSS Grid) 
        ?>
        <div id="andon-grid-container" class="andon-grid">
            <?php // Loader inicial (será reemplazado por JS) 
            ?>
            <div id="andon-loader" class="andon-loader">Cargando datos...</div>
        </div>

    </div>

    <script>
        /**
         * Escapa HTML para evitar XSS al insertar en el DOM.
         * @param {string|number|null|undefined} unsafe String o valor potencialmente inseguro.
         * @returns {string} String escapado o '-' si es null/undefined/vacío.
         */
        const escapeHtml = (unsafe) => {
            if (unsafe === null || unsafe === undefined || unsafe === '') return '-';
            // Convertir a string antes de reemplazar por si llega un número
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        /**
         * Función para generar el HTML de una "tarjeta" Andon
         * (Versión: Compacto Horizontal, NP/Desc Agrupados Verticalmente, Estado Inline, Borde por Estado).
         * @param {object} wo Objeto con datos de la Work Order.
         * @returns {string} HTML del div de la tarjeta.
         */
        function crearAndonCard(wo) {
            let statusClass = '';
            let statusText = wo.estado_pickeo || 'DESCONOCIDO';

            // Determinar clase y texto según el estado
            switch (statusText.toUpperCase()) {
                case 'PARCIAL':
                    statusClass = 'status-parcial';
                    statusText = 'PARCIAL';
                    break;
                case 'COMPLETO':
                    statusClass = 'status-completo';
                    statusText = 'COMPLETO';
                    break;
                default:
                    statusClass = 'status-otro';
            }

            // Acortar descripción (Ajusta el límite)
            // Puede que ahora tengas un poco más de espacio horizontal para esto
            let descCorta = wo.descripcion || '';
            const limiteDesc = 45; // Ajusta según pruebas
            if (descCorta.length > limiteDesc) {
                descCorta = descCorta.substring(0, limiteDesc) + '...';
            }
            // Acortar número de parte si es necesario
            let npCorto = wo.numero_parte || '';
            const limiteNp = 30; // Ajusta si tus NP son muy largos
            if (npCorto.length > limiteNp) {
                npCorto = npCorto.substring(0, limiteNp) + '...';
            }


            // Estructura HTML actualizada:
            // - Se añade <div class="andon-card__part-info"> para agrupar NP y Desc
            // - NP y Desc van dentro de ese nuevo div
            return `
                <div class="andon-card ${statusClass}">
                    <div class="andon-card__main-info">
                        <div class="andon-card__wo" title="Work Order: ${escapeHtml(wo.workorder)}">${escapeHtml(wo.workorder)}</div>
                        <div class="andon-card__part-info">
                            <div class="andon-card__np" title="Num. Parte: ${escapeHtml(wo.numero_parte)}">${escapeHtml(npCorto)}</div>
                            <div class="andon-card__desc" title="Descripción: ${wo.descripcion}">${descCorta}</div>
                        </div>
                        <div class="andon-card__status">${escapeHtml(statusText)}</div>
                    </div>
                </div>
            `;
        }


        /**
         * Función principal para obtener datos y actualizar el grid Andon.
         */
        function actualizarAndon() {
            const url = '../ajax/get_andon_data.php'; // Ruta al script PHP backend
            const andonGridContainer = document.getElementById('andon-grid-container');
            const loaderHtml = `<div class="andon-loader">Actualizando...</div>`; // Loader para refresco

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        // Incluir status text en el error para más info
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
                    }
                    // Verificar content-type por si el servidor devuelve error HTML en lugar de JSON
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        throw new TypeError(`Esperaba JSON pero recibió ${contentType}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Limpiar el contenedor antes de añadir nuevas tarjetas
                    if (andonGridContainer) andonGridContainer.innerHTML = '';

                    if (data.success && Array.isArray(data.data)) {
                        if (data.data.length > 0) {
                            // Llenar con nuevas tarjetas
                            data.data.forEach(wo => {
                                if (andonGridContainer) andonGridContainer.innerHTML += crearAndonCard(wo);
                            });
                        } else {
                            // Mensaje si no hay WOs relevantes
                            if (andonGridContainer) andonGridContainer.innerHTML = '<div class="andon-empty-message">No hay Work Orders con pickeo Parcial o Completo pendientes.</div>';
                        }
                    } else {
                        // Si success es false o data no es array, mostrar error del servidor si existe
                        console.error("Error lógico del servidor Andon:", data.error || "Respuesta no exitosa o formato incorrecto");
                        if (andonGridContainer) andonGridContainer.innerHTML = `<div class="andon-error-message">Error al obtener datos: ${escapeHtml(data.error || 'Formato inválido')}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error en fetch o procesamiento para Andon:', error);
                    // Mostrar error de conexión o procesamiento genérico
                    // Evitar blanquear pantalla si ya había datos y fue error de red temporal
                    if (andonGridContainer && (!andonGridContainer.hasChildNodes() || andonGridContainer.querySelector('.andon-loader'))) {
                        andonGridContainer.innerHTML = `<div class="andon-error-message">Error de conexión o procesamiento: ${escapeHtml(error.message)}. Verifique la consola.</div>`;
                    } else if (andonGridContainer && !andonGridContainer.querySelector('.andon-error-message')) {
                        // Opcional: Añadir un pequeño indicador de error sin borrar todo
                        // const errorIndicator = document.createElement('div');
                        // errorIndicator.className = 'andon-update-error-indicator';
                        // errorIndicator.textContent = 'Error al actualizar';
                        // andonGridContainer.appendChild(errorIndicator); // Requiere CSS para este indicador
                        console.warn("Error de actualización, mostrando datos anteriores.");
                    }
                });
        }

        // --- Ejecución al cargar y periódica ---
        document.addEventListener('DOMContentLoaded', function() {
            actualizarAndon(); // Cargar datos inmediatamente

            // Actualizar cada 5 segundos (5000 ms)
            setInterval(actualizarAndon, 5000);
        });
    </script>

</body>

</html>