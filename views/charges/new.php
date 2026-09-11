<?php ob_start();
require_once __DIR__ . '/../_helpers.php';

// امروز → پیش‌فرض سال/ماه شمسی
$gy = (int)date('Y'); $gm = (int)date('n'); $gd = (int)date('j');
[$jy,$jm,$jd] = gregorian_to_jalali($gy,$gm,$gd);
$faMonths = [1=>'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

// پیش‌فرض issue/due (میلادی) + نمایش شمسی
$gIssue = date('Y-m-d');
$gDue   = date('Y-m-d', strtotime('+10 days'));
$defaultSubject = 'شارژ ماه '.$faMonths[$jm].' '.fa_num((string)$jy);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">صدور فاکتور شارژ ماهانه (شمسی)</h3>
    <a href="index.php?page=invoices" class="btn btn-secondary">مشاهده فاکتورها</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="index.php?page=charge_generate" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-md-3">
                <label class="form-label">سال (شمسی)</label>
                <input type="text" name="jyear" class="form-control" value="<?= h(fa_num((string)$jy)) ?>"
                    placeholder="مثلاً ۱۴۰۳" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">ماه (شمسی)</label>
                <select name="jmonth" class="form-select" required id="jmonth">
                    <?php foreach ($faMonths as $i=>$n): ?>
                    <option value="<?= $i ?>" <?= $i===$jm?'selected':'' ?>><?= h($n) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">عنوان فاکتور</label>
                <input type="text" name="subject" id="subject" class="form-control" value="<?= h($defaultSubject) ?>"
                    placeholder="مثلاً: شارژ ماه مهر ۱۴۰۳">
                <div class="form-text">در صورت خالی‌بودن، به‌صورت خودکار بر اساس سال/ماه پر می‌شود.</div>
            </div>

            <div class="col-md-3">
                <label class="form-label">تاریخ صدور (شمسی)</label>
                <input type="hidden" name="issue_date" value="<?= h($gIssue) ?>">
                <input type="text" class="form-control jdate" data-target="issue_date"
                    value="<?= h(jdate($gIssue,'Y/m/d')) ?>" placeholder="انتخاب از تقویم">
            </div>

            <div class="col-md-3">
                <label class="form-label">سررسید (شمسی)</label>
                <input type="hidden" name="due_date" value="<?= h($gDue) ?>">
                <input type="text" class="form-control jdate" data-target="due_date"
                    value="<?= h(jdate($gDue,'Y/m/d')) ?>" placeholder="انتخاب از تقویم">
            </div>

            <div class="col-md-3">
                <label class="form-label">مبلغ ثابت هر واحد (ریال)</label>
                <input type="number" step="1" min="0" name="amount" class="form-control" placeholder="مثلاً 500000"
                    required>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100">صدور فاکتور برای همه‌ی واحدهای فعال</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const faMonths = <?= json_encode($faMonths, JSON_UNESCAPED_UNICODE) ?>;
    const jmonthEl = document.getElementById('jmonth');
    const subjEl = document.getElementById('subject');
    const jyearEl = document.querySelector('input[name="jyear"]');

    function toEnDigits(s) {
        return String(s).replace(/[۰-۹٠-٩]/g, d => ({
            '۰': '0',
            '۱': '1',
            '۲': '2',
            '۳': '3',
            '۴': '4',
            '۵': '5',
            '۶': '6',
            '۷': '7',
            '۸': '8',
            '۹': '9',
            '٠': '0',
            '١': '1',
            '٢': '2',
            '٣': '3',
            '٤': '4',
            '٥': '5',
            '٦': '6',
            '٧': '7',
            '٨': '8',
            '٩': '9'
        } [d]));
    }

    function toFaDigits(s) {
        return String(s).replace(/[0-9]/g, d => ({
            '0': '۰',
            '1': '۱',
            '2': '۲',
            '3': '۳',
            '4': '۴',
            '5': '۵',
            '6': '۶',
            '7': '۷',
            '8': '۸',
            '9': '۹'
        } [d]));
    }

    function updateSubject() {
        const jm = parseInt(jmonthEl.value || '1', 10);
        const jy = toEnDigits(jyearEl.value || '');
        if (!subjEl.value.trim()) {
            subjEl.value = 'شارژ ماه ' + (faMonths[jm] || jm) + ' ' + toFaDigits(jy);
        }
    }
    jmonthEl.addEventListener('change', updateSubject);
    jyearEl.addEventListener('input', () => {
        /* فقط اگر کاربر عنوان را خالی گذاشته */
        if (!subjEl.value.trim()) updateSubject();
    });
});
</script>

<?php
$content = ob_get_clean();
// تلاش برای یافتن layout
$paths = [__DIR__.'/../layout.php', dirname(__DIR__,1).'/layout.php', __DIR__.'/../layout_admin.php'];
foreach ($paths as $p) { if (is_file($p)) { include $p; return; } }
echo $content;