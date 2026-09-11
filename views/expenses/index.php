<?php ob_start();
require_once __DIR__ . '/../_helpers.php';
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

    /* --- Responsive table → card on mobile --- */
    #expensesTable thead th,
    #expensesTable tbody td {
        white-space: nowrap;
    }

    #expensesTable .text-truncate {
        max-width: 16rem;
    }

    @media (max-width: 576px) {
        #expensesTable {
            width: 100%;
        }

        #expensesTable thead {
            display: none;
        }

        #expensesTable tbody tr {
            display: block;
            margin-bottom: .75rem;
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: .75rem;
            padding: .5rem .75rem;
            background: var(--bs-body-bg, #fff);
        }

        #expensesTable tbody td {
            display: grid;
            grid-template-columns: 11ch 1fr;
            gap: .5rem .75rem;
            border: 0 !important;
            padding: .25rem 0 !important;
            white-space: normal;
        }

        #expensesTable tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            opacity: .75;
        }

        /* عملیات بره آخر کارت و تک‌ستونی بشه */
        #expensesTable tbody td.actions {
            grid-template-columns: 1fr;
            justify-items: end;
        }

        /* بهینه‌سازی RTL */
        html[dir="rtl"] #expensesTable tbody td {
            text-align: right;
        }

        html[dir="rtl"] #expensesTable tbody td::before {
            text-align: start;
        }
    }
</style>
<?php
/**
 * انتظار از کنترلر:
 * $rows, $sum, $filter, $categories, $units, $residents
 */
$rows      = $rows      ?? [];
$sum       = isset($sum) ? (float)$sum : 0.0;
$filter    = $filter    ?? ['from' => '', 'to' => '', 'category' => '', 'spender' => '', 'unit_id' => ''];
$cats      = $categories ?? [];
$units     = $units ?? [];
$residents = $residents ?? [];

/* ------ مرتب‌سازی ------ */
$sort = (string)($_GET['sort'] ?? 'expense_date');
if ($sort === 'date') $sort = 'expense_date';
$dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : 'desc';

