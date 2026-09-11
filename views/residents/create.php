<?php ob_start();
/** views/residents/create.php — فرم افزودن ساکن جدید
 * متغیرهای ورودی: $has_portal_pin (bool)
 */
require_once __DIR__ . '/../_helpers.php';
?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4>ساکن جدید</h4>
    <a class="btn btn-secondary" href="index.php?page=residents">بازگشت</a>
</div>

<form method="POST" action="index.php?page=resident_store" class="row g-3">
    <?php if (function_exists('csrf_field')): ?>
    <?= csrf_field() ?>
    <?php else: ?>
    <input type="hidden" name="_csrf"
        value="<?= h(function_exists('csrf_value') ? csrf_value() : ($_SESSION['_csrf'] ?? '')) ?>">
    <?php endif; ?>
    <div class="col-md-6">
        <label class="form-label">نام و نام‌خانوادگی <span class="text-danger">*</span></label>
        <input type="text" name="full_name" class="form-control" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">موبایل</label>
        <input type="text" name="mobile" class="form-control" placeholder="09xxxxxxxxx">
        <div class="form-text">در صورت تکراری بودن، خطا داده می‌شود.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">ایمیل</label>
        <input type="email" name="email" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">کد ملی</label>
        <input type="text" name="national_id" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">نوع</label>
        <select name="type" class="form-select">
            <option value="owner">مالک</option>
            <option value="tenant">مستأجر</option>
        </select>
    </div>

    <?php if (!empty($has_portal_pin)): ?>
    <div class="col-md-3">
        <label class="form-label">PIN پرتال</label>
        <input type="password" name="portal_pin" class="form-control" autocomplete="new-password">
        <div class="form-text">در صورت پرکردن، به صورت امن (bcrypt) ذخیره می‌شود.</div>
    </div>
    <?php endif; ?>

    <div class="col-12">
        <button class="btn btn-primary">ذخیره</button>
        <a class="btn btn-secondary" href="index.php?page=residents">انصراف</a>
    </div>
</form>

<?php
$content = ob_get_clean();
$paths = [
    dirname(__DIR__) . '/layout.php',
    dirname(__DIR__, 2) . '/layout.php',
];
foreach ($paths as $p) {
    if (is_file($p)) {
        include $p;
        return;
    }
}
echo $content;