<?php
echo "<!-- VIEW: ", __FILE__, " @ ", date('H:i:s'), " -->";
?>
<?php ob_start();
/**
 * views/units/index.php — با لایه‌بندی (layout.php)
 * این نسخه مسیر layout.php را به‌صورت امن پیدا می‌کند تا خطای include برطرف شود.
 */
$allowedSort = ['name', 'floor', 'is_active'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort, true) ? $_GET['sort'] : 'name';
$dir  = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'desc' : 'asc';
function sort_link(string $key, string $label, string $currentSort, string $currentDir): string
{
    $q = $_GET;
    $q['sort'] = $key;
    $q['dir'] = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $href = 'index.php?' . http_build_query($q);
    $arrow = ($currentSort === $key) ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . htmlspecialchars($href) . '" class="link-light text-decoration-none">' . $label . $arrow . '</a>';
}
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>واحدها</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* --- Table header links in dark header --- */
        .table-dark th a {
            color: #fff !important;
        }

        .table-dark th a:hover,
        .table-dark th a:focus {
            color: #f8f9fa !important;
            text-decoration: underline;
        }

        /* --- Compact buttons (desktop & mobile) --- */
        .btn-compact {
            padding: .25rem .5rem;
            font-size: .8rem;
            line-height: 1.2;
        }

        /* --- Nicer header bar --- */
        .page-bar {
            background: var(--bs-light, #f8f9fa);
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: .75rem;
            padding: .5rem .75rem;
        }

        /* --- Status pill with dot --- */
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

        /* --- Table polish --- */
        #unitsTable {
            overflow: hidden;
        }

        #unitsTable thead th,
        #unitsTable tbody td {
            white-space: nowrap;
        }

        #unitsTable tbody tr:hover {
            background-color: var(--bs-table-hover-bg, rgba(0, 0, 0, .03));
        }

        #unitsTable .text-truncate {
            max-width: 18rem;
        }

        @media (max-width: 576px) {
            #unitsTable {
                width: 100%;
            }

            #unitsTable thead {
                display: none;
            }

            #unitsTable tbody tr {
                display: block;
                margin-bottom: .75rem;
                border: 1px solid var(--bs-border-color, #dee2e6);
                border-radius: .75rem;
                padding: .5rem .75rem;
                background: var(--bs-body-bg, #fff);
            }

            #unitsTable tbody td {
                display: grid;
                grid-template-columns: 12ch 1fr;
                gap: .5rem .75rem;
                border: 0 !important;
                padding: .25rem 0 !important;
                white-space: normal;
            }

            #unitsTable tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                opacity: .75;
            }

            /* ستون عملیات: دو ستونه، دکمه‌ها کوچیک */
            #unitsTable tbody td.actions {
                grid-template-columns: 1fr 1fr;
                gap: .25rem;
            }

            #unitsTable tbody td.actions>* {
                display: block !important;
                margin: 0;
            }

            #unitsTable tbody td.actions a.btn,
            #unitsTable tbody td.actions button.btn {
                width: 100%;
            }

            html[dir="rtl"] #unitsTable tbody td {
                text-align: right;
            }

            html[dir="rtl"] #unitsTable tbody td::before {
                text-align: start;
            }
        }
    </style>
</head>

<body class="p-3">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 page-bar">
            <h3 class="mb-0">واحدها</h3>
            <a class="btn btn-primary btn-compact" href="index.php?page=unit_edit">
                <i class="bi bi-plus-lg ms-1"></i>افزودن واحد
            </a>
        </div>

        <div class="table-responsive">
            <table id="unitsTable" class="table table-striped table-bordered align-middle table-hover">
                <thead class="table-dark">
                    <tr>
                        <th style="width:70px" class="text-center">ردیف</th>
                        <th><?= sort_link('name', 'نام واحد', $sort, $dir) ?></th>
                        <th style="width:140px"><?= sort_link('floor', 'طبقه', $sort, $dir) ?></th>
                        <th style="width:140px"><?= sort_link('is_active', 'وضعیت', $sort, $dir) ?></th>
                        <th style="width:240px">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($units ?? []) as $i => $u): ?>
                        <?php $isActive = !empty($u['is_active']); ?>
                        <tr>
                            <td class="text-center" data-label="ردیف"><?= $i + 1 ?></td>
                            <td data-label="نام واحد" class="text-truncate"><?= htmlspecialchars($u['name']) ?></td>
                            <td data-label="طبقه" class="text-nowrap"><?= htmlspecialchars($u['floor']) ?></td>
                            <td data-label="وضعیت">
                                <span class="badge-status <?= $isActive ? 'active' : 'inactive' ?>">
                                    <span class="dot"></span><?= $isActive ? 'فعال' : 'غیرفعال' ?>
                                </span>
                            </td>
                            <td class="text-center actions" data-label="عملیات">
                                <a class="btn btn-sm btn-success btn-compact"
                                    href="index.php?page=charge_collect&period=<?= date('Y-m') ?>&unit_id=<?= (int)$u['id'] ?>">
                                    <i class="bi bi-cash-coin ms-1"></i>پرداخت
                                </a>

                                <a class="btn btn-sm btn-primary btn-compact"
                                    href="index.php?page=unit_edit&id=<?= (int)$u['id'] ?>">
                                    <i class="bi bi-pencil ms-1"></i>ویرایش
                                </a>
                                <form method="POST" action="index.php?page=unit_toggle_active" class="d-inline"
                                    onsubmit="return confirm('تغییر وضعیت این واحد؟');">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <?php if (function_exists('csrf_field')): ?>
                                        <?= csrf_field() ?>
                                    <?php else: ?>
                                        <input type="hidden" name="_csrf"
                                            value="<?= h(function_exists('csrf_value') ? csrf_value() : ($_SESSION['_csrf'] ?? '')) ?>">
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-warning btn-compact">
                                        <i class="bi bi-power ms-1"></i><?= $isActive ? 'غیرفعال' : 'فعال' ?>
                                    </button>
                                </form>
                                <!-- دکمه‌های انتساب مالک/مستأجر -->
                                <a class="btn btn-sm btn-outline-primary btn-compact"
                                    href="index.php?page=ownership_reassign&unit_id=<?= (int)$u['id'] ?>&role=owner">
                                    <i class="bi bi-person-badge ms-1"></i>مالک
                                </a>

                                <a class="btn btn-sm btn-outline-secondary btn-compact"
                                    href="index.php?page=ownership_reassign&unit_id=<?= (int)$u['id'] ?>&role=tenant">
                                    <i class="bi bi-people ms-1"></i>مستأجر
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($units)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">رکوردی یافت نشد.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php
// --- لایه‌بندی ---
$content = ob_get_clean();

// مسیرهای احتمالی layout.php (یکی را پیدا و include می‌کنیم)
$candidates = [
    __DIR__ . '/../layout.php',       // views/layout.php
    __DIR__ . '/../../layout.php',    // ریشه پروژه/layout.php
    __DIR__ . '/layout.php',          // همین پوشه (units/layout.php)
];

$layout = null;
foreach ($candidates as $p) {
    if (file_exists($p)) {
        $layout = $p;
        break;
    }
}

// اگر پیدا شد include، وگرنه محتوای خام را نمایش بدهیم (بدون لایه)
if ($layout) {
    include $layout;
} else {
    echo $content;
}
?>