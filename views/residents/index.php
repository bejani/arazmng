<?php if (!empty($_SESSION['ok'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['ok']) ?></div>
    <?php unset($_SESSION['ok']); ?>
<?php endif; ?>

<?php ob_start();
require_once __DIR__ . '/../_helpers.php';

/**
 * انتظار:
 * $residents               : array
 * $unit_labels_for_view    : [resident_id => 'label']
 * $current_sort, $current_dir
 * $current_filters         : ['q','active','has_unit']
 * (اختیاری) $unit_units_for_view : [resident_id => [ ['id'=>...,'label'=>...], ... ] ]
 */
$rows        = $residents ?? [];
$unit_labels = $unit_labels_for_view ?? [];
$unit_units_for_view = $unit_units_for_view ?? [];
$sort        = $current_sort ?? (string)($_GET['sort'] ?? 'full_name');
$dir         = $current_dir  ?? ((strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc') ? 'desc' : 'asc');
$f           = $current_filters ?? ['q' => '', 'active' => 'all', 'has_unit' => ''];

function sort_link_res(string $key, string $label, string $cur, string $dir, array $filters): string
{
    $q = $_GET;
    $q['page']  = 'residents';
    $q['sort']  = $key;
    $q['dir']   = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    // حفظ فیلترها
    $q['q']        = $filters['q']        ?? '';
    $q['active']   = $filters['active']   ?? 'all';
    if (!empty($filters['has_unit'])) $q['has_unit'] = '1';
    else unset($q['has_unit']);

    $href  = 'index.php?' . http_build_query($q, '&', '&', PHP_QUERY_RFC3986);
    $arrow = ($cur === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . h($href) . '" class="text-white text-decoration-none">' . h($label) . $arrow . '</a>';
}
?>
<style>
    /* لینک‌های سرستون در تم تیره جدول */
    .table-dark th a {
        color: #fff !important;
    }

    .table-dark th a:hover {
        color: #f8f9fa !important;
        text-decoration: underline;
    }

    /* نوار بالای صفحه */
    .page-bar {
        background: var(--bs-light, #f8f9fa);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .75rem;
        padding: .5rem .75rem;
    }

    /* دکمه‌های کامپکت */
    .btn-compact {
        padding: .25rem .5rem;
        font-size: .8rem;
        line-height: 1.2;
    }

    /* قرص وضعیت + نقطه */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .35rem .55rem;
        font-weight: 600;
        border: 1px solid transparent;
        border-radius: 50rem;
    }

    .badge-status .dot {
        width: .5rem;
        height: .5rem;
        border-radius: 50%;
        display: inline-block;
    }

    .badge-status.active {
        color: rgb(var(--bs-success-rgb));
        background: rgba(var(--bs-success-rgb), .12);
        border-color: rgba(var(--bs-success-rgb), .35);
    }

    .badge-status.active .dot {
        background: rgb(var(--bs-success-rgb));
        box-shadow: 0 0 0 .15rem rgba(var(--bs-success-rgb), .25);
    }

    .badge-status.inactive {
        color: rgb(var(--bs-secondary-rgb));
        background: rgba(var(--bs-secondary-rgb), .12);
        border-color: rgba(var(--bs-secondary-rgb), .35);
    }

    .badge-status.inactive .dot {
        background: rgb(var(--bs-secondary-rgb));
    }

    /* جدول و حالت موبایل */
    #resTable thead th,
    #resTable tbody td {
        white-space: nowrap;
    }

    #resTable .text-truncate {
        max-width: 18rem;
    }

    @media (max-width: 576px) {
        #resTable thead {
            display: none;
        }

        #resTable tbody tr {
            display: block;
            margin-bottom: .75rem;
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: .75rem;
            padding: .5rem .75rem;
            background: var(--bs-body-bg, #fff);
        }

        #resTable tbody td {
            display: grid;
            grid-template-columns: 12ch 1fr;
            gap: .5rem .75rem;
            border: 0 !important;
            padding: .25rem 0 !important;
            white-space: normal;
        }

        #resTable tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            opacity: .75;
        }

        /* عملیات: دو ستونه و فشرده */
        #resTable tbody td.actions {
            grid-template-columns: 1fr 1fr;
            gap: .25rem;
        }

        #resTable tbody td.actions>* {
            display: block !important;
            margin: 0;
        }

        #resTable tbody td.actions a.btn,
        #resTable tbody td.actions button.btn {
            width: 100%;
        }

        html[dir="rtl"] #resTable tbody td {
            text-align: right;
        }

        html[dir="rtl"] #resTable tbody td::before {
            text-align: start;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 page-bar">
    <h3 class="mb-0">ساکنان</h3>
    <a href="index.php?page=residents_create" class="btn btn-primary btn-compact">➕ ساکن جدید</a>
