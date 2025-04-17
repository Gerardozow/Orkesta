<?php $count = $count_parcial ?? 0; ?>
<div class="card shadow-sm">
    <div class="card-body text-center p-3">
        <h5 class="card-title text-warning mb-1">PICKEO PARCIAL</h5>
        <h1 class="display-5 fw-bold text-warning" id="andon-count-parcial"><?php echo number_format($count); ?></h1>
    </div>
</div>