/* ------ سازنده لینک مرتب‌سازی ------ */
function sort_link_e(string $key, string $label, string $cur, string $dir): string
{
    $q = $_GET;
    $q['page'] = 'expenses';
    $q['sort'] = $key;
    $q['dir']  = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    $href  = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $arrow = ($cur === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($href) . '" class="link-dark text-decoration-none">' . h($label) . $arrow . '</a>';
}

/* ------ لینک‌های چاپ/CSV با حفظ وضعیت ------ */
$qAll = $_GET;
$qAll['page'] = 'expenses';
$qPrint = $qAll;
$qPrint['view'] = 'print';
$qCsv   = $qAll;
$qCsv['export'] = 'csv';
$hrefPrint = 'index.php?' . http_build_query($qPrint, '&', '&', PHP_QUERY_RFC3986);
$hrefCsv   = 'index.php?' . http_build_query($qCsv,   '&', '&', PHP_QUERY_RFC3986);

/* کنترل نمایش اولیهٔ فرم (اختیاری با ?open=new) */
$showForm = (isset($_GET['open']) && $_GET['open'] === 'new');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">هزینه‌ها</h3>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-info text-dark">جمع فیلتر: <?= h(money($sum)) ?></span>
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary" href="<?= h($hrefPrint) ?>" target="_blank">چاپ</a>
            <a class="btn btn-sm btn-success" href="<?= h($hrefCsv) ?>">خروجی اکسل</a>
            <!-- دکمهٔ باز/بسته کردن فرم -->
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#expForm"
                aria-expanded="<?= $showForm ? 'true' : 'false' ?>" aria-controls="expForm">
                ➕ هزینه جدید
            </button>
        </div>
    </div>
</div>

<!-- فیلتر -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="expenses">
            <div class="col-md-2">
                <label class="form-label">از تاریخ</label>
                <input type="hidden" name="from" value="<?= h($filter['from'] ?? '') ?>">
                <input type="text" class="form-control jdate" data-target="from" placeholder="۱۴۰۳/۰۱/۰۱">
            </div>
            <div class="col-md-2">
                <label class="form-label">تا تاریخ</label>
                <input type="hidden" name="to" value="<?= h($filter['to'] ?? '') ?>">
                <input type="text" class="form-control jdate" data-target="to" placeholder="۱۴۰۳/۱۲/۲۹">
            </div>
            <div class="col-md-3">
                <label class="form-label">دسته</label>
                <select name="category" class="form-select">
                    <option value="">— همه —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= h($c) ?>" <?= ($filter['category'] ?? '') === $c ? 'selected' : '' ?>>
                            <?= h($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">واحد</label>
                <select name="unit_id" class="form-select">
                    <option value="">— همه —</option>
                    <?php foreach ($units as $u):
                        $lbl = trim(($u['name'] ?? '') . ' - طبقه ' . (int)($u['floor'] ?? 0)); ?>
                        <option value="<?= (int)$u['id'] ?>"
                            <?= (string)($filter['unit_id'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>>
                            <?= h($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">هزینه‌کننده</label>
                <input type="text" name="spender" class="form-control" value="<?= h($filter['spender'] ?? '') ?>"
                    placeholder="نام">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100">اعمال فیلتر</button>
                <a href="index.php?page=expenses" class="btn btn-outline-secondary w-100">پاکسازی</a>
            </div>
        </form>
    </div>
</div>

<!-- فرم افزودن (پیش‌فرض پنهان) -->
<div id="expForm" class="collapse<?= $showForm ? ' show' : '' ?> mb-3">
    <div class="card">
        <div class="card-body">
            <form method="POST" action="index.php?page=expense_store" class="row g-2">
                <?= csrf_field() ?>

                <div class="col-md-3">
                    <input type="text" name="title" class="form-control" placeholder="عنوان هزینه" required>
                </div>

                <div class="col-md-2">
                    <input type="text" name="category" class="form-control" list="catlist"
                        placeholder="دسته (مثلاً: برق)">
                    <datalist id="catlist">
                        <?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-md-2">
                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="مبلغ" required>
                </div>

                <div class="col-md-2">
                    <input type="hidden" name="expense_date" value="<?= h(date('Y-m-d')) ?>">
                    <input type="text" class="form-control jdate" data-target="expense_date" placeholder="تاریخ (شمسی)">
                </div>

                <div class="col-md-3">
                    <div class="input-group">
                        <select name="spender_id" class="form-select">
                            <option value="">— انتخاب از ساکنان —</option>
                            <?php foreach ($residents as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= h($r['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="spender_name" class="form-control" placeholder="یا نام دستی">
                    </div>
                </div>

                <div class="col-md-2">
                    <select name="unit_id" class="form-select">
                        <option value="">— عمومی —</option>
                        <?php foreach ($units as $u):
                            $lbl = ($u['name'] ?? '') . ' - طبقه ' . (int)($u['floor'] ?? 0); ?>
                            <option value="<?= (int)$u['id'] ?>"><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <input type="text" name="notes" class="form-control" placeholder="توضیحات">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-success w-100" type="submit">ثبت هزینه</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- جدول -->
<div class="table-responsive">
    <table id="expensesTable" class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th style="width:70px" class="text-center"><?= sort_link_e('id', 'شناسه', $sort, $dir) ?></th>
                <th><?= sort_link_e('title', 'عنوان', $sort, $dir) ?></th>
                <th><?= sort_link_e('category', 'دسته', $sort, $dir) ?></th>
                <th><?= sort_link_e('expense_date', 'تاریخ هزینه', $sort, $dir) ?></th>
                <th><?= sort_link_e('spender_name', 'هزینه‌کننده', $sort, $dir) ?></th>
                <th><?= sort_link_e('created_at', 'ثبت', $sort, $dir) ?></th>
                <th style="width:150px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">موردی یافت نشد.</td>
                </tr>
                <?php else: foreach ($rows as $i => $r): ?>
                    <tr>
                        <td class="text-center" data-label="شناسه"><?= (int)($r['id'] ?? 0) ?></td>
                        <td data-label="عنوان" class="text-truncate"><?= h($r['title'] ?? '') ?></td>
                        <td data-label="دسته"><?= h($r['category'] ?? '') ?></td>
                        <td data-label="تاریخ هزینه" class="text-nowrap"><?= h(jdate($r['expense_date'] ?? null, 'Y/m/d')) ?>
                        </td>
                        <td data-label="هزینه‌کننده"><?= h($r['spender_name'] ?? '') ?></td>
                        <td data-label="ثبت" class="text-nowrap"><?= h(jdate($r['created_at'] ?? null, 'Y/m/d H:i')) ?></td>
                        <td class="text-center actions" data-label="عملیات">
                            <a class="btn btn-sm btn-primary"
                                href="index.php?page=expense_edit&id=<?= (int)($r['id'] ?? 0) ?>">ویرایش</a>
                            <form method="POST" action="index.php?page=expense_delete" class="d-inline"
                                onsubmit="return confirm('حذف این هزینه؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>

<script>
    // اگر لازم داری پلاگین تقویم شمسی را پس از باز شدن فرم اینیت کنی:
    document.addEventListener('shown.bs.collapse', function(ev) {
        if (ev.target && ev.target.id === 'expForm') {
            if (typeof window.initJdate === 'function') {
                try {
                    window.initJdate();
                } catch (e) {}
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
$paths = [__DIR__ . '/../layout.php', dirname(__DIR__, 1) . '/layout.php'];
foreach ($paths as $p) {
    if (is_file($p)) {
        include $p;
        return;
    }
}
echo $content;
