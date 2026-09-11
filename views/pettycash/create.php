<?php // views/pettycash/create.php
require_once __DIR__ . '/../_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(16)); }
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">ایجاد راند تنخواه</h3>
    <a href="index.php?page=pettycash" class="btn btn-outline-secondary">بازگشت</a>
</div>

<?php if (!empty($_SESSION['ok'])): ?>
<div class="alert alert-success"><?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="card card-elevated">
    <div class="card-body">
        <form method="post" action="index.php?page=pettycash_store">
            <!-- CSRF مطابق کنترلر: نام فیلد باید _csrf باشد -->
            <input type="hidden" name="_csrf" value="<?= h($_SESSION['_csrf']) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">عنوان</label>
                    <input type="text" name="title" class="form-control" placeholder="مثلاً: تنخواه آسانسور">
                </div>
                <div class="col-md-3">
                    <label class="form-label">دسته</label>
                    <input type="text" name="category" class="form-control" placeholder="elevator / pump / ...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">دوره</label>
                    <input type="text" name="period" class="form-control" placeholder="مثلاً 1403-07">
                </div>

                <div class="col-md-4">
                    <label class="form-label">هدف (تومان)</label>
                    <input type="number" name="target_amount" min="0" step="1" class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">روش توزیع</label>
                    <select name="method" class="form-select" id="pc_method">
                        <option value="equal" selected>تقسیم مساوی</option>
                        <option value="weights">بر اساس وزن/متراژ</option>
                        <option value="custom">مبلغ سفارشی برای هر واحد</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">توضیحات</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="توضیحات اختیاری..."></textarea>
                </div>
            </div>

            <hr>

            <h6 class="mb-2">واحدها</h6>
            <p class="text-muted small mb-2" id="pc_help">
                در «مساوی» نیازی به ورودی نیست. در «وزن»، برای هر واحد عدد وزن وارد کنید.
                در «سفارشی»، خودِ مبلغ هر واحد را وارد کنید.
            </p>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px">#</th>
                            <th>واحد</th>
                            <th>ورودی</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($units)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">واحدی یافت نشد.</td>
                        </tr>
                        <?php else: $i=1; foreach ($units as $u): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= h(trim(($u['name'] ?? '') . (isset($u['floor'])?' — طبقه '.(int)$u['floor']:''))) ?>
                            </td>
                            <td>
                                <div class="input-group">
                                    <span class="input-group-text">weights →</span>
                                    <input type="number" name="w[<?= (int)$u['id'] ?>]" step="0.01"
                                        class="form-control w-input" placeholder="وزن/متراژ" disabled>
                                    <span class="input-group-text">custom →</span>
                                    <input type="number" name="c[<?= (int)$u['id'] ?>]" step="1"
                                        class="form-control c-input" placeholder="مبلغ سفارشی (تومان)" disabled>
                                </div>
                                <div class="form-text">برای مساوی خالی بگذارید.</div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-success">ثبت و صدور فاکتور</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const sel = document.getElementById('pc_method');
    const setState = () => {
        const m = sel.value;
        document.querySelectorAll('.w-input').forEach(el => el.disabled = (m !== 'weights'));
        document.querySelectorAll('.c-input').forEach(el => el.disabled = (m !== 'custom'));
        const help = document.getElementById('pc_help');
        if (m === 'equal') help.textContent = 'در تقسیم مساوی نیازی به ورود وزن/مبلغ نیست.';
        else if (m === 'weights') help.textContent =
            'برای هر واحد عدد وزن/متراژ وارد کنید. سهم هر واحد متناسب با وزن تعیین می‌شود.';
        else help.textContent = 'برای هر واحد، مبلغ نهایی (تومان) را وارد کنید.';
    };
    sel.addEventListener('change', setState);
    setState();
})();
</script>