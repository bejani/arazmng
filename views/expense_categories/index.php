<?php ob_start();
require_once __DIR__ . '/../_helpers.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">دسته‌های هزینه</h3>
    <a href="index.php?page=expenses" class="btn btn-secondary">بازگشت به هزینه‌ها</a>
</div>

<?php if (!empty($_SESSION['ok'])): ?>
<div class="alert alert-success"><?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div>
<?php elseif (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="index.php?page=expense_category_store" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="name" class="form-control" placeholder="نام دسته (مثلاً برق، نگهبانی…)"
                    required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-success w-100">افزودن</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th style="width:70px" class="text-center">#</th>
            <th>نام دسته</th>
            <th>وضعیت</th>
            <th style="width:160px">عملیات</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (($cats ?? []) as $i => $c): ?>
        <tr>
            <td class="text-center"><?= $i+1 ?></td>
            <td><?= h($c['name'] ?? '') ?></td>
            <td><?= !empty($c['is_active']) ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>' ?>
            </td>
            <td class="text-center">
                <a class="btn btn-sm btn-primary"
                    href="index.php?page=expense_category_edit&id=<?= (int)$c['id'] ?>">ویرایش</a>
                <form method="POST" action="index.php?page=expense_category_delete" class="d-inline"
                    onsubmit="return confirm('حذف این دسته؟');">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cats)): ?>
        <tr>
            <td colspan="4" class="text-center text-muted">هنوز دسته‌ای ثبت نشده.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
$paths = [__DIR__.'/../layout.php', dirname(__DIR__,1).'/layout.php'];
foreach ($paths as $p) { if (is_file($p)) { include $p; return; } }
echo $content;