</div>

<!-- فیلترها -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="residents">

            <div class="col-md-4">
                <label class="form-label">جست‌وجو (نام/موبایل)</label>
                <input type="text" name="q" value="<?= h($f['q'] ?? '') ?>" class="form-control"
                    placeholder="مثلاً احمد یا 0912...">
            </div>

            <div class="col-md-3">
                <label class="form-label">وضعیت</label>
                <select name="active" class="form-select">
                    <option value="all" <?= (($f['active'] ?? 'all') === 'all') ? 'selected' : '' ?>>همه</option>
                    <option value="1" <?= (($f['active'] ?? 'all') === '1') ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= (($f['active'] ?? 'all') === '0') ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="has_unit" name="has_unit" value="1"
                        <?= !empty($f['has_unit']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="has_unit">فقط دارای واحد</label>
                </div>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100">اعمال</button>
                <a href="index.php?page=residents" class="btn btn-outline-secondary w-100">پاکسازی</a>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table id="resTable" class="table table-striped table-bordered align-middle table-hover">
        <thead class="table-dark">
            <tr>
                <th class="text-center" style="width:60px"><?= sort_link_res('id', 'شناسه', $sort, $dir, $f) ?></th>
                <th><?= sort_link_res('full_name', 'نام', $sort, $dir, $f) ?></th>
                <th><?= sort_link_res('mobile', 'موبایل', $sort, $dir, $f) ?></th>
                <th><?= sort_link_res('unit', 'واحد / طبقه', $sort, $dir, $f) ?></th>
                <th class="text-center" style="width:140px"><?= sort_link_res('is_active', 'وضعیت', $sort, $dir, $f) ?>
                </th>
                <th class="text-center" style="width:260px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">موردی یافت نشد.</td>
                </tr>
                <?php else: foreach ($rows as $i => $r): $rid = (int)($r['id'] ?? 0); ?>
                    <tr>
                        <td class="text-center" data-label="شناسه"><?= $rid ?></td>
                        <td data-label="نام" class="text-truncate"><?= h($r['full_name'] ?? '') ?></td>
                        <td data-label="موبایل" dir="ltr" class="text-nowrap">
                            <?php $mob = h($r['mobile'] ?? ''); ?>
                            <?php if ($mob): ?><a href="tel:<?= $mob ?>"
                                    class="text-decoration-none"><?= $mob ?></a><?php else: ?>—<?php endif; ?>
                        </td>
                        <td data-label="واحد / طبقه"><?= h($unit_labels[$rid] ?? '—') ?></td>
                        <td class="text-center" data-label="وضعیت">
                            <?php $active = (int)($r['is_active'] ?? 1) === 1; ?>
                            <span class="badge-status <?= $active ? 'active' : 'inactive' ?>">
                                <span class="dot"></span><?= $active ? 'فعال' : 'غیرفعال' ?>
                            </span>
                        </td>
                        <td class="text-center actions" data-label="عملیات">
                            <?php
                            $uArr = $unit_units_for_view[$rid] ?? []; // آرایه‌ای از ['id','label']
                            ?>

                            <!-- ترتیب جدید: ویرایش → پرداخت شارژ → تغییر وضعیت → حذف -->
                            <a class="btn btn-sm btn-primary btn-compact"
                                href="index.php?page=resident_edit&id=<?= $rid ?>">ویرایش</a>

                            <?php if (count($uArr) === 1): ?>
                                <a class="btn btn-sm btn-success btn-compact"
                                    href="index.php?page=payment_new&unit_id=<?= (int)$uArr[0]['id'] ?>">
                                    پرداخت شارژ
                                </a>
                            <?php elseif (count($uArr) > 1): ?>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-success dropdown-toggle btn-compact"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        پرداخت شارژ
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php foreach ($uArr as $uu): ?>
                                            <li>
                                                <a class="dropdown-item"
                                                    href="index.php?page=payment_new&unit_id=<?= (int)$uu['id'] ?>">
                                                    <?= h($uu['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary btn-compact" disabled>پرداخت شارژ</button>
                            <?php endif; ?>

                            <form method="POST" action="index.php?page=resident_toggle_active" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $rid ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning btn-compact">
                                    <?= ((int)($r['is_active'] ?? 1) === 1) ? 'غیرفعال کن' : 'فعال کن' ?>
                                </button>
                            </form>

                            <form method="POST" action="index.php?page=resident_delete" class="d-inline"
                                onsubmit="return confirm('حذف این ساکن؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $rid ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-compact">حذف</button>
                            </form>
                        </td>

                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>

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
