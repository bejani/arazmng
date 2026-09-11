<?php
echo "<!-- VIEW: ", __FILE__, " @ ", date('H:i:s'), " -->";
?>

<?php ob_start();


// 1) خواندن و اعتبارسنجی پارامترهای مرتب‌سازی
$allowedSort = ['name', 'floor', 'is_active'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort, true) ? $_GET['sort'] : 'name';
$dir  = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'desc' : 'asc';

// 2) سازنده لینک سرستون (حفظ پارامترها + فلش جهت)
function sort_link(string $key, string $label, string $currentSort, string $currentDir): string
{
    $q = $_GET;
    $q['sort'] = $key;
    $q['dir']  = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $href  = 'index.php?' . http_build_query($q);
    $arrow = ($currentSort === $key) ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . htmlspecialchars($href) . '" class="link-light text-decoration-none">' . $label . $arrow . '</a>';
}

// 3) مرتب‌سازی آرایه $units در خود ویو (در صورت ارسال توسط کنترلر)
$rows = [];
if (isset($units) && is_array($units)) {
    $rows = $units;
    usort($rows, function (array $a, array $b) use ($sort, $dir) {
        $va = $a[$sort] ?? null;
        $vb = $b[$sort] ?? null;

        if ($sort === 'is_active') {
            $va = !empty($a['is_active']) ? 1 : 0;
            $vb = !empty($b['is_active']) ? 1 : 0;
        }

        // برای floor تلاش می‌کنیم عددی مقایسه کنیم
        $isNum = fn($x) => is_int($x) || is_float($x) || (is_string($x) && is_numeric($x));
        if ($sort === 'floor' && $isNum($va) && $isNum($vb)) {
            $cmp = (int)$va <=> (int)$vb;
        } else {
            $va = mb_strtolower((string)$va, 'UTF-8');
            $vb = mb_strtolower((string)$vb, 'UTF-8');
            $cmp = strcmp($va, $vb);
        }
        return $dir === 'asc' ? $cmp : -$cmp;
    });
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>واحدها</h2>
    <a class="btn btn-primary" href="index.php?page=unit_edit">➕ افزودن واحد</a>
</div>

<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th style="width:70px" class="text-center">ردیف</th>
            <th><?= sort_link('name', 'نام واحد', $sort, $dir) ?></th>
            <th style="width:140px"><?= sort_link('floor', 'طبقه', $sort, $dir) ?></th>
            <th style="width:120px"><?= sort_link('is_active', 'وضعیت', $sort, $dir) ?></th>
            <th style="width:180px">عملیات</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $i => $u): ?>
            <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['floor']) ?></td>
                <td>
                    <?= !empty($u['is_active'])
                        ? '<span class="badge bg-success">فعال</span>'
                        : '<span class="badge bg-secondary">غیرفعال</span>' ?>
                </td>
                <td class="text-center">

                    <a class="btn btn-sm btn-primary" href="index.php?page=unit_edit&id=<?= (int)$u['id'] ?>">ویرایش</a>
                    <form method="POST" action="index.php?page=unit_toggle_active" class="d-inline"
                        onsubmit="return confirm('تغییر وضعیت این واحد؟');">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button
                            class="btn btn-sm btn-outline-warning"><?= !empty($u['is_active']) ? 'غیرفعال' : 'فعال' ?></button>
                    </form>
                    <!-- دکمه‌های انتساب مالک/مستأجر -->
                    <a class="btn btn-sm btn-outline-primary"
                        href="index.php?page=ownership_reassign&unit_id=<?= $u['id'] ?>&role=owner">
                        انتساب مالک
                    </a>

                    <a class="btn btn-sm btn-outline-secondary"
                        href="index.php?page=ownership_reassign&unit_id=<?= $u['id'] ?>&role=tenant">
                        انتساب مستأجر
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="5" class="text-center text-muted">رکوردی یافت نشد.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php $content = ob_get_clean();
include 'layout.php'; ?>