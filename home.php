<?php ob_start();

/*
 * Dashboard counters + دو کارد «قبض‌ها» و «تنخواه»
 * — امن در برابر نبودن جدول‌ها/ستون‌ها (همه‌چیز fallback به صفر)
 */

$annCount = $annPinned = $ticketOpen = $ticketPending = 0;

// فقط اگر $pdo موجود باشد:
if (isset($pdo) && $pdo instanceof PDO) {
    $hasTable = function (string $t) use ($pdo): bool {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t LIMIT 1");
        $st->execute([':t' => $t]);
        return (bool)$st->fetchColumn();
    };
    $hasColumn = function (string $t, string $c) use ($pdo): bool {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
        $st->execute([':t' => $t, ':c' => $c]);
        return (bool)$st->fetchColumn();
    };
    $firstCol = function (array $cands, string $table) use ($hasColumn): ?string {
        foreach ($cands as $c) if ($hasColumn($table, $c)) return $c;
        return null;
    };

    // اعلان‌ها + تیکت‌ها
    try {
        if ($hasTable('announcements')) {
            $annCount  = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
            $annPinned = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE is_pinned=1")->fetchColumn();
        }
        if ($hasTable('tickets')) {
            $ticketOpen    = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
            $ticketPending = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='pending'")->fetchColumn();
        }
    } catch (Throwable $e) { /* ignore */
    }

    // -------- خلاصه برای kind خاص از invoices (petty/utility)
    $summaryForKind = function (string $kind) use ($pdo, $hasTable, $hasColumn, $firstCol): array {
        $amtCol = $firstCol(['amount', 'total_amount', 'total', 'price', 'final_amount'], 'invoices') ?? 'amount';

        // پرداخت‌ها: payment یا payments + ستون‌های amount و invoice_id
        $payTbl = null;
        if ($hasTable('payment'))  $payTbl = 'payment';
        if ($hasTable('payments')) $payTbl = $payTbl ?: 'payments';
        $payInv = $payTbl ? ($firstCol(['invoice_id', 'inv_id'], $payTbl) ?? null) : null;
        $payAmt = $payTbl ? ($firstCol(['amount', 'paid_amount', 'value'], $payTbl) ?? null) : null;

        $invoiced = 0.0;
        $paid = 0.0;
        $openCount = 0;

        if ($hasTable('invoices') && $hasColumn('invoices', 'kind') && $firstCol([$amtCol], 'invoices')) {
            // جمع مبلغ فاکتورها
            $st = $pdo->prepare("SELECT COALESCE(SUM($amtCol),0) FROM invoices WHERE kind=:k");
            $st->execute([':k' => $kind]);
            $invoiced = (float)$st->fetchColumn();

            // جمع پرداخت‌ها (با JOIN به invoices)
            if ($payTbl && $payInv && $payAmt) {
                $sqlP = "SELECT COALESCE(SUM(p.$payAmt),0)
                         FROM $payTbl p
                         JOIN invoices i ON i.id = p.$payInv
                         WHERE i.kind = :k";
                $st = $pdo->prepare($sqlP);
                $st->execute([':k' => $kind]);
                $paid = (float)$st->fetchColumn();

                // تعداد فاکتورهای باز
                $sqlOpen = "SELECT COUNT(*)
                            FROM invoices i
                            LEFT JOIN (
                                 SELECT $payInv AS invoice_id, COALESCE(SUM($payAmt),0) AS paid
                                 FROM $payTbl GROUP BY $payInv
                            ) t ON t.invoice_id = i.id
                            WHERE i.kind=:k AND COALESCE(t.paid,0) < i.$amtCol";
                $st = $pdo->prepare($sqlOpen);
                $st->execute([':k' => $kind]);
                $openCount = (int)$st->fetchColumn();
            } else {
                // جدول پرداخت نداریم → همهٔ فاکتورهای مثبت را باز فرض کن
                $st = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE kind=:k AND $amtCol > 0");
                $st->execute([':k' => $kind]);
                $openCount = (int)$st->fetchColumn();
            }
        }

        // تعداد راندها
        $runsCount = 0;
        if ($kind === 'petty') {
            if ($hasTable('pettycash_runs')) {
                $runsCount = (int)$pdo->query("SELECT COUNT(*) FROM pettycash_runs")->fetchColumn();
            }
        } else if ($kind === 'utility') {
            // هر کدوم که هست:
            $runsTbl = null;
            if ($hasTable('utility_runs'))    $runsTbl = 'utility_runs';
            elseif ($hasTable('utilities_runs'))  $runsTbl = 'utilities_runs';
            elseif ($hasTable('allocation_runs')) $runsTbl = 'allocation_runs';
            if ($runsTbl) $runsCount = (int)$pdo->query("SELECT COUNT(*) FROM $runsTbl")->fetchColumn();
        }

        return [
            'invoiced'  => $invoiced,
            'paid'      => $paid,
            'remain'    => max(0.0, $invoiced - $paid),
            'openCount' => $openCount,
            'runsCount' => $runsCount,
        ];
    };

    $sumPetty = $summaryForKind('petty');
    $sumUtil  = $summaryForKind('utility');
}

