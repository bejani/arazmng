<?php

/** @var array $payment */
/** @var array $openInvoices */
/** @var float|int $maxAllowed */
/** @var array $methods */
require_once __DIR__ . '/../_helpers.php'; // h(), csrf_field(), normalize_date_input, jdate, money
if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$methodsView = isset($methods) && is_array($methods) && $methods ? array_values($methods) : ['cash', 'bank', 'card', 'online'];
// اطمینان از اینکه روش فعلی داخل لیست است
if (!in_array((string)($payment['method'] ?? ''), $methodsView, true) && !empty($payment['method'])) {
    $methodsView[] = (string)$payment['method'];
}
$maxAttr = (is_numeric($maxAllowed ?? null) ? (string)(float)$maxAllowed : '');
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
?>
<div class="container my-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-start align-items-md-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-0">ویرایش پرداخت #<?= h($payment['id']); ?></h3>
            <div class="text-muted small">
                فاکتور فعلی: #<?= h($payment['invoice_id']); ?> — <?= h($payment['subject'] ?? ''); ?>
            </div>
        </div>
        <div class="ms-auto d-flex gap-2 w-100 w-md-auto">
            <a class="btn btn-outline-secondary flex-fill flex-md-none"
                href="<?= h($redirect ?: 'index.php?page=payments'); ?>">بازگشت</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['ok'])): ?>
        <div class="alert alert-success"><?= h($_SESSION['ok']);
                                            unset($_SESSION['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= h($_SESSION['error']);
                                        unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>فرم ویرایش</span>
                    <span class="badge bg-light text-dark">حداکثر مبلغ مجاز:
                        <?= number_format((float)$maxAllowed); ?></span>
                </div>
                <div class="card-body">
                    <form method="post" action="index.php?page=payment_update" class="row g-3">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= (int)$payment['id']; ?>">
                        <?php if ($redirect): ?>
                            <input type="hidden" name="redirect" value="<?= h($redirect); ?>">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">فاکتور</label>
                            <select name="invoice_id" class="form-select" required>
                                <?php foreach ($openInvoices as $inv): ?>
                                    <?php
                                    $optId   = (int)$inv['id'];
                                    $sel     = $optId === (int)$payment['invoice_id'] ? 'selected' : '';
                                    $subject = (string)($inv['subject'] ?? '');
                                    $balance = isset($inv['balance']) ? (float)$inv['balance'] : 0;
                                    ?>
                                    <option value="<?= $optId; ?>" <?= $sel; ?>>
                                        #<?= $optId; ?> — <?= h($subject); ?>
                                        <?= $balance > 0 ? '— مانده: ' . number_format($balance) : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">می‌توانید پرداخت را به فاکتور دیگری از همان واحد منتقل کنید.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">مبلغ</label>
                            <input type="number" name="amount" class="form-control" min="1" step="1"
                                value="<?= h((string)$payment['amount']); ?>"
                                <?= $maxAttr !== '' ? 'max="' . h($maxAttr) . '"' : ''; ?> required>
                            <div class="form-text">حداکثر مجاز: <?= number_format((float)$maxAllowed); ?></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">تاریخ پرداخت</label>
                            <input type="date" name="pay_date" class="form-control"
                                value="<?= h((string)$payment['pay_date']); ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">روش</label>
                            <select name="method" class="form-select">
                                <?php foreach ($methodsView as $m): ?>
                                    <option value="<?= h($m); ?>"
                                        <?= ((string)$payment['method'] === (string)$m) ? 'selected' : ''; ?>><?= h($m); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">شماره ارجاع</label>
                            <input type="text" name="ref" class="form-control"
                                value="<?= h((string)($payment['ref'] ?? '')); ?>" placeholder="Ref">
                        </div>

                        <div class="col-12">
                            <label class="form-label">یادداشت</label>
                            <input type="text" name="note" class="form-control"
                                value="<?= h((string)($payment['note'] ?? '')); ?>" placeholder="توضیحات">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-success">ذخیره تغییرات</button>
                            <a class="btn btn-outline-secondary"
                                href="<?= h($redirect ?: 'index.php?page=payments'); ?>">انصراف</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">خلاصه</div>
                <div class="card-body">
                    <div class="mb-2 small text-muted">فاکتور: #<?= h($payment['invoice_id']); ?> —
                        <?= h($payment['subject'] ?? ''); ?></div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>مبلغ فاکتور</span>
                            <span
                                class="fw-semibold"><?= number_format((float)($payment['invoice_amount'] ?? 0)); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>پرداخت‌های دیگر</span>
                            <span
                                class="fw-semibold"><?= number_format((float)($payment['paid_except_this'] ?? 0)); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>حداکثر مبلغ مجاز</span>
                            <span class="fw-semibold"><?= number_format((float)$maxAllowed); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>مبلغ فعلی پرداخت</span>
                            <span class="fw-semibold"><?= number_format((float)$payment['amount']); ?></span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer">
                    <form method="post" action="index.php?page=payment_delete"
                        onsubmit="return confirm('حذف پرداخت؟ این عملیات غیرقابل بازگشت است.');" class="d-grid">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= (int)$payment['id']; ?>">
                        <?php if ($redirect): ?>
                            <input type="hidden" name="redirect" value="<?= h($redirect); ?>">
                        <?php endif; ?>
                        <button class="btn btn-outline-danger">حذف پرداخت</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>