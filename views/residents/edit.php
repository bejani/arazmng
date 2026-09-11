<?php ob_start();
// views/residents_edit.php (یا هر نامی که استفاده می‌کنی)

// هِلپر خروجی امن
require_once __DIR__ . '/../_helpers.php';
if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$is_edit      = isset($resident) && is_array($resident);
$action_page  = $is_edit ? 'resident_update' : 'resident_store';
$btn_title    = $is_edit ? 'ویرایش ساکن' : '➕ افزودن ساکن';
$collapse_cls = $is_edit ? 'collapse mb-3 show' : 'collapse mb-3';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>ساکنان</h2>
    <button class="btn btn-primary" data-bs-toggle="collapse"
        data-bs-target="#residentForm"><?= h($btn_title) ?></button>
</div>

<div id="residentForm" class="<?= h($collapse_cls) ?>">
    <div class="card card-body">
        <form method="POST" action="index.php?page=<?= h($action_page) ?>" class="row g-3">
            <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?= (int)($resident['id'] ?? 0) ?>">
            <?php endif; ?>

            <div class="col-md-3">
                <input class="form-control" type="text" name="full_name" placeholder="نام کامل" required
                    value="<?= h($resident['full_name'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input class="form-control" type="text" name="mobile" placeholder="موبایل"
                    value="<?= h($resident['mobile'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input class="form-control" type="email" name="email" placeholder="ایمیل"
                    value="<?= h($resident['email'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input class="form-control" type="text" name="national_id" placeholder="کد ملی"
                    value="<?= h($resident['national_id'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <?php $type = $resident['type'] ?? 'owner'; ?>
                <select name="type" class="form-select">
                    <option value="owner" <?= $type === 'owner'  ? 'selected' : '' ?>>مالک</option>
                    <option value="tenant" <?= $type === 'tenant' ? 'selected' : '' ?>>مستأجر</option>
                </select>
            </div>

            <!-- PIN پرتال (اختیاری)؛ اگر خالی بماند تغییری اعمال نمی‌شود -->
            <div class="col-md-2">
                <input class="form-control" type="password" name="portal_pin" placeholder="PIN پرتال (اختیاری)"
                    inputmode="numeric" pattern="\d{4,6}">
                <div class="form-text">اگر خالی بماند، PIN قبلی حفظ می‌شود.</div>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <?php $active = (int)($resident['is_active'] ?? 1); ?>
                <!-- فالبک: اگر چک‌باکس تیک نخورد، مقدار 0 ارسال شود -->
                <input type="hidden" name="is_active" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                        <?= $active ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">فعال</label>
                </div>
            </div>


            <div class="col-md-1">
                <button class="btn btn-success w-100"><?= $is_edit ? 'بروزرسانی' : 'ذخیره' ?></button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($residents) && is_array($residents)) : ?>
<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th style="width:70px">ردیف</th>
            <th>نام</th>
            <th>موبایل</th>
            <th>ایمیل</th>
            <th>کد ملی</th>
            <th>نوع</th>
            <th>وضعیت</th>
            <th style="width:150px">عملیات</th>
        </tr>
    </thead>
    <tbody>
        <?php $row = 1;
            foreach ($residents as $r): ?>
        <tr>
            <td class="text-center"><?= $row++ ?></td>
            <td><?= h($r['full_name'] ?? '') ?></td>
            <td><?= h($r['mobile'] ?? '') ?></td>
            <td><?= h($r['email'] ?? '') ?></td>
            <td><?= h($r['national_id'] ?? '') ?></td>
            <td><?= ($r['type'] ?? '') === 'owner' ? 'مالک' : 'مستأجر' ?></td>
            <td>
                <?= !empty($r['is_active'])
                            ? '<span class="badge bg-success">فعال</span>'
                            : '<span class="badge bg-secondary">غیرفعال</span>' ?>
            </td>
            <td class="text-center">
                <a class="btn btn-sm btn-primary"
                    href="index.php?page=resident_edit&id=<?= (int)($r['id'] ?? 0) ?>">ویرایش</a>
                <form method="POST" action="index.php?page=resident_toggle_active" class="d-inline"
                    onsubmit="return confirm('تغییر وضعیت این ساکن؟');">
                    <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                    <button class="btn btn-sm btn-outline-warning">
                        <?= !empty($r['is_active']) ? 'غیرفعال' : 'فعال' ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
// تلاش برای یافتن layout.php (ریشه یا views/)
$paths = [
    __DIR__ . '/layout.php',       // views/layout.php
    dirname(__DIR__) . '/layout.php', // ریشه پروژه
];
foreach ($paths as $p) {
    if (is_file($p)) {
        include $p;
        return;
    }
}
echo $content; // اگر لایه نبود، خام
?>