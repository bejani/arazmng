<?php ob_start();
/**
 * views/units/form.php — فرم ایجاد/ویرایش واحد
 * ورودی‌ها: $unit (آرایه یا null). اگر null باشد یعنی «ایجاد».
 */
require_once __DIR__ . '/../_helpers.php';

$isNew  = empty($unit) || empty($unit['id']);
$unitId = (int)($unit['id'] ?? 0);
$name   = trim((string)($unit['name'] ?? ''));
$floor  = (string)($unit['floor'] ?? '');
$active = array_key_exists('is_active', (array)$unit) ? ((int)$unit['is_active'] === 1) : true; // پیش‌فرض: فعال
$action = $isNew ? 'unit_store' : 'unit_update';
?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><?= $isNew ? 'واحد جدید' : 'ویرایش واحد' ?></h4>
    <a class="btn btn-secondary" href="index.php?page=units">بازگشت</a>
</div>

<form method="POST" action="index.php?page=<?= h($action) ?>" class="row g-3">
    <?php if (function_exists('csrf_field')): ?>
    <?= csrf_field() ?>
    <?php else: /* فالبک مطمئن اگر csrf_field نبود */ ?>
    <input type="hidden" name="_csrf"
        value="<?= h(function_exists('csrf_value') ? csrf_value() : ($_SESSION['_csrf'] ?? '')) ?>">
    <?php endif; ?>

    <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= $unitId ?>">
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label">نام واحد <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($name) ?>" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">طبقه</label>
        <input type="number" name="floor" class="form-control" value="<?= h($floor) ?>" inputmode="numeric">
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                <?= $active ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">فعال</label>
        </div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary"><?= $isNew ? 'ذخیره' : 'بروزرسانی' ?></button>
        <a class="btn btn-secondary" href="index.php?page=units">انصراف</a>
    </div>
</form>

<?php
$content = ob_get_clean();
/* اتصال به لایه (layout.php) مشابه سایر ویوها */
$paths = [
  dirname(__DIR__) . '/layout.php',     // /views/layout.php
  dirname(__DIR__, 2) . '/layout.php',  // /layout.php (ریشه)
  __DIR__ . '/layout.php',              // /views/units/layout.php (اگر وجود داشته باشد)
];
foreach ($paths as $p) {
  if (is_file($p)) {
    include $p;
    return;
  }
}
echo $content;