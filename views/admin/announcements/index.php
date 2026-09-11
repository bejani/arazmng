<?php ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">اعلان‌ها</h4>
    <a class="btn btn-primary" href="index.php?page=admin_announcement_new">اعلان جدید</a>
</div>

<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success">
        <?= h($_SESSION['ok']);
        unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
        <?= h($_SESSION['error']);
        unset($_SESSION['error']); ?></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle table-modern">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>عنوان</th>
                <th>پین</th>
                <th>نمایش</th>
                <th>ایجاد</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): $i = 1;
                foreach ($rows as $r): ?>
                    <?php
                    $pinBadge = !empty($r['is_pinned'])
                        ? '<span class="badge text-bg-warning">پین</span>' : '—';
                    $visBadge = !empty($r['visible_in_portal'])
                        ? '<span class="badge text-bg-success">نمایش</span>' : '<span class="badge text-bg-secondary">مخفی</span>';
                    ?>
                    <tr>
                        <td data-label="#"><?= $i++ ?></td>
                        <td data-label="عنوان"><?= h($r['title']) ?></td>
                        <td data-label="پین"><?= $pinBadge ?></td>
                        <td data-label="نمایش"><?= $visBadge ?></td>
                        <td data-label="ایجاد"><?= h(jdate($r['created_at'] ?? null, 'Y/m/d H:i')) ?></td>
                        <td data-label="عملیات" class="table-actions">
                            <a class="btn btn-sm btn-primary"
                                href="index.php?page=admin_announcement_edit&id=<?= (int)$r['id'] ?>">ویرایش</a>
                            <form method="POST" action="index.php?page=admin_announcement_toggle_pin" class="d-inline">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button
                                    class="btn btn-sm btn-outline-warning"><?= !empty($r['is_pinned']) ? 'برداشتن پین' : 'پین کردن' ?></button>
                            </form>
                            <form method="POST" action="index.php?page=admin_announcement_toggle_visibility" class="d-inline">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button
                                    class="btn btn-sm btn-outline-secondary"><?= !empty($r['visible_in_portal']) ? 'مخفی کن' : 'نمایش بده' ?></button>
                            </form>
                            <form method="POST" action="index.php?page=admin_announcement_delete" class="d-inline"
                                onsubmit="return confirm('حذف شود؟')">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">اعلانی وجود ندارد.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>