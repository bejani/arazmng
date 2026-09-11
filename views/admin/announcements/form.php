<?php ob_start();
$isEdit = ($mode === 'edit'); ?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><?= $isEdit ? 'ویرایش اعلان' : 'اعلان جدید' ?></h4>
    <a class="btn btn-secondary" href="index.php?page=admin_announcements">بازگشت</a>
</div>

<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
        <?= h($_SESSION['error']);
        unset($_SESSION['error']); ?></div><?php endif; ?>

<form method="POST" action="index.php?page=<?= $isEdit ? 'admin_announcement_update' : 'admin_announcement_store' ?>"
    class="row g-3">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$ann['id'] ?>"><?php endif; ?>

    <div class="col-12">
        <label class="form-label">عنوان</label>
        <input type="text" name="title" class="form-control" required maxlength="200"
            value="<?= h($ann['title'] ?? '') ?>">
    </div>

    <div class="col-12">
        <label class="form-label">متن اعلان</label>
        <textarea name="body" rows="8" class="form-control" required><?= h($ann['body'] ?? '') ?></textarea>
    </div>

    <div class="col-12 d-flex gap-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_pinned" id="is_pinned"
                <?= !empty($ann['is_pinned']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_pinned">پین شود</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="visible_in_portal" id="visible_in_portal"
                <?= array_key_exists('visible_in_portal', $ann) ? (!empty($ann['visible_in_portal']) ? 'checked' : '') : 'checked' ?>>
            <label class="form-check-label" for="visible_in_portal">نمایش در پورتال</label>
        </div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'ثبت تغییرات' : 'ذخیره اعلان' ?></button>
    </div>
</form>
<?php
// $content = ob_get_clean();
// include __DIR__ . '/../../layout.php';