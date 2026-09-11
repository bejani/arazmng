<?php
// views/invoices/index.php
require_once __DIR__ . '/../_helpers.php';

/**
 * Vars expected from controller scope:
 * $invoices, $units, $periods (array), $total, $page, $perPage, $lastPage, $qsBase
 * $colIssue, $colDue, $colPeriod
 */
$colIssue  = $colIssue  ?? null;
$colDue    = $colDue    ?? null;
$colPeriod = $colPeriod ?? null;

/* ---------- Sorting (UI only; controller should map keys to ORDER BY) ---------- */
$allowedSort = ['id', 'unit_name', 'subject', 'amount'];
if ($colIssue)  $allowedSort[] = 'issue_date';
if ($colDue)    $allowedSort[] = 'due_date';
if ($colPeriod) $allowedSort[] = 'period';

$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort, true) ? (string)$_GET['sort'] : 'id';
$dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : 'desc';

function sort_link_inv(string $key, string $label, string $cur, string $dir): string
{
    $q = $_GET;
    $q['page'] = 'invoices';
    $q['sort'] = $key;
    $q['dir']  = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    $href  = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $arrow = ($cur === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($href) . '" class="text-white text-decoration-none">' . h($label) . $arrow . '</a>';
}

/* ---------- Pagination summary ---------- */
$total    = $total    ?? 0;
$page     = $page     ?? 1;
$perPage  = $perPage  ?? 25;
$lastPage = $lastPage ?? 1;

$from = $total ? (($page - 1) * $perPage + 1) : 0;
$to   = min($total, $page * $perPage);

/* ---------- Link helpers that preserve filters + sort/dir ---------- */
$qsBase = $qsBase ?? ''; // e.g. "page=invoices&unit_id=...&period=...&q=..."
$plink = function ($label, $p, $disabled = false, $active = false) use ($qsBase, $sort, $dir) {
    $q = [];
    if ($qsBase) parse_str($qsBase, $q);
    $q['p']    = (int)$p;
    $q['sort'] = $sort;
    $q['dir']  = $dir;
    $href = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $cls = 'page-item';
    if ($disabled) $cls .= ' disabled';
    if ($active)   $cls .= ' active';
    echo '<li class="' . $cls . '"><a class="page-link" href="' . h($href) . '">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</a></li>';
};
$perHref = function (int $n) use ($qsBase, $sort, $dir) {
    $q = [];
    if ($qsBase) parse_str($qsBase, $q);
    $q['p']        = 1;
    $q['per_page'] = $n;
    $q['sort']     = $sort;
    $q['dir']      = $dir;
    return 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
};
$exportHref = function (string $key, string $val) use ($qsBase, $sort, $dir) {
    $q = [];
    if ($qsBase) parse_str($qsBase, $q);
    $q[$key] = $val;
    $q['sort'] = $sort;
    $q['dir']  = $dir;
    return 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
};
?>

<style>
    /* --- Page bar & tweaks --- */
    .page-bar {
        background: var(--bs-light, #f8f9fa);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .75rem;
        padding: .5rem .75rem;
    }

    .btn-compact {
        padding: .25rem .5rem;
        font-size: .8rem;
        line-height: 1.2;
    }

    .muted {
        opacity: .8;
    }

    /* --- Table header links in dark header --- */
    .table-dark th a {
        color: #fff !important;
    }

    .table-dark th a:hover {
        color: #f8f9fa !important;
        text-decoration: underline;
    }

    /* --- Responsive table → card (mobile) --- */
    #invTable thead th,
    #invTable tbody td {
        white-space: nowrap;
    }

    #invTable .text-truncate {
        max-width: 18rem;
    }

    #invTable .amount {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    @media (max-width: 576px) {
        #invTable thead {
            display: none;
        }

        #invTable tbody tr {
            display: block;
            margin-bottom: .75rem;
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: .75rem;
            padding: .5rem .75rem;
            background: var(--bs-body-bg, #fff);
        }

        #invTable tbody td {
            display: grid;
            grid-template-columns: 12ch 1fr;
            gap: .5rem .75rem;
            border: 0 !important;
            padding: .25rem 0 !important;
            white-space: normal;
        }

        #invTable tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            opacity: .75;
        }

        html[dir="rtl"] #invTable tbody td {
            text-align: right;
        }

        html[dir="rtl"] #invTable tbody td::before {
            text-align: start;
        }

        .table-actions {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 page-bar">
    <h3 class="mb-0">فاکتورها</h3>
    <div class="btn-group">
        <a class="btn btn-sm btn-outline-secondary btn-compact" href="<?= h($exportHref('view', 'print')) ?>"
            target="_blank">چاپ</a>
        <a class="btn btn-sm btn-success btn-compact" href="<?= h($exportHref('export', 'csv')) ?>">خروجی اکسل</a>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="invoices">

            <div class="col-md-4">
                <label class="form-label">واحد</label>
                <select name="unit_id" class="form-select">
                    <option value="">همه</option>
                    <?php foreach ($units as $u):
                        $sel = (isset($_GET['unit_id']) && (string)$_GET['unit_id'] === (string)$u['id']) ? 'selected' : ''; ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel ?>>
                            <?= h($u['name'] ?? '') ?>
                            <?php if (isset($u['floor']) && $u['floor'] !== ''): ?> —
                                <?= 'طبقه ' . h((string)$u['floor']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($colPeriod): ?>
                <div class="col-md-3">
                    <label class="form-label">دوره</label>
                    <select name="period" class="form-select">
                        <option value="">همه</option>
                        <?php foreach ($periods as $p):
                            $sel = (isset($_GET['period']) && $_GET['period'] === $p) ? 'selected' : ''; ?>
                            <option value="<?= h($p) ?>" <?= $sel ?>><?= h($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-4">
                <label class="form-label">جستجو (عنوان)</label>
                <input type="text" name="q" class="form-control" value="<?= h($_GET['q'] ?? '') ?>"
                    placeholder="مثلاً: شارژ خرداد">
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">اعمال فیلتر</button>
                <a href="index.php?page=invoices" class="btn btn-outline-secondary w-100">پاکسازی</a>
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
            <a class="btn btn-outline-secondary<?= ($perPage == 25 ? ' active' : '') ?>"
                href="<?= h($perHref(25)) ?>">25</a>
            <a class="btn btn-outline-secondary<?= ($perPage == 50 ? ' active' : '') ?>"
                href="<?= h($perHref(50)) ?>">50</a>
            <a class="btn btn-outline-secondary<?= ($perPage == 100 ? ' active' : '') ?>"
                href="<?= h($perHref(100)) ?>">100</a>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table id="invTable" class="table table-striped table-bordered align-middle table-hover">
        <thead class="table-dark">
            <tr>
                <th style="width:70px" class="text-center"><?= sort_link_inv('id', 'شناسه', $sort, $dir) ?></th>
                <th><?= sort_link_inv('unit_name', 'واحد', $sort, $dir) ?></th>
                <th><?= sort_link_inv('subject', 'عنوان', $sort, $dir) ?></th>
                <th><?= sort_link_inv('amount', 'مبلغ', $sort, $dir) ?></th>
                <?php if ($colIssue): ?><th><?= sort_link_inv('issue_date', 'تاریخ صدور', $sort, $dir) ?></th>
                <?php endif; ?>
                <?php if ($colDue):   ?><th><?= sort_link_inv('due_date', 'سررسید', $sort, $dir) ?></th><?php endif; ?>
                <?php if ($colPeriod): ?><th><?= sort_link_inv('period', 'دوره', $sort, $dir) ?></th><?php endif; ?>
                <th style="width:140px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="<?= 6 + (int)$colIssue + (int)$colDue + (int)$colPeriod ?>" class="text-center text-muted">
                        هیچ رکوردی یافت نشد.</td>
                </tr>
                <?php else: foreach ($invoices as $row): ?>
                    <?php
                    $unitName   = trim((string)($row['unit_name'] ?? ''));
                    $unitId     = isset($row['unit_id']) ? (string)$row['unit_id'] : '';
                    $unitLabel  = $unitName !== '' ? h($unitName) : ($unitId !== '' ? ('#' . h($unitId)) : '—');
                    $unitFloor  = isset($row['unit_floor']) && $row['unit_floor'] !== '' ? ' — طبقه ' . h((string)$row['unit_floor']) : '';
                    $amountKeys = ['amount', 'total_amount', 'total', 'price', 'invoice_amount'];
                    $amount = 0.0;
                    foreach ($amountKeys as $k) {
                        if (isset($row[$k]) && $row[$k] !== '') {
                            $amount = (float)$row[$k];
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td class="text-center" data-label="#"><?= h($row['id']) ?></td>
                        <td data-label="واحد"><?= $unitLabel . $unitFloor ?></td>
                        <td data-label="عنوان" class="text-truncate"><?= h($row['subject'] ?? '') ?></td>
                        <td data-label="مبلغ" class="amount text-nowrap"><?= h(money($amount)) ?></td>

                        <?php if ($colIssue): ?>
                            <td data-label="تاریخ صدور" class="text-nowrap"><?= h($row['issue_date'] ?? '') ?></td>
                        <?php endif; ?>

                        <?php if ($colDue): ?>
                            <td data-label="سررسید" class="text-nowrap"><?= h($row['due_date'] ?? '') ?></td>
                        <?php endif; ?>

                        <?php if ($colPeriod): ?>
                            <td data-label="دوره" class="text-nowrap"><?= h($row['period'] ?? '') ?></td>
                        <?php endif; ?>

                        <td data-label="عملیات" class="text-center">
                            <div class="table-actions">
                                <a class="btn btn-sm btn-success"
                                    href="index.php?page=payment_new&invoice_id=<?= (int)$row['id'] ?>">
                                    ثبت پرداخت
                                </a>
                            </div>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>

<?php if ($lastPage > 1): ?>
    <nav aria-label="pagination" class="mt-3">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $plink('قبلی', max(1, $page - 1), $page <= 1);
            $win = 2;
            $start = max(1, $page - $win);
            $end   = min($lastPage, $page + $win);
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