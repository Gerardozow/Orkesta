<?php
// Componente Card: Pickeo en Proceso
$count = $count_en_proceso ?? 0;
?>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col mt-0">
                <h5 class="card-title">Pickeo En Proceso</h5>
            </div>
            <div class="col-auto">
                <div class="stat text-primary">
                    <i class="align-middle" data-feather="truck"></i>
                </div>
            </div>
        </div>
        <h1 class="mt-1 mb-3" id="count-en-proceso"><?php echo number_format($count_en_proceso ?? 0); ?></h1>
        <div class="mb-0">
            <span class="badge badge-primary-light">Asignadas y trabajándose</span>
        </div>
    </div>
</div>