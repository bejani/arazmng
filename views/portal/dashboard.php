<?php ob_start();
require_once __DIR__ . '/../_helpers.php';

/**
 * $summary  : [{unit_id, unit_name, floor, billed, paid, due}]
 * $invoices : [{id, unit_id, subject, invoice_amount, paid_amount, period, issue_date, due_date}]
 */
$summary  = $summary  ?? [];
$invoices = $invoices ?? [];

// Totals
$total_billed = 0.0;
$total_paid   = 0.0;
foreach ($summary as $s) {
    $total_billed += (float)($s['billed'] ?? 0);
    $total_paid   += (float)($s['paid']   ?? 0);
}
$total_due = max(0, $total_billed - $total_paid);

// Unit index for labels
$unitIndex = [];
foreach ($summary as $s) {
    $unitIndex[(int)($s['unit_id'] ?? 0)] = [
        'name'  => (string)($s['unit_name'] ?? ''),
        'floor' => isset($s['floor']) ? (int)$s['floor'] : null,
    ];
}

// status helper (paid/partial/unpaid)
$computeStatus = function (array $inv): array {
    $invAmt  = (float)($inv['invoice_amount'] ?? 0);
    $paid    = (float)($inv['paid_amount']    ?? 0);
    $balance = max(0, round($invAmt - $paid, 2));

    $status = 'unpaid';
    if ($balance <= 0.0)       $status = 'paid';
    elseif ($paid > 0.0)       $status = 'partial';

    return [$invAmt, $paid, $balance, $status];
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">سلام، <?= h($_SESSION['portal_resident_name'] ?? 'ساکن') ?> 👋</h4>
    <div class="d-none d-sm-block">
        <a href="index.php?page=portal_invoices" class="btn btn-outline-primary btn-sm">مشاهده قبض‌ها</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-emoji">🧾</div>
            <div>
                <div class="stat-caption">جمع صورتحساب‌ها (شارژ)</div>
                <div class="stat-value"><?= h(money($total_billed)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-emoji">💳</div>
            <div>
                <div class="stat-caption">جمع پرداخت‌ها</div>
                <div class="stat-value money-pos"><?= h(money($total_paid)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-emoji">⚠️</div>
            <div>
                <div class="stat-caption">بدهی کل</div>
                <div class="stat-value <?= $total_due > 0 ? 'money-neg' : '' ?>"><?= h(money($total_due)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card card-elevated mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0">واحدهای من</h5>
            <a href="index.php?page=portal_invoices" class="btn btn-sm btn-outline-primary d-sm-none">مشاهده قبض‌ها</a>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle responsive-cards table-modern">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>واحد</th>
                        <th>طبقه</th>
                        <th class="text-end">صورتحساب</th>
                        <th class="text-end">پرداخت</th>
                        <th class="text-end">بدهی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($summary)): ?>
                        <?php foreach ($summary as $i => $s): ?>
                            <tr>
                                <td data-label="#"><?= $i + 1 ?></td>
                                <td data-label="واحد"><?= h($s['unit_name'] ?? '') ?></td>
                                <td data-label="طبقه"><?= isset($s['floor']) ? h((string)$s['floor']) : '—' ?></td>
                                <td data-label="صورتحساب" class="text-end"><?= h(money((float)($s['billed'] ?? 0))) ?></td>
                                <td data-label="پرداخت" class="text-end money-pos"><?= h(money((float)($s['paid'] ?? 0))) ?>
                                </td>
                                <?php $due = (float)($s['due'] ?? 0); ?>
                                <td data-label="بدهی" class="text-end <?= $due > 0 ? 'money-neg' : '' ?>"><?= h(money($due)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">واحدی برای شما ثبت نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-elevated">
    <div class="card-body">
        <h5 class="card-title mb-3">فاکتورهای اخیر </h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle responsive-cards table-modern">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>واحد</th>
                        <th>عنوان</th>
                        <th class="text-end">مبلغ</th>
                        <th>دوره</th>
                        <th>صدور</th>
                        <th>سررسید</th>
                        <th>وضعیت</th>
                        <th class="text-end">پرداخت‌شده</th>
                        <th class="text-end">باقی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoices)): $rownum = 1; ?>
                        <?php foreach ($invoices as $inv):
                            $unitId  = (int)($inv['unit_id'] ?? 0);
                            $unit    = $unitIndex[$unitId] ?? null;
                            $unitLbl = $unit ? (($unit['name'] ?? '') . ($unit['floor'] !== null ? (' - طبقه ' . $unit['floor']) : '')) : '—';

                            [$invAmt, $paid, $balance, $status] = $computeStatus($inv);

                            $chipClass = 'chip';
                            $chipText = 'نامشخص';
                            $chipIcon = 'ℹ️';
                            if ($status === 'paid') {
                                $chipClass .= ' chip-paid';
                                $chipText = 'پرداخت‌شده';
                                $chipIcon = '✅';
                            } elseif ($status === 'partial') {
                                $chipClass .= ' chip-partial';
                                $chipText = 'قسمتی';
                                $chipIcon = '🟡';
                            } else {
                                $chipClass .= ' chip-unpaid';
                                $chipText = 'پرداخت‌نشده';
                                $chipIcon = '🔴';
                            }

                            $issue = jdate($inv['issue_date'] ?? null, 'Y/m/d');
                            $due   = jdate($inv['due_date']   ?? null, 'Y/m/d');
                        ?>
                            <tr>
                                <td data-label="#"><?= $rownum++ ?></td>
                                <td data-label="واحد"><?= h($unitLbl) ?></td>
                                <td data-label="عنوان" class="text-nowrap text-truncate" style="max-width:220px;">
                                    <?= h($inv['subject'] ?? '') ?></td>
                                <td data-label="مبلغ" class="text-end"><?= h(money($invAmt)) ?></td>
                                <td data-label="دوره"><?= h($inv['period'] ?? '') ?></td>
                                <td data-label="صدور"><?= h($issue) ?></td>
                                <td data-label="سررسید"><?= h($due) ?></td>
                                <td data-label="وضعیت"><span class="<?= $chipClass ?>"><?= $chipIcon ?> <?= $chipText ?></span>
                                </td>
                                <td data-label="پرداخت‌شده" class="text-end money-pos"><?= h(money($paid)) ?></td>
                                <td data-label="باقی" class="text-end <?= $balance > 0 ? 'money-neg' : '' ?>">
                                    <?= h(money($balance)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">فعلاً فاکتوری برای نمایش وجود ندارد.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted small">* برای مشاهدهٔ قبوض آب/برق/گاز: «مشاهده قبض‌ها» را بزنید.</div>
    </div>
</div>

<?php
$content = ob_get_clean();
$candidates = [__DIR__ . '/../layout.php', __DIR__ . '/../../layout.php'];
foreach ($candidates as $p) {
    if (is_file($p)) {
        include $p;
        return;
    }
}
echo $content;
