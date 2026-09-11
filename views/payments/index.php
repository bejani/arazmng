<?php
// views/payments/index.php
require_once __DIR__ . '/../_helpers.php';
$rows  = $rows  ?? [];
$units = $units ?? [];
$f     = $filter ?? ['unit_id' => null, 'from' => '', 'to' => '', 'method' => '', 'q' => ''];

/* ---------- Sorting (UI) ---------- */
$allowedSort = ['unit_name', 'invoice_id', 'subject', 'amount', 'pay_date', 'method', 'ref', 'note', 'payer_name', 'id'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort, true) ? (string)$_GET['sort'] : 'pay_date';
$dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : 'desc';

function sort_link_pay(string $key, string $label, string $cur, string $dir, array $filters): string
{
    $q = $_GET;
    $q['page'] = 'payments';
    $q['sort'] = $key;
    $q['dir']  = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    // keep filters
    $q['unit_id'] = $filters['unit_id'] ?? '';
    $q['method']  = $filters['method']  ?? '';
    $q['q']       = $filters['q']       ?? '';
    if (!empty($filters['from'])) $q['from'] = $filters['from'];
    if (!empty($filters['to']))   $q['to']   = $filters['to'];
    $href  = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $arrow = ($cur === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($href) . '" class="text-white text-decoration-none">' . h($label) . $arrow . '</a>';
}
?>
<style>
    /* Header bar */
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

    /* Table dark header links */
    .table-dark th a {
        color: #fff !important;
    }

    .table-dark th a:hover {
        color: #f8f9fa !important;
        text-decoration: underline;
    }

    /* Method badge */
    .method-badge {
        display: inline-block;
        padding: .35rem .55rem;
        border-radius: 50rem;
        font-weight: 600;
    }

    .method-cash {
        color: rgb(var(--bs-success-rgb));
        background: rgba(var(--bs-success-rgb), .12);
        border: 1px solid rgba(var(--bs-success-rgb), .35);
    }

    .method-bank {
        color: rgb(var(--bs-info-rgb));
        background: rgba(var(--bs-info-rgb), .12);
        border: 1px solid rgba(var(--bs-info-rgb), .35);
    }

    .method-card {
        color: rgb(var(--bs-primary-rgb));
        background: rgba(var(--bs-primary-rgb), .12);
        border: 1px solid rgba(var(--bs-primary-rgb), .35);
    }

    .ref-chip {
        display: inline-block;
        padding: .2rem .45rem;
        border-radius: .35rem;
        background: var(--bs-light, #f8f9fa);
        border: 1px solid var(--bs-border-color, #dee2e6);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    /* Amount typography */
    .amount {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    /* Responsive table → card */
    #payTable thead th,
    #payTable tbody td {
        white-space: nowrap;
    }

    #payTable .text-truncate {
        max-width: 18rem;
    }

    @media (max-width: 576px) {
        #payTable thead {
            display: none;
        }

        #payTable tbody tr {
            display: block;
            margin-bottom: .75rem;
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: .75rem;
            padding: .5rem .75rem;
            background: var(--bs-body-bg, #fff);
        }

        #payTable tbody td {
            display: grid;
            grid-template-columns: 12ch 1fr;
            gap: .5rem .75rem;
            border: 0 !important;
            padding: .25rem 0 !important;
            white-space: normal;
        }

        #payTable tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            opacity: .75;
        }

        /* actions two-per-row on mobile */
        #payTable tbody td.actions {
            grid-template-columns: 1fr 1fr;
            gap: .25rem;
        }

        #payTable tbody td.actions>* {
            display: block !important;
            margin: 0;
        }

        #payTable tbody td.actions a.btn,
        #payTable tbody td.actions button.btn {
            width: 100%;
        }

        html[dir="rtl"] #payTable tbody td {
            text-align: right;
        }

        html[dir="rtl"] #payTable tbody td::before {
            text-align: start;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 page-bar">
    <h3 class="mb-0">پرداخت‌ها</h3>
    <a href="index.php?page=payment_new" class="btn btn-primary btn-compact">➕ پرداخت جدید</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="payments">
            <!-- Preserve sort/dir when applying filters -->
            <input type="hidden" name="sort" value="<?= h($sort) ?>">
            <input type="hidden" name="dir" value="<?= h($dir) ?>">

            <div class="col-md-3">
                <label class="form-label">واحد</label>
                <select name="unit_id" class="form-select">
                    <option value="">— همه —</option>
                    <?php foreach ($units as $u):
                        $lbl = trim(($u['name'] ?? '') . ' - طبقه ' . (int)($u['floor'] ?? 0));
                        $sel = (string)($f['unit_id'] ?? '') === (string)$u['id'] ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">از تاریخ</label>
                <input type="hidden" name="from" value="<?= h($f['from'] ?? '') ?>">
                <input type="text" class="form-control jdate" data-target="from" placeholder="۱۴۰۳/۰۱/۰۱">
            </div>
            <div class="col-md-2">
                <label class="form-label">تا تاریخ</label>
                <input type="hidden" name="to" value="<?= h($f['to'] ?? '') ?>">
                <input type="text" class="form-control jdate" data-target="to" placeholder="۱۴۰۳/۱۲/۲۹">
            </div>
            <div class="col-md-2">
                <label class="form-label">روش</label>
                <select name="method" class="form-select">
                    <option value="">— همه —</option>
                    <option value="cash" <?= ($f['method'] ?? '') === 'cash' ? 'selected' : '' ?>>نقدی</option>
                    <option value="bank" <?= ($f['method'] ?? '') === 'bank' ? 'selected' : '' ?>>واریز بانکی</option>
                    <option value="card" <?= ($f['method'] ?? '') === 'card' ? 'selected' : '' ?>>کارت‌خوان</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">جست‌وجو (رسید/توضیح)</label>
                <input type="text" name="q" value="<?= h($f['q'] ?? '') ?>" class="form-control"
                    placeholder="مثل: 123ABC یا تخفیف">
            </div>
            <div class="col-md-12 d-flex gap-2">
                <button class="btn btn-primary">اعمال فیلتر</button>
                <a href="index.php?page=payments" class="btn btn-outline-secondary">پاکسازی</a>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table id="payTable" class="table table-striped table-bordered align-middle table-hover">
        <thead class="table-dark">
            <tr>
                <th class="text-center">#</th>
                <th><?= sort_link_pay('unit_name', 'واحد', $sort, $dir, $f) ?></th>
                <th><?= sort_link_pay('invoice_id', 'فاکتور', $sort, $dir, $f) ?></th>
                <th class="text-end"><?= sort_link_pay('amount', 'مبلغ', $sort, $dir, $f) ?></th>
                <th><?= sort_link_pay('pay_date', 'تاریخ پرداخت', $sort, $dir, $f) ?></th>
                <th><?= sort_link_pay('method', 'روش', $sort, $dir, $f) ?></th>
                <th><?= sort_link_pay('ref', 'رسید', $sort, $dir, $f) ?></th>
                <th><?= sort_link_pay('payer_name', 'پرداخت‌کننده', $sort, $dir, $f) ?></th>
                <th style="width:180px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted">موردی یافت نشد.</td>
                </tr>
                <?php else: foreach ($rows as $i => $r): ?>
                    <?php
                    $unitLbl = trim(($r['unit_name'] ?? '') . ' - طبقه ' . (int)($r['floor'] ?? 0));
                    $unitLbl = $unitLbl !== '' ? $unitLbl : '—';

                    $sid = (int)($r['invoice_id'] ?? 0);
                    $sub = (string)($r['subject'] ?? ('#' . $sid));
                    $invText = '#' . (int)$sid . ' — ' . $sub;

                    $method = (string)($r['method'] ?? '');
                    $mLabel = ['cash' => 'نقدی', 'bank' => 'واریز بانکی', 'card' => 'کارت‌خوان'][$method] ?? h($method);
                    $mClass = ['cash' => 'method-cash', 'bank' => 'method-bank', 'card' => 'method-card'][$method] ?? 'method-card';

                    $ref = trim((string)($r['ref'] ?? ''));
                    $pid = (int)($r['id'] ?? 0);
                    $payerName = trim((string)($r['payer_name'] ?? ''));
                    ?>
                    <tr>
                        <td class="text-center" data-label="#"><?= $i + 1 ?></td>
                        <td data-label="واحد"><?= h($unitLbl) ?></td>
                        <td data-label="فاکتور" class="text-truncate"><?= h($invText) ?></td>
                        <td data-label="مبلغ" class="text-end amount"><?= h(money((float)($r['amount'] ?? 0))) ?></td>
                        <td data-label="تاریخ پرداخت" class="text-nowrap"><?= h(jdate($r['pay_date'] ?? null, 'Y/m/d')) ?></td>
                        <td data-label="روش"><span class="method-badge <?= $mClass ?>"><?= h($mLabel) ?></span></td>
                        <td data-label="رسید"><?= $ref !== '' ? '<span class="ref-chip" dir="ltr">' . h($ref) . '</span>' : '—' ?>
                        </td>
                        <td data-label="پرداخت‌کننده"><?= $payerName !== '' ? h($payerName) : '—' ?></td>
                        <td class="text-center actions" data-label="عملیات">
                            <?php if ($pid > 0): ?>
                                <a class="btn btn-sm btn-primary btn-compact"
                                    href="index.php?page=payment_edit&id=<?= $pid ?>">ویرایش</a>
                                <form method="POST" action="index.php?page=payment_delete" class="d-inline"
                                    onsubmit="return confirm('حذف این پرداخت؟');">
                                    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
                                    <input type="hidden" name="id" value="<?= $pid ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-compact">حذف</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>