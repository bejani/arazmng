<?php
// views/payments/new.php
require_once __DIR__ . '/../_helpers.php';

/**
 * Vars expected:
 * $units (array of id,name,floor)
 * $selUnit (int|null)
 * $openInvoices (array of invoices with: id, unit_id, subject, balance[, invoice_amount, paid_amount])
 * $selectedInvoice (array|null)
 * $selectedBalance (float|null)
 */
$units           = $units           ?? [];
$selUnit         = $selUnit         ?? null;
$openInvoices    = $openInvoices    ?? [];
$selectedInvoice = $selectedInvoice ?? null;
$selectedBalance = isset($selectedBalance) ? (float)$selectedBalance : null;

/* اگر فاکتور انتخاب‌شده در لیست نبود، به اول لیست اضافه شود تا در سلکت دیده شود */
if ($selectedInvoice && $selectedBalance !== null) {
    $exists = false;
    foreach ($openInvoices as $oi) {
        if ((int)$oi['id'] === (int)$selectedInvoice['id']) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $tmp = $selectedInvoice;
        $tmp['balance'] = $selectedBalance;
        array_unshift($openInvoices, $tmp);
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">ثبت پرداخت</h3>
</div>

<div class="card card-elevated">
    <div class="card-body">
        <form method="POST" action="index.php?page=payment_store" class="row g-3">
            <?= csrf_field() ?>

            <!-- واحد -->
            <div class="col-md-4">
                <label class="form-label">واحد</label>
                <select name="unit_id" id="unit_id" class="form-select">
                    <option value="">— انتخاب واحد —</option>
                    <?php foreach ($units as $u):
                        $lbl = trim(($u['name'] ?? '') . ' - طبقه ' . (int)($u['floor'] ?? 0));
                        $sel = ($selUnit !== null && (int)$selUnit === (int)$u['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- فاکتور -->
            <div class="col-md-5">
                <label class="form-label">فاکتور (قبض)</label>
                <select name="invoice_id" id="invoice_id" class="form-select" required>
                    <option value="">— انتخاب فاکتور —</option>
                    <?php foreach ($openInvoices as $inv):
                        $iid = (int)$inv['id'];
                        $lbl = '#' . $iid . ' — ' . trim((string)($inv['subject'] ?? ''));
                        $bal = (float)($inv['balance'] ?? 0);
                        $un  = (int)($inv['unit_id'] ?? 0);
                        $sel = ($selectedInvoice && (int)$selectedInvoice['id'] === $iid) ? 'selected' : '';
                    ?>
                        <option value="<?= $iid ?>"
                            data-balance="<?= htmlspecialchars((string)$bal, ENT_QUOTES, 'UTF-8') ?>" data-unit="<?= $un ?>"
                            <?= $sel ?>>
                            <?= h($lbl) ?> — مانده: <?= h(money($bal)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    ماندهٔ این فاکتور: <span id="balance_badge" class="fw-bold">
                        <?= $selectedBalance !== null ? h(money($selectedBalance)) : '—' ?>
                    </span>
                </div>
            </div>

            <!-- مبلغ -->
            <div class="col-md-3">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>مبلغ پرداختی</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="fill_balance">پر کردن با
                        مانده</button>
                </label>
                <?php
                // اگر کنترلر مانده را فرستاده باشد، فیلد از ابتدا پُر می‌شود
                $initialAmount = ($selectedBalance !== null && $selectedBalance > 0)
                    ? (string)(int)round($selectedBalance)
                    : '';
                ?>
                <input type="number" step="0.01" min="0" class="form-control" name="amount" id="amount"
                    value="<?= h($initialAmount) ?>" required>
            </div>

            <!-- تاریخ پرداخت -->
            <div class="col-md-3">
                <label class="form-label">تاریخ پرداخت (شمسی)</label>
                <input type="hidden" name="pay_date" value="<?= h(date('Y-m-d')) ?>">
                <input type="text" class="form-control jdate" data-target="pay_date" placeholder="۱۴۰۳/۰۶/۰۱">
            </div>

            <!-- روش پرداخت -->
            <div class="col-md-3">
                <label class="form-label">روش</label>
                <select name="method" class="form-select">
                    <option value="cash">نقدی</option>
                    <option value="bank">واریز بانکی</option>
                    <option value="card">کارت‌خوان</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">شناسه/یادداشت (اختیاری)</label>
                <input type="text" class="form-control" name="ref" placeholder="مثلاً شماره پیگیری">
            </div>

            <div class="col-12">
                <button class="btn btn-success">ثبت پرداخت</button>
                <a href="index.php?page=invoices" class="btn btn-outline-secondary">بازگشت</a>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        var selInvoice = document.getElementById('invoice_id');
        var selUnit = document.getElementById('unit_id');
        var amountInp = document.getElementById('amount');
        var badge = document.getElementById('balance_badge');
        var fillBtn = document.getElementById('fill_balance');

        function formatMoney(n) {
            try {
                return new Intl.NumberFormat('fa-IR').format(Math.round(n)) + ' ریال';
            } catch (e) {
                return String(n);
            }
        }

        function readSelectedBalance() {
            var opt = selInvoice.options[selInvoice.selectedIndex];
            if (!opt) return 0;
            var raw = (opt.getAttribute('data-balance') || '0').replace(/[^\d.]/g, '');
            var bal = parseFloat(raw);
            return isFinite(bal) ? bal : 0;
        }

        function readSelectedUnit() {
            var opt = selInvoice.options[selInvoice.selectedIndex];
            if (!opt) return '';
            return opt.getAttribute('data-unit') || '';
        }

        function updateByInvoice() {
            var bal = readSelectedBalance();
            var uid = readSelectedUnit();

            // ست‌کردن واحد در صورت موجود بودن
            if (uid && selUnit) selUnit.value = String(uid);

            // نشان مانده
            if (badge) badge.textContent = bal > 0 ? formatMoney(bal) : '—';

            // محدودیت max برای مبلغ
            if (bal > 0) amountInp.setAttribute('max', String(bal));
            else amountInp.removeAttribute('max');

            // اگر کاربر هنوز چیزی وارد نکرده، خودکار با مانده پُر کن
            if (!amountInp.value || parseFloat(amountInp.value) === 0) {
                if (bal > 0) {
                    amountInp.value = (bal % 1 === 0) ? String(Math.round(bal)) : String(bal.toFixed(2));
                }
            }
        }

        if (selInvoice) {
            selInvoice.addEventListener('change', updateByInvoice);
            // بار اول: اگر گزینه‌ای از قبل انتخاب‌شده است (از لینک "ثبت پرداخت")، فوراً بروزرسانی کن
            updateByInvoice();
        }

        if (fillBtn) {
            fillBtn.addEventListener('click', function() {
                var bal = readSelectedBalance();
                if (bal > 0) {
                    amountInp.value = (bal % 1 === 0) ? String(Math.round(bal)) : String(bal.toFixed(2));
                }
            });
        }

        // اینیت تقویم شمسی اگر موجود بود
        if (typeof window.initJdate === 'function') {
            try {
                window.initJdate();
            } catch (e) {}
        }
    })();
</script>