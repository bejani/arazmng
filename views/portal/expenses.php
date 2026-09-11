<?php ob_start();
require_once __DIR__ . '/../_helpers.php';

/**
 * انتظار:
 * $rows  : e.id, e.title, e.category, e.amount, e.expense_date, e.spender_name
 * $filter: ['from'=>Y-m-d,'to'=>Y-m-d]
 * $sum   : float
 */

$rows    = $rows    ?? [];
$filter  = $filter  ?? ['from' => '', 'to' => ''];
$sum     = isset($sum) ? (float)$sum : 0.0;

$sort = (string)($_GET['sort'] ?? 'expense_date');
if ($sort === 'date') $sort = 'expense_date';
$dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : 'desc';

$qsBase = (function () {
    $q = $_GET;
    $q['page'] = 'portal_expenses';
    return http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
})();

function sort_link_portal(string $key, string $label, string $cur, string $dir, string $qsBase): string
{
    parse_str($qsBase, $q);
    $q['sort'] = $key;
    $q['dir']  = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    $href  = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $arrow = ($cur === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($href) . '" class="text-white text-decoration-none">' . h($label) . $arrow . '</a>';
}
?>
<style>
.table-dark th a {
    color: #fff !important;
}

.table-dark th a:hover,
.table-dark th a:focus {
    color: #f8f9fa !important;
    text-decoration: underline;
}

@media (max-width: 575.98px) {
    .card.exp-item {
        border-radius: .85rem;
    }

    .exp-item .exp-title {
        font-weight: 600;
    }

    .exp-item .exp-amount {
        font-weight: 700;
    }

    .exp-item .meta {
        font-size: .9rem;
    }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">گزارش هزینه‌ها (پورتال)</h3>
    <div class="btn-group">
        <a class="btn btn-sm btn-outline-secondary" href="index.php?<?= $qsBase ?>&view=print" target="_blank">چاپ</a>
        <a class="btn btn-sm btn-success" href="index.php?<?= $qsBase ?>&export=csv">خروجی اکسل</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="portal_expenses">
            <div class="col-12 col-md-3">
                <label class="form-label">از تاریخ</label>
                <input type="hidden" name="from" value="<?= h($filter['from'] ?? '') ?>">
                <input type="text" class="form-control jdate" data-target="from" placeholder="۱۴۰۳/۰۱/۰۱">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">تا تاریخ</label>
                <input type="hidden" name="to" value="<?= h($filter['to'] ?? '') ?>">
                <input type="text" class="form-control jdate" data-target="to" placeholder="۱۴۰۳/۱۲/۲۹">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-primary w-100">فیلتر</button>
                <a href="index.php?page=portal_expenses" class="btn btn-outline-secondary w-100">پاکسازی</a>
            </div>
            <div class="col-12 col-md-3 text-md-end">
                <span class="badge bg-info text-dark">جمع بازه: <?= h(money($sum)) ?></span>
            </div>
        </form>
    </div>
</div>

<!-- کارت موبایل -->
<div class="d-block d-md-none">
    <?php if (!empty($rows)): ?>
    <?php $totalAmount = 0.0;
        foreach ($rows as $r): $amt = (float)($r['amount'] ?? 0);
            $totalAmount += $amt; ?>
    <div class="card exp-item mb-2">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div class="exp-title"><?= h($r['title'] ?? '') ?></div>
                <div class="exp-amount"><?= h(money($amt)) ?></div>
            </div>
            <div class="meta text-muted mt-1">
                <div><span class="fw-semibold">تاریخ:</span> <?= h(jdate($r['expense_date'] ?? null, 'Y/m/d')) ?></div>
                <?php if (!empty($r['category'])): ?>
                <div><span class="fw-semibold">دسته:</span> <?= h($r['category']) ?></div>
                <?php endif; ?>
                <?php if (!empty($r['spender_name'])): ?>
                <div><span class="fw-semibold">هزینه‌کننده:</span> <?= h($r['spender_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="text-end mt-2"><span class="fw-bold">جمع نمایش‌داده‌شده: </span><?= h(money($totalAmount)) ?></div>
    <?php else: ?>
    <div class="text-center text-muted">موردی یافت نشد.</div>
    <?php endif; ?>
</div>

<!-- جدول دسکتاپ -->
<div class="d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th style="width:70px" class="text-center">#</th>
                    <th><?= sort_link_portal('title',        'عنوان',        $sort, $dir, $qsBase) ?></th>
                    <th class="d-none d-lg-table-cell"><?= sort_link_portal('category', 'دسته', $sort, $dir, $qsBase) ?>
                    </th>
                    <th class="text-end"><?= sort_link_portal('amount',      'مبلغ', $sort, $dir, $qsBase) ?></th>
                    <th><?= sort_link_portal('expense_date', 'تاریخ هزینه',  $sort, $dir, $qsBase) ?></th>
                    <th class="d-none d-xl-table-cell">
                        <?= sort_link_portal('spender_name', 'هزینه‌کننده', $sort, $dir, $qsBase) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $totalAmount = 0.0; ?>
                <?php foreach ($rows as $i => $r): $amt = (float)($r['amount'] ?? 0);
                    $totalAmount += $amt; ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= h($r['title'] ?? '') ?></td>
                    <td class="d-none d-lg-table-cell"><?= h($r['category'] ?? '') ?></td>
                    <td class="text-end"><?= h(money($amt)) ?></td>
                    <td><?= h(jdate($r['expense_date'] ?? null, 'Y/m/d')) ?></td>
                    <td class="d-none d-xl-table-cell"><?= h($r['spender_name'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">موردی یافت نشد.</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="6" class="text-end fw-bold">جمع مبلغ نمایش‌داده‌شده: <?= h(money($totalAmount)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>