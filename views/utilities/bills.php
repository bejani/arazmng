<?php
// views/utilities/bills.php
require_once __DIR__ . '/../_helpers.php';

/** انتظار: $rows, $units, $periods, $total, $page, $perPage, $qsBase, $lastPage */
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
$dir  = (strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc') ? 'asc' : 'desc';

function sort_link_util(string $key, string $label, string $cur, string $dir): string
{
    $q = $_GET;
    $q['page'] = 'utility_bills';
    $q['sort'] = $key;
    $q['dir']  = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    $href  = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $arrow = ($cur === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($href) . '" class="text-white text-decoration-none">' . h($label) . $arrow . '</a>';
}

$total    = $total    ?? 0;
$page     = $page     ?? 1;
$perPage  = $perPage  ?? 25;
$lastPage = $lastPage ?? 1;

$from = $total ? (($page - 1) * $perPage + 1) : 0;
$to   = min($total, $page * $perPage);

$plink = function ($label, $p, $disabled = false, $active = false) use ($qsBase, $sort, $dir) {
    $q = [];
    if ($qsBase) parse_str($qsBase, $q);
    $q['p']    = (int)$p;
    $q['sort'] = $sort;
    $q['dir']  = $dir;
    $href = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
    echo '<li class="' . $cls . '"><a class="page-link" href="' . h($href) . '">' . h((string)$label) . '</a></li>';
};
$perHref = function (int $n) use ($qsBase, $sort, $dir) {
    $q = [];
    if ($qsBase) parse_str($qsBase, $q);
    $q['p'] = 1;
    $q['per_page'] = $n;
    $q['sort'] = $sort;
    $q['dir'] = $dir;
    return 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
};
?>
<style>
.table-dark th a {
    color: #fff !important
}

.table-dark th a:hover {
    color: #f8f9fa !important;
    text-decoration: underline
}

@media (max-width:576px) {
    #utilTable thead {
        display: none
    }

    #utilTable tbody tr {
        display: block;
        margin-bottom: .8rem;
        border: 1px solid var(--bs-border-color, #e5e7eb);
        border-radius: .9rem;
        padding: .6rem .8rem;
        background: var(--bs-body-bg, #fff);
        box-shadow: 0 2px 8px rgba(0, 0, 0, .04)
    }

    #utilTable tbody td {
        display: grid;
        grid-template-columns: 12ch 1fr;
        gap: .4rem .75rem;
        border: 0 !important;
        padding: .3rem 0 !important;
        white-space: normal
    }

    #utilTable tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        opacity: .75
    }

    .table-actions {
        display: flex;
        gap: .4rem;
        flex-wrap: wrap
    }
}

.amount {
    font-variant-numeric: tabular-nums;
    font-weight: 600
}

.badge-soft {
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #334155
}

.badge-soft.ok {
    background: #e6f9ed;
    border-color: #c6f0d1;
    color: #137a2a;
    font-weight: 700
}

.badge-soft.warn {
    background: #fff7e6;
    border-color: #ffe8b3;
    color: #8a5a00
}

