<?php /* اعلان‌ها */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">اعلان‌ها</h4>
</div>

<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success">
        <?= h($_SESSION['ok']);
        unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
        <?= h($_SESSION['error']);
        unset($_SESSION['error']); ?></div><?php endif; ?>

<div class="row g-3">
    <?php if (!empty($items)): foreach ($items as $a): ?>
            <div class="col-12">
                <div class="card card-elevated">
                    <div class="card-body">
                        <div class="d-flex gap-2 align-items-center mb-2">
                            <?php if (!empty($a['is_pinned'])): ?><span class="badge text-bg-warning">مهم</span><?php endif; ?>
                            <h5 class="mb-0"><?= h($a['title']) ?></h5>
                        </div>
                        <div class="text-muted small mb-2"><?= h(jdate($a['created_at'] ?? null, 'Y/m/d H:i')) ?></div>
                        <div style="white-space:pre-wrap"><?= h($a['body']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach;
    else: ?>
        <div class="col-12 text-center text-muted">اعلانی ثبت نشده است.</div>
    <?php endif; ?>
</div>