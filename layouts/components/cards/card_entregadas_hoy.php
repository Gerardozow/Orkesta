<?php
// Componente Card: Entregadas Hoy
$count = $count_entregadas_hoy ?? 0;
?>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col mt-0">
                <h5 class="card-title">Entregadas Hoy</h5>
            </div>
            <div class="col-auto">
                <div class="stat text-success">
                    <i class="align-middle" data-feather="check-circle"></i>
                </div>
            </div>
        </div>
        <h1 class="mt-1 mb-3" id="count-entregadas-hoy"><?php echo number_format($count_entregadas_hoy ?? 0); ?></h1>
        <div class="mb-0">
            <span class="badge badge-success-light ms-1">Entregadas a producción hoy</span>
        </div>
    </div>
</div>