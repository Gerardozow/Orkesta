<?php
// Componente Card: Entregadas Hoy
$count = $count_parciales ?? 0;
?>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col mt-0">
                <h5 class="card-title">WO Parciales</h5>
            </div>
            <div class="col-auto">
                <div class="stat text-warning">
                    <i class="align-middle" data-feather="alert-triangle"></i>
                </div>
            </div>
        </div>
        <h1 class="mt-1 mb-3" id="count-parciales"><?php echo number_format($count_parciales ?? 0); ?></h1>
        <div class="mb-0">
            <span class="badge bg-warning ms-1">Workorders Parciales</span>
        </div>
    </div>
</div>