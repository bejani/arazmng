<?php

declare(strict_types=1);
require_once __DIR__ . '/../_helpers.php'; ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="h5 m-0">چاپ فاکتورها</h1>
        <button class="btn btn-secondary" onclick="window.print()">چاپ</button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>واحد</th>
                    <th>عنوان</th>
                    <th>مبلغ</th>
                    <th>دوره</th>
                    <th>تاریخ صدور</th>
                    <th>سررسید</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="7" class="text-center">موردی نیست</td>
                </tr>
                <?php else: foreach ($invoices as $r): ?>
                <tr>
                    <td><?= (int)($r['id'] ?? 0) ?></td>
                    <td>
                        <?= h($r['unit_name'] ?? '') ?>
                        <?php if (!empty($r['unit_floor'])): ?> —
                        <?= 'طبقه ' . h((string)$r['unit_floor']) ?><?php endif; ?>
                    </td>
                    <td><?= h($r['subject'] ?? '') ?></td>
                    <td><?= h(number_format((float)($r['amount'] ?? 0))) ?></td>
                    <td><?= h($r['period'] ?? '') ?></td>
                    <td><?= h($r['issue_date'] ?? '') ?></td>
                    <td><?= h($r['due_date'] ?? '') ?></td>
                </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }

    @page {
        size: A4 landscape;
        margin: 12mm;
    }
}
</style>
<script>
/* چاپ سریع */
</script>