<?php
// Componente Card: Pendientes de Pickeo
$count = $count_pendientes ?? 0;
?>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col mt-0">
                <h5 class="card-title">Pendientes Pickeo</h5>
            </div>
            <div class="col-auto">
                <div class="stat text-warning">
                    <i class="align-middle" data-feather="clock"></i>
                </div>
            </div>
        </div>
        <h1 class="mt-1 mb-3" id="count-pendientes"><?php echo number_format($count_pendientes ?? 0); ?></h1>
        <div class="mb-0">
            <span class="badge badge-warning-light">Listas para asignar/iniciar</span>
        </div>
    </div>
</div>