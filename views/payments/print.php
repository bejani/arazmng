<?php

declare(strict_types=1);
require_once __DIR__ . '/../_helpers.php'; ?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>چاپ پرداخت‌ها</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
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
</head>

<body class="p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="h5 m-0">چاپ پرداخت‌ها</h1>
        <button class="btn btn-secondary" onclick="window.print()">چاپ</button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>واحد</th>
                    <th>فاکتور</th>
                    <th>مبلغ</th>
                    <?php if (!empty($hasPaidAt)): ?><th>تاریخ (شمسی)</th><?php endif; ?>
                    <th>روش</th>
                    <th>مرجع</th>
                    <th>توضیح</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="8" class="text-center">موردی نیست</td>
                </tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)($r['id'] ?? 0) ?></td>
                    <td>
                        <?= h($r['unit_name'] ?? '') ?>
                        <?php if (!empty($r['unit_floor'])): ?> —
                        <?= 'طبقه ' . h((string)$r['unit_floor']) ?><?php endif; ?>
                    </td>
                    <td>#<?= (int)($r['invoice_id'] ?? 0) ?> — <?= h($r['inv_subject'] ?? '') ?></td>
                    <td><?= h(number_format((float)($r['amount'] ?? 0))) ?></td>
                    <?php if (!empty($hasPaidAt)): ?><td><?= h($r['paid_at_jalali'] ?? '') ?></td><?php endif; ?>
                    <td><?= h($r['method'] ?? '') ?></td>
                    <td><?= h($r['ref'] ?? '') ?></td>
                    <td><?= h($r['note'] ?? '') ?></td>
                </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>