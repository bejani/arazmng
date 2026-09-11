<?php ob_start();
require_once __DIR__ . '/../_helpers.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">قبض‌ها</h3>
    <a class="btn btn-primary" href="index.php?page=utilities_create">➕ راند جدید</a>
</div>
<style>
.page-bar {
    background: var(--bs-light, #f8f9fa);
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: .75rem;
    padding: .5rem .75rem;
}

.btn-compact {
    padding: .35rem .6rem;
    font-size: .85rem;
    line-height: 1.2;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 page-bar">
    <h3 class="mb-0">راند جدید قبض</h3>
    <div class="btn-group">
        <a class="btn btn-sm btn-outline-primary btn-compact" href="index.php?page=utility_bills">
            قبض‌های صادر شده
        </a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-modern align-middle responsive-cards">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>نوع</th>
                <th>دوره</th>
                <th>مبلغ قبض</th>
                <th>مبلغ تخصیص‌یافته</th>
                <th>روش</th>
                <th>توضیح</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted">موردی نیست.</td>
            </tr>
            <?php else: foreach ($rows as $i => $r): ?>
            <tr>
                <td data-label="#"><?= $i + 1 ?></td>
                <td data-label="نوع"><?= h($r['utility_type']) ?></td>
                <td data-label="دوره"><?= h($r['period']) ?></td>
                <td data-label="مبلغ قبض"><?= h(money((float)$r['bill_amount'])) ?></td>
                <td data-label="تخصیص"><?= h(money((float)$r['allocated'])) ?></td>
                <td data-label="روش"><?= h($r['method']) ?></td>
                <td data-label="توضیح"><?= h($r['note'] ?? '—') ?></td>
            </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>