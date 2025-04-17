<?php
// Componente Card: En Espera Entrega
// Espera $count_en_espera
$count = $count_en_espera ?? 0;
?>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col mt-0">
                <h5 class="card-title">En Espera Entrega</h5>
            </div>
            <div class="col-auto">
                <div class="stat text-info">
                    <?php // Icono: paquete o envío 
                    ?>
                    <i class="align-middle" data-feather="package"></i>
                </div>
            </div>
        </div>
        <h1 class="mt-1 mb-3" id="count-en-espera"><?php echo number_format($count_en_espera ?? 0); ?></h1>
        <div class="mb-0">
            <span class="badge badge-info-light">Listas para entregar a Prod.</span>
            <?php // Enlace opcional a una vista filtrada 
            ?>
        </div>
    </div>
</div>