?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">داشبورد</h3>
</div>
<style>
    .card-admin .muted {
        color: #6c757d;
    }

    .dash-stat {
        font-weight: 700;
        font-size: 1.05rem;
    }

    .chip {
        display: inline-block;
        padding: .125rem .5rem;
        border-radius: 1rem;
        background: #f1f3f5;
        font-size: .85rem;
    }

    /* حداقل ارتفاع برای هم‌قد شدن کارت‌ها (در صورت نیاز عدد را کم/زیاد کن) */
    .card-admin.equal .card-body {
        min-height: 340px;
    }

    @media (max-width: 576px) {
        .card-admin.equal .card-body {
            min-height: auto;
        }
    }
</style>

<style>
    .card-admin .muted {
        color: #6c757d;
    }

    .dash-stat {
        font-weight: 700;
        font-size: 1.05rem;
    }

    .chip {
        display: inline-block;
        padding: .125rem .5rem;
        border-radius: 1rem;
        background: #f1f3f5;
        font-size: .85rem;
    }
</style>
<?php
$unitsTotal = 0;
$unitsActive = null;
$residentsTotal = 0;
$residentsActive = null;

if (isset($pdo) && $pdo instanceof PDO) {
    // ... همان $hasTable و $hasColumn و $firstCol که قبلاً دارید ...

    try {
        if ($hasTable('units')) {
            $unitsTotal = (int)$pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
            if ($hasColumn('units', 'is_active')) {
                $unitsActive = (int)$pdo->query("SELECT COUNT(*) FROM units WHERE is_active=1")->fetchColumn();
            }
        }
        if ($hasTable('residents')) {
            $residentsTotal = (int)$pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
            if ($hasColumn('residents', 'is_active')) {
                $residentsActive = (int)$pdo->query("SELECT COUNT(*) FROM residents WHERE is_active=1")->fetchColumn();
            }
        }
    } catch (Throwable $e) { /* ignore */
    }
}
?>
<div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4">



    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-buildings card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">واحدها</h5>
                </div>
                <p class="small muted mb-2">مدیریت لیست واحدها، ویرایش اطلاعات و وضعیت.</p>
                <div class="small mb-2">
                    <span class="chip">کل: <?= number_format((int)$unitsTotal) ?></span>
                    <?php if ($unitsActive !== null): ?>
                        <span class="chip ms-1">فعال: <?= number_format((int)$unitsActive) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-auto">
                    <a href="index.php?page=units" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>


    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-people card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">ساکنان</h5>
                </div>
                <p class="small muted mb-2">افزودن/ویرایش ساکنان و تغییر وضعیت.</p>
                <div class="small mb-2">
                    <span class="chip">کل: <?= number_format((int)$residentsTotal) ?></span>
                    <?php if ($residentsActive !== null): ?>
                        <span class="chip ms-1">فعال: <?= number_format((int)$residentsActive) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-auto">
                    <a href="index.php?page=residents" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>


    <!-- 📊 قبض‌ها -->
    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-lightning-charge card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">قبض‌ها</h5>
                </div>
                <p class="small muted mb-2">وضعیت راندها و فاکتورهای آب/برق/گاز و...</p>
                <div class="small mb-2">
                    <span class="chip">راندها:
                        <?= isset($sumUtil['runsCount']) ? (int)$sumUtil['runsCount'] : 0 ?></span>
                    <span class="chip ms-1">باز:
                        <?= isset($sumUtil['openCount']) ? (int)$sumUtil['openCount'] : 0 ?></span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between"><span class="muted">جمع فاکتور:</span><span
                            class="dash-stat"><?= number_format((float)($sumUtil['invoiced'] ?? 0)) ?></span></div>
                    <div class="d-flex justify-content-between"><span class="muted">جمع پرداخت:</span><span
                            class="dash-stat"><?= number_format((float)($sumUtil['paid'] ?? 0)) ?></span></div>
                    <div class="d-flex justify-content-between"><span class="muted">مانده:</span><span
                            class="dash-stat"><?= number_format((float)($sumUtil['remain'] ?? 0)) ?></span></div>
                </div>
                <div class="mt-auto pt-3">
                    <!-- لینک به روت فعلی شما: utilities -->
                    <a href="index.php?page=utilities" class="btn btn-primary w-100">مشاهده راندهای قبض</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 💸 تنخواه -->
    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-cash-coin card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">تنخواه</h5>
                </div>
                <p class="small muted mb-2">توزیع، فاکتورهای صادره، و پرداخت‌های تنخواه.</p>
                <div class="small mb-2">
                    <span class="chip">راندها:
                        <?= isset($sumPetty['runsCount']) ? (int)$sumPetty['runsCount'] : 0 ?></span>
                    <span class="chip ms-1">باز:
                        <?= isset($sumPetty['openCount']) ? (int)$sumPetty['openCount'] : 0 ?></span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between"><span class="muted">جمع فاکتور:</span><span
                            class="dash-stat"><?= number_format((float)($sumPetty['invoiced'] ?? 0)) ?></span></div>
                    <div class="d-flex justify-content-between"><span class="muted">جمع پرداخت:</span><span
                            class="dash-stat"><?= number_format((float)($sumPetty['paid'] ?? 0)) ?></span></div>
                    <div class="d-flex justify-content-between"><span class="muted">مانده:</span><span
                            class="dash-stat"><?= number_format((float)($sumPetty['remain'] ?? 0)) ?></span></div>
                </div>
                <div class="mt-auto pt-3">
                    <!-- لینک به روت فعلی شما: pettycash -->
                    <a href="index.php?page=pettycash" class="btn btn-primary w-100">مشاهده راندهای تنخواه</a>
                </div>
            </div>
        </div>
    </div>



    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-file-earmark-arrow-up card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">ورود از اکسل</h5>
                </div>
                <p class="small muted mb-3">ایمپورت واحدها و مالک‌ها از فایل Excel/CSV.</p>
                <div class="mt-auto">
                    <a href="index.php?page=import_units_owners" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-receipt card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">فاکتورها</h5>
                </div>
                <p class="small muted mb-3">مدیریت صدور و وضعیت فاکتورها.</p>
                <div class="mt-auto">
                    <a href="index.php?page=invoices" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-credit-card-2-front card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">پرداخت‌ها</h5>
                </div>
                <p class="small muted mb-3">ثبت و بررسی پرداخت‌های مربوط به فاکتورها.</p>
                <div class="mt-auto">
                    <a href="index.php?page=payments" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 📢 اعلان‌ها -->
    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-megaphone card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">اعلان‌ها</h5>
                </div>
                <p class="small muted mb-2">مدیریت اعلان‌های پورتال.</p>
                <div class="small mb-3">
                    <span class="badge text-bg-secondary">کل: <?= (int)$annCount ?></span>
                    <span class="badge text-bg-warning text-dark ms-1">پین‌شده: <?= (int)$annPinned ?></span>
                </div>
                <div class="mt-auto">
                    <a href="index.php?page=admin_announcements" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 🎫 تیکت‌ها -->
    <div class="col">
        <div class="card card-admin h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-life-preserver card-admin__icon me-2" aria-hidden="true"></i>
                    <h5 class="card-title mb-0">تیکت‌ها</h5>
                </div>
                <p class="small muted mb-2">مشاهده و پاسخ به درخواست‌های پشتیبانی.</p>
                <div class="small mb-3">
                    <span class="badge text-bg-danger">باز: <?= (int)$ticketOpen ?></span>
                    <span class="badge text-bg-warning text-dark ms-1">در حال رسیدگی: <?= (int)$ticketPending ?></span>
                </div>
                <div class="mt-auto">
                    <a href="index.php?page=admin_tickets" class="btn btn-primary w-100">باز کردن</a>
                </div>
            </div>
        </div>
    </div>

</div><!-- /row -->

<?php
// خروجی و اتصال به layout.php در مسیرهای رایج
$content = ob_get_clean();
$candidates = [
    __DIR__ . '/views/layout.php',
    __DIR__ . '/layout.php',
];
$layout = null;
foreach ($candidates as $p) {
    if (file_exists($p)) {
        $layout = $p;
        break;
    }
}
if ($layout) include $layout;
else echo $content;
