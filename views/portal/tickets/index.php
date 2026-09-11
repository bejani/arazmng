<?php /* لیست تیکت‌ها */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">تیکت‌های من</h4>
    <a class="btn btn-primary" href="index.php?page=portal_ticket_new">تیکت جدید</a>
</div>

<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success">
    <?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
    <?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle responsive-cards table-modern">
        <thead>
            <tr>
                <th>#</th>
                <th>عنوان</th>
                <th>وضعیت</th>
                <th>ایجاد</th>
                <th>به‌روزرسانی</th>
                <th style="width:120px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): $i=1; foreach($rows as $t): ?>
            <?php
          $status = (string)$t['status'];
          $chip = 'chip chip-unpaid'; $label='باز';
          if ($status==='pending'){ $chip='chip chip-partial'; $label='در حال رسیدگی'; }
          if ($status==='closed'){  $chip='chip chip-paid';    $label='بسته'; }
        ?>
            <tr>
                <td data-label="#"><?= $i++ ?></td>
                <td data-label="عنوان"><?= h($t['subject']) ?></td>
                <td data-label="وضعیت"><span class="<?= $chip ?>"><?= h($label) ?></span></td>
                <td data-label="ایجاد"><?= h(jdate($t['created_at'] ?? null, 'Y/m/d H:i')) ?></td>
                <td data-label="به‌روزرسانی"><?= h(jdate($t['updated_at'] ?? null, 'Y/m/d H:i')) ?></td>
                <td data-label="عملیات" class="table-actions">
                    <a class="btn btn-sm btn-outline-primary"
                        href="index.php?page=portal_ticket_show&id=<?= (int)$t['id'] ?>">مشاهده</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="6" class="text-center text-muted">تیکتی ثبت نکرده‌اید.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>