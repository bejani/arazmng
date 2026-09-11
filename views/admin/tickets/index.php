<?php ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">تیکت‌ها</h4>
    <form class="d-flex gap-2" method="GET">
        <input type="hidden" name="page" value="admin_tickets">
        <?php $st = $_GET['status'] ?? 'all'; ?>
        <select class="form-select" name="status" style="width:140px">
            <option value="all" <?= $st==='all'?'selected':'' ?>>همه</option>
            <option value="open" <?= $st==='open'?'selected':'' ?>>باز</option>
            <option value="pending" <?= $st==='pending'?'selected':'' ?>>در حال رسیدگی</option>
            <option value="closed" <?= $st==='closed'?'selected':'' ?>>بسته</option>
        </select>
        <input class="form-control" name="q" placeholder="جستجو در عنوان/نام" value="<?= h($_GET['q'] ?? '') ?>">
        <button class="btn btn-outline-primary">اعمال</button>
    </form>
</div>

<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success">
    <?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
    <?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle table-modern">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>عنوان</th>
                <th>ساکن</th>
                <th>وضعیت</th>
                <th>ایجاد</th>
                <th>به‌روزرسانی</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): $i=1; foreach($rows as $t): ?>
            <?php
          $status = (string)$t['status'];
          $badge='secondary'; $label='باز';
          if ($status==='pending'){ $badge='warning text-dark'; $label='در حال رسیدگی'; }
          if ($status==='closed'){  $badge='success';           $label='بسته'; }
        ?>
            <tr>
                <td data-label="#"><?= $i++ ?></td>
                <td data-label="عنوان"><?= h($t['subject']) ?></td>
                <td data-label="ساکن"><?= h($t['full_name'] ?? '—') ?></td>
                <td data-label="وضعیت"><span class="badge bg-<?= $badge ?>"><?= h($label) ?></span></td>
                <td data-label="ایجاد"><?= h(jdate($t['created_at'] ?? null, 'Y/m/d H:i')) ?></td>
                <td data-label="به‌روزرسانی"><?= h(jdate($t['updated_at'] ?? null, 'Y/m/d H:i')) ?></td>
                <td data-label="عملیات" class="table-actions">
                    <a class="btn btn-sm btn-primary"
                        href="index.php?page=admin_ticket_show&id=<?= (int)$t['id'] ?>">مشاهده/پاسخ</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="7" class="text-center text-muted">تیکتی یافت نشد.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>