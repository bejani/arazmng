<?php ob_start();
/**
 * views/ownerships/reassign.php — فرم جابجایی/انتساب مالکیت
 * فرض: $unit, $current, $residents, $role موجودند
 */
require_once __DIR__ . '/../_helpers.php';
$unitLabel = isset($unitLabel) ? $unitLabel : ($unit['name'] ?? ('#' . ($unit['id'] ?? '')));
?>
<div class="mb-3">
    <h4>انتساب <?= $role === 'owner' ? 'مالک' : 'مستأجر' ?> برای واحد: <?= h($unitLabel) ?></h4>
    <?php if (!empty($current)): ?>
    <div class="alert alert-info">
        مالکیت فعال فعلی: <?= h($current['full_name'] ?? '') ?> (از <?= h($current['start_date'] ?? '') ?>)
    </div>
    <?php endif; ?>
</div>

<form method="POST" action="index.php?page=ownership_reassign_store" class="row g-3">
    <form method="POST" action="index.php?page=ownership_reassign_store" class="row g-3">
        <?php if (function_exists('csrf_field')): ?>
        <?= csrf_field() ?>
        <?php else: ?>
        <input type="hidden" name="_csrf"
            value="<?= h(function_exists('csrf_value') ? csrf_value() : ($_SESSION['_csrf'] ?? '')) ?>">
        <?php endif; ?>
        <input type="hidden" name="role" value="<?= h($role) ?>">
        <input type="hidden" name="unit_id" value="<?= (int)($unit['id'] ?? 0) ?>">

        <div class="col-md-6">
            <label class="form-label">ساکن</label>
            <select name="resident_id" class="form-select" required>
                <option value="">انتخاب کنید…</option>
                <?php foreach ($residents as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= h($r['full_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">تاریخ شروع</label>
            <input type="date" name="start_date" class="form-control" value="<?= h(date('Y-m-d')) ?>">
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="auto_close" id="auto_close" checked>
                <label class="form-check-label" for="auto_close">بستن مالکیت فعال قبلی</label>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary">ذخیره</button>
            <a class="btn btn-secondary" href="index.php?page=units">بازگشت</a>
        </div>
    </form>

    <?php
$content = ob_get_clean();
$paths = [
    dirname(__DIR__) . '/layout.php',        // /views/layout.php
    dirname(__DIR__, 2) . '/layout.php',     // /layout.php (project root)
];
foreach ($paths as $p) {
    if (is_file($p)) { include $p; return; }
}
// Fallback: render raw content if no layout found
echo $content;
?>