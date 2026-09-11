<?php ob_start();
require_once __DIR__ . '/../_helpers.php'; // h(), csrf_field()

// ---- تنظیم مسیر لوگو (در صورت نیاز تغییر بده) ----
$logoUrl = 'assets/logo.png';
$logoFs  = __DIR__ . '/../assets/logo.png'; // مسیر فایل روی سرور
$hasLogo = is_file($logoFs);

// اگر لوگو ندارید، می‌توانید حروف مجتمع را اینجا بگذارید:
$brandInitials = 'MS'; // مثل: "مجتمع سپید"
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 shadow-sm portal-login-card">
            <!-- هدر گرادیانی + لوگو -->
            <div class="p-4 p-md-5 text-white position-relative header-gradient">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-badge">
                        <?php if ($hasLogo): ?>
                        <img src="<?= h($logoUrl) ?>" alt="لوگوی مجتمع" class="brand-logo">
                        <?php else: ?>
                        <div class="brand-fallback"><?= h($brandInitials) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-1">ورود به پورتال ساکنان</h4>
                        <small class="opacity-75">با موبایل و PIN وارد شوید</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-md-5">
                <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger mb-4"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php elseif (!empty($_SESSION['ok'])): ?>
                <div class="alert alert-success mb-4"><?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=portal_do_login" class="needs-validation" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">شماره موبایل</label>
                        <div class="input-group">
                            <span class="input-group-text">📱</span>
                            <input type="text" name="mobile" class="form-control" placeholder="مثلاً: 0912xxxxxxx"
                                required inputmode="tel" autocomplete="tel">
                        </div>
                        <div class="form-text">اعداد فارسی/انگلیسی و +98 هم قابل قبول است.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PIN</label>
                        <div class="input-group">
                            <span class="input-group-text">🔐</span>
                            <input type="password" name="pin" class="form-control" placeholder="PIN چهاررقمی" required
                                minlength="4" maxlength="10" autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePin">نمایش</button>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-portal w-100" type="submit">
                            ورود به پورتال
                        </button>
                        <a href="index.php?page=admin_login" class="btn btn-link">ورود ادمین</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.portal-login-card {
    border-radius: 1rem;
    overflow: hidden;
}

.header-gradient {
    background: linear-gradient(135deg, #5b8cff 0%, #7f56d9 50%, #22d3ee 100%);
}

.brand-badge {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(255, 255, 255, .15);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 16px rgba(0, 0, 0, .15);
    overflow: hidden;
}

.brand-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.brand-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: .5px;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, .25);
}

.btn-portal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: .75rem 1rem;
    border-radius: .75rem;
    background: linear-gradient(135deg, #5b8cff 0%, #7f56d9 50%, #22d3ee 100%);
    color: #fff;
    font-weight: 600;
    box-shadow: 0 10px 24px rgba(55, 0, 179, .20);
    transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
}

.btn-portal:hover {
    transform: translateY(-2px);
    color: #fff;
    box-shadow: 0 14px 28px rgba(55, 0, 179, .28);
    filter: brightness(1.03);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمایش/پنهان کردن PIN
    var btn = document.getElementById('togglePin');
    var input = document.querySelector('input[name="pin"]');
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

    // ولیدیشن سادهٔ Bootstrap
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
$paths = [__DIR__.'/../layout.php', dirname(__DIR__,1).'/layout.php'];
foreach ($paths as $p) { if (is_file($p)) { include $p; return; } }
echo $content;