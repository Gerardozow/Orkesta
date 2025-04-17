<footer class="footer">
    <div class="container-fluid">
        <div class="row text-muted">
            <div class="col-6 text-start">
                <p class="mb-0">
                    <a class="text-muted" href="#"><strong>WorkOrders Portal</strong></a> &copy; <?php echo date("Y"); ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <ul class="list-inline">
                    <li class="list-inline-item">
                        <a class="text-muted" href="#">Soporte</a>
                    </li>
                    <li class="list-inline-item">
                        <a class="text-muted" href="#">Ayuda</a>
                    </li>
                    <li class="list-inline-item">
                        <a class="text-muted" href="#">Privacidad</a>
                    </li>
                    <li class="list-inline-item">
                        <a class="text-muted" href="#">Términos</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>
</div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script> <?php // Para exportar a Excel 
                                                                                            ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script> <?php // Para exportar a PDF 
                                                                                            ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script> <?php // Fuentes para PDF 
                                                                                            ?>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script> <?php // Botones HTML5 (CSV, Excel, PDF) 
                                                                                            ?>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script> <?php // Botón Imprimir (opcional) 
                                                                                            ?>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>


<?php // --- Lógica para mostrar Mensajes Flash con SweetAlert2 --- 
?>
<?php if (isset($_SESSION['mensaje_flash'])): ?>
    <?php
    // Obtener datos del mensaje flash
    $flash_message = $_SESSION['mensaje_flash'];
    // ¡Importante! Eliminar el mensaje de la sesión para que no se muestre de nuevo
    unset($_SESSION['mensaje_flash']);

    // Preparar datos para JavaScript (escapando para seguridad)
    $swal_title = $flash_message['tipo'] === 'success' ? '¡Éxito!' : ($flash_message['tipo'] === 'danger' || $flash_message['tipo'] === 'error' ? 'Error' : 'Aviso'); // Título simple
    $swal_text = json_encode($flash_message['mensaje']); // Mensaje como texto JSON seguro
    $swal_icon = json_encode($flash_message['tipo']); // Tipo ('success', 'error', 'warning', 'info') como icono
    ?>
    <script>
        // Esperar a que el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Usar los datos de PHP para mostrar SweetAlert2
            Swal.fire({
                title: '<?php echo $swal_title; ?>', // Título simple
                text: <?php echo $swal_text; ?>, // Texto del mensaje (ya escapado por json_encode)
                icon: <?php echo $swal_icon; ?>, // Icono según el tipo
                confirmButtonText: 'Ok', // Texto del botón
                confirmButtonColor: '#3B7DDD' // Color primario de AdminKit (ajusta si es diferente)
                // Puedes añadir más opciones de SweetAlert2 aquí (timer, toast, etc.)
                // timer: 3000, // Cierra automáticamente después de 3 segundos
                // toast: true, position: 'top-end', showConfirmButton: false, // Estilo Toast
            });
        });
    </script>
<?php endif; // Fin if (isset($_SESSION['mensaje_flash'])) 
?>
<?php // --- Fin Lógica Mensajes Flash --- 
?>
</body>

</html>