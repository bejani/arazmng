<?php
// views/expenses/print.php — چاپ لیست هزینه‌ها (مشترک ادمین/پورتال)
require_once __DIR__ . '/../_helpers.php';

/**
 * انتظار:
 * $rows   : آرایهٔ هزینه‌ها (حداقل: id, title, category, amount, expense_date, unit_name?, floor?, spender_name?)
 * $sum    : جمع مبالغِ فیلترشده (اختیاری؛ اگر نباشد، همین‌جا محاسبه می‌کنیم)
 * $filter : ['from'=>Y-m-d,'to'=>Y-m-d] اختیاری برای نمایش در هِدر
 */
$rows   = $rows   ?? [];
$filter = $filter ?? ['from' => '', 'to' => ''];

// اگر $sum پاس نشده بود، محاسبه کن
if (!isset($sum)) {
    $sum = 0.0;
    foreach ($rows as $r) $sum += (float)($r['amount'] ?? 0);
}
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <title>چاپ هزینه‌ها</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <style>
    @media print {
        .no-print {
            display: none !important;
        }

        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        thead.table-dark th {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }

    .total-row {
        background: #f5f8ff;
        font-weight: 700;
    }

    .header-note {
        font-size: .95rem;
        color: #6c757d;
    }
    </style>
</head>

<body class="p-3">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">گزارش هزینه‌ها</h4>
                <?php if (!empty($filter['from']) || !empty($filter['to'])): ?>
                <div class="header-note">
                    بازه:
                    <?php if (!empty($filter['from'])): ?>
                    از <?= h(jdate($filter['from'], 'Y/m/d')) ?>
                    <?php endif; ?>
                    <?php if (!empty($filter['to'])): ?>
                    تا <?= h(jdate($filter['to'], 'Y/m/d')) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="no-print">
                <button class="btn btn-primary" onclick="window.print()">چاپ</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width:70px" class="text-center">#</th>
                        <th>عنوان</th>
                        <th>دسته</th>
                        <th class="text-end">مبلغ</th>
                        <th>تاریخ</th>

                        <th>هزینه‌کننده</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total = 0.0; ?>
                    <?php foreach ($rows as $i => $r): ?>
                    <?php
                        $amt = (float)($r['amount'] ?? 0);
                        $total += $amt;
                        $unitLbl = '';
                        if (!empty($r['unit_name'])) {
                            $unitLbl = trim((string)$r['unit_name']);
                            if (isset($r['floor'])) $unitLbl .= ' - طبقه ' . (int)$r['floor'];
                        }
                        ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td><?= h($r['title'] ?? '') ?></td>
                        <td><?= h($r['category'] ?? '') ?></td>
                        <td class="text-end"><?= h(money($amt)) ?></td>
                        <td><?= h(jdate($r['expense_date'] ?? null, 'Y/m/d')) ?></td>
                        <td><?= h($r['spender_name'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">موردی یافت نشد.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>—</td>
                        <td colspan="2" class="text-end">جمع مبالغ:</td>
                        <td class="text-end"><?= h(money($sum ?? $total)) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>

</html>