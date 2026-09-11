<?php ob_start();
require_once __DIR__ . '/../_helpers.php'; // h() + csrf_field()

// ---- لوگوی ادمین (اختیاری) ----
// فایل لوگو را در assets/logo-admin.png بگذار؛ در غیر این صورت fallback حروف ADM نمایش داده می‌شود.
$logoUrl = 'assets/logo-admin.png';
$logoFs  = __DIR__ . '/../assets/logo-admin.png';
$hasLogo = is_file($logoFs);
$brandInitials = 'ADM';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card admin-login-card border-0 shadow-lg">
            <!-- هدر تیره با لوگو -->
            <div class="p-4 p-md-5 text-white admin-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="admin-brand-badge">
                        <?php if ($hasLogo): ?>
                            <img src="<?= h($logoUrl) ?>" alt="لوگو ادمین" class="admin-brand-logo">
                        <?php else: ?>
                            <div class="admin-brand-fallback"><?= h($brandInitials) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-1">ورود ادمین</h4>
                        <small class="text-white-50">دسترسی مدیریت پنل</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-md-5">
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger mb-4"><?= h($_SESSION['error']);
                                                            unset($_SESSION['error']); ?></div>
                <?php elseif (!empty($_SESSION['ok'])): ?>
                    <div class="alert alert-success mb-4"><?= h($_SESSION['ok']);
                                                            unset($_SESSION['ok']); ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=admin_do_login" class="needs-validation" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">رمز ادمین</label>
                        <div class="input-group">
                            <span class="input-group-text">🔒</span>
                            <input type="password" name="password" class="form-control" placeholder="رمز عبور" required
                                autocomplete="current-password" minlength="4" id="adminPwd">
                            <button class="btn btn-outline-secondary" type="button" id="toggleAdminPwd">نمایش</button>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-admin w-100" type="submit">ورود به پنل مدیریت</button>
                        <div class="text-center">
                            <a href="index.php?page=portal_login" class="link-secondary small">ورود پورتال ساکنان</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* کارت و هدر تیره */
    .admin-login-card {
        border-radius: 1rem;
        overflow: hidden;
        background: #0b1020;
    }

    .admin-header {
        background: radial-gradient(1200px 400px at 10% -10%, #1f2937 10%, transparent 60%),
            linear-gradient(135deg, #0f172a 0%, #111827 45%, #0b1020 100%);
        border-bottom: 1px solid rgba(255, 255, 255, .05);
    }

    /* نشان/لوگوی ادمین */
    .admin-brand-badge {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: rgba(255, 255, 255, .08);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 24px rgba(0, 0, 0, .35);
        overflow: hidden;
    }

    .admin-brand-logo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .admin-brand-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.05rem;
        letter-spacing: .6px;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0, 0, 0, .35);
    }

    /* دکمه سنگین ادمین */
    .btn-admin {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .8rem 1rem;
        border-radius: .75rem;
        background: linear-gradient(135deg, #111827 0%, #0f172a 60%, #1f2937 100%);
        color: #fff;
        font-weight: 700;
        letter-spacing: .3px;
        border: 1px solid rgba(255, 255, 255, .08);
        box-shadow: 0 10px 28px rgba(2, 6, 23, .45), inset 0 0 0 1px rgba(255, 255, 255, .04);
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    }

    .btn-admin:hover {
        transform: translateY(-2px);
        filter: brightness(1.08);
        color: #fff;
        box-shadow: 0 14px 36px rgba(2, 6, 23, .55), inset 0 0 0 1px rgba(255, 255, 255, .06);
    }

    .btn-admin:focus {
        outline: 0;
        box-shadow: 0 0 0 .2rem rgba(17, 24, 39, .35);
    }

    /* ورودی‌ها روی بک‌گراند تیره */
    .admin-login-card .form-control,
    .admin-login-card .input-group-text,
    .admin-login-card .btn.btn-outline-secondary {
        background-color: #0f172a;
        color: #e5e7eb;
        border-color: #1f2937;
    }

    .admin-login-card .form-control::placeholder {
        color: #9ca3af;
    }

    .admin-login-card .btn.btn-outline-secondary:hover {
        background: #111827;
        color: #fff;
        border-color: #2a3648;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // نمایش/پنهان کردن رمز ادمین
        var btn = document.getElementById('toggleAdminPwd');
        var input = document.getElementById('adminPwd');
        if (btn && input) {
            btn.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = 'پنهان';
                } else {
                    input.type = 'password';
                    btn.textContent = 'نمایش';
                }
            });
        }

        // ولیدیشن ساده Bootstrap
        var form = document.querySelector('.needs-validation');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        }
    });
</script>

<?php
$content = ob_get_clean();
$paths = [__DIR__ . '/../layout.php', dirname(__DIR__, 1) . '/layout.php'];
foreach ($paths as $p) {
    if (is_file($p)) {
        include $p;
        return;
    }
}
echo $content;
