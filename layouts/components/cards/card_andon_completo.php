<?php $count = $count_completo ?? 0; ?>
<div class="card shadow-sm">
    <div class="card-body text-center p-3">
        <h5 class="card-title text-success mb-1">PICKEADO COMPLETO</h5>
        <h1 class="display-5 fw-bold text-success" id="andon-count-completo"><?php echo number_format($count); ?></h1>
    </div>
</div>