.badge-soft.danger {
    background: #fef2f2;
    border-color: #fee2e2;
    color: #991b1b
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">قبض‌ها</h3>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="utility_bills">

            <div class="col-md-4">
                <label class="form-label">واحد</label>
                <select name="unit_id" class="form-select">
                    <option value="">— همه —</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"
                        <?= ((string)($_GET['unit_id'] ?? '') === (string)$u['id']) ? 'selected' : '' ?>>
                        <?= h(trim(($u['name'] ?? '') . ' — طبقه ' . (int)($u['floor'] ?? 0))) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">وضعیت</label>
                <?php $st = $_GET['status'] ?? 'all'; ?>
                <select name="status" class="form-select">
                    <option value="all" <?= $st === 'all' ? 'selected' : '' ?>>همه</option>
                    <option value="unpaid" <?= $st === 'unpaid' ? 'selected' : '' ?>>پرداخت‌نشده</option>
                    <option value="partial" <?= $st === 'partial' ? 'selected' : '' ?>>پرداخت‌جزئی</option>
                    <option value="paid" <?= $st === 'paid' ? 'selected' : '' ?>>تسویه‌شده</option>
                </select>
            </div>

            <?php if (!empty($periods)): ?>
            <div class="col-md-3">
                <label class="form-label">دوره</label>
                <select name="period" class="form-select">
                    <option value="">— همه —</option>
                    <?php foreach ($periods as $p): ?>
                    <option value="<?= h($p) ?>"
                        <?= ((string)($_GET['period'] ?? '') === (string)$p) ? 'selected' : '' ?>><?= h($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-4">
                <label class="form-label">جستجو (نام مالک)</label>
                <input type="text" name="q" class="form-control" value="<?= h($_GET['q'] ?? '') ?>"
                    placeholder="مثلاً: احمد رضایی">
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100">اعمال فیلتر</button>
                <a href="index.php?page=utility_bills" class="btn btn-outline-secondary w-100">پاکسازی</a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <small class="text-muted">نمایش <?= h(number_format($from)) ?> تا <?= h(number_format($to)) ?> از
        <?= h(number_format($total)) ?> رکورد</small>
    <div class="d-flex align-items-center gap-2">
        <small class="text-muted">در هر صفحه:</small>
        <div class="btn-group btn-group-sm" role="group" aria-label="per-page">
            <a class="btn btn-outline-secondary<?= (($perPage ?? 25) == 25 ? ' active' : '') ?>"
                href="<?= h($perHref(25)) ?>">25</a>
            <a class="btn btn-outline-secondary<?= (($perPage ?? 25) == 50 ? ' active' : '') ?>"
                href="<?= h($perHref(50)) ?>">50</a>
            <a class="btn btn-outline-secondary<?= (($perPage ?? 25) == 100 ? ' active' : '') ?>"
                href="<?= h($perHref(100)) ?>">100</a>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table id="utilTable" class="table table-striped table-bordered align-middle table-hover">
        <thead class="table-dark">
            <tr>
                <th style="width:70px" class="text-center"><?= sort_link_util('id', 'شناسه', $sort, $dir) ?></th>
                <th><?= sort_link_util('unit_name', 'واحد', $sort, $dir) ?></th>
                <th><?= sort_link_util('subject', 'عنوان قبض', $sort, $dir) ?></th>
                <th class="text-end"><?= sort_link_util('amount', 'مبلغ', $sort, $dir) ?></th>
                <th class="text-end"><?= sort_link_util('paid_amount', 'پرداخت‌شده', $sort, $dir) ?></th>
                <th class="text-end"><?= sort_link_util('balance', 'مانده', $sort, $dir) ?></th>
                <th><?= sort_link_util('owner_name', 'مالک', $sort, $dir) ?></th>
                <?php if (!empty($rows) && array_key_exists('period', $rows[0])): ?>
                <th><?= sort_link_util('period', 'دوره', $sort, $dir) ?></th>
                <?php endif; ?>
                <th style="width:140px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr>
                <td colspan="10" class="text-center text-muted">موردی یافت نشد.</td>
            </tr>
            <?php else: foreach ($rows as $r): ?>
            <?php
          $unit = trim(($r['unit_name'] ?? '—') . (isset($r['unit_floor']) ? ' — طبقه ' . (int)$r['unit_floor'] : ''));
          $amt  = (float)($r['amount'] ?? 0);
          $paid = (float)($r['paid_amount'] ?? 0);
          $bal  = (float)($r['balance'] ?? 0);
          $chipClass = $bal <= 0 ? 'ok' : ($paid > 0 ? 'warn' : 'danger');
          $chipText  = $bal <= 0 ? 'تسویه' : ($paid > 0 ? 'جزئی' : 'بدهکار');
        ?>
            <tr>
                <td class="text-center" data-label="#"><?= (int)$r['id'] ?></td>
                <td data-label="واحد"><?= h($unit) ?></td>
                <td data-label="عنوان" class="text-truncate"><?= h($r['subject'] ?? '') ?></td>
                <td data-label="مبلغ" class="text-end amount"><?= h(money($amt)) ?></td>
                <td data-label="پرداخت‌شده" class="text-end amount"><?= h(money($paid)) ?></td>
                <td data-label="مانده" class="text-end amount">
                    <?= h(money($bal)) ?>
                    <span class="badge badge-soft <?= $chipClass ?> ms-1"><?= $chipText ?></span>
                </td>
                <td data-label="مالک" class="text-nowrap"><?= h($r['owner_name'] ?? '—') ?></td>
                <?php if (array_key_exists('period', $r)): ?>
                <td data-label="دوره" class="text-nowrap"><?= h($r['period'] ?? '') ?></td>
                <?php endif; ?>
                <td data-label="عملیات" class="text-center">
                    <div class="table-actions">
                        <a class="btn btn-sm btn-success"
                            href="index.php?page=payment_new&invoice_id=<?= (int)$r['id'] ?>">ثبت پرداخت</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if (($lastPage ?? 1) > 1): ?>
<nav class="mt-3" aria-label="pagination">
    <ul class="pagination justify-content-center flex-wrap">
        <?php
      $plink('قبلی', max(1, $page - 1), $page <= 1);
      $win = 2;
      $start = max(1, $page - $win);
      $end = min($lastPage, $page + $win);
      if ($start > 1) {
        $plink(1, 1, false, $page === 1);
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
      for ($i = $start; $i <= $end; $i++) $plink($i, $i, false, $i === $page);
      if ($end < $lastPage) {
        if ($end < $lastPage - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $plink($lastPage, $lastPage, false, $page === $lastPage);
      }
      $plink('بعدی', min($lastPage, $page + 1), $page >= $lastPage);
    ?>
    </ul>
    <div class="text-center text-muted small">صفحه <?= h($page) ?> از <?= h($lastPage) ?></div>
</nav>
<?php endif; ?>