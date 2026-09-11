<?php ob_start();
/** views/portal/change_pin.php (بهبود یافته) */
require_once __DIR__ . '/../_helpers.php';
$force = !empty($_GET['force']);
?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">تغییر رمز پورتال</h4>
    <?php if (!$force): ?>
        <a class="btn btn-secondary" href="index.php?page=portal_dashboard">بازگشت</a>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= h($_SESSION['error']);
                                    unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if ($force): ?>
    <div class="alert alert-warning">برای افزایش امنیت، لطفاً پیش از ادامه رمز خود را تغییر دهید.</div>
<?php endif; ?>

<form method="POST" action="index.php?page=portal_do_change_pin" class="row g-3 needs-validation" novalidate>
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>

    <div class="col-12 col-md-4">
        <label class="form-label">رمز فعلی</label>
        <div class="position-relative">
            <input type="password" name="current_pin" id="current_pin" class="form-control"
                autocomplete="current-password" required>
            <button type="button"
                class="btn btn-sm btn-outline-secondary position-absolute top-50 translate-middle-y end-0 me-2"
                onclick="toggleVis('current_pin', this)">نمایش</button>
        </div>
        <div class="invalid-feedback">رمز فعلی را وارد کنید.</div>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">رمز جدید</label>
        <div class="position-relative">
            <input type="password" name="new_pin" id="new_pin" class="form-control" autocomplete="new-password"
                minlength="4" required>
            <!-- اگر PIN فقط عددی می‌خواهید، این دو ویژگی را فعال کنید: -->
            <!-- inputmode="numeric" pattern="[0-9]{4,32}" -->
            <button type="button"
                class="btn btn-sm btn-outline-secondary position-absolute top-50 translate-middle-y end-0 me-2"
                onclick="toggleVis('new_pin', this)">نمایش</button>
        </div>
        <div class="form-text">حداقل ۴ کاراکتر. (می‌توانید قوی‌تر انتخاب کنید)</div>
        <div class="invalid-feedback">لطفاً رمز جدید معتبر وارد کنید (حداقل ۴ کاراکتر).</div>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">تکرار رمز جدید</label>
        <div class="position-relative">
            <input type="password" name="new_pin2" id="new_pin2" class="form-control" autocomplete="new-password"
                required>
            <button type="button"
                class="btn btn-sm btn-outline-secondary position-absolute top-50 translate-middle-y end-0 me-2"
                onclick="toggleVis('new_pin2', this)">نمایش</button>
        </div>
        <div class="invalid-feedback">تکرار رمز جدید با رمز جدید یکی نیست.</div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary">تغییر رمز</button>
    </div>
</form>

<script>
    // نمایش/مخفی‌کردن رمز
    function toggleVis(id, btn) {
        const inp = document.getElementById(id);
        if (!inp) return;
        const to = (inp.type === 'password') ? 'text' : 'password';
        inp.type = to;
        btn.textContent = (to === 'text') ? 'مخفی' : 'نمایش';
    }

    // ولیدیشن Bootstrap + چک برابر بودن رمزها
    (function() {
        const form = document.querySelector('form.needs-validation');
        const new1 = document.getElementById('new_pin');
        const new2 = document.getElementById('new_pin2');

        function checkMatch() {
            if (!new1 || !new2) return true;
            const ok = new1.value === new2.value && new1.value.length >= 4;
            if (!ok) new2.setCustomValidity('mismatch');
            else new2.setCustomValidity('');
            return ok;
        }

        if (new1 && new2) {
            new1.addEventListener('input', checkMatch);
            new2.addEventListener('input', checkMatch);
        }

        form.addEventListener('submit', function(e) {
            if (!checkMatch() || !form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    })();
</script>

<?php
$content = ob_get_clean();
$paths = [
    dirname(__DIR__) . '/layout.php',
    dirname(__DIR__, 2) . '/layout.php',
];
foreach ($paths as $p) if (is_file($p)) {
    include $p;
    return;
}
echo $content;
