<?php

/** @var array $run */
/** @var array $rows */
/** @var array $methods */
/** @var string $csrf */
/** @var string $owner_q */
require_once __DIR__ . '/../_helpers.php';
if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>
<style>
.sticky-th th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
}

/* فرم پرداخت در موبایل عمودی و تمام‌عرض شود */
@media (max-width: 768px) {
    .pay-inline {
        display: none !important;
    }

    /* فرم افقی دسکتاپ پنهان */
    .pay-mobile {
        display: block !important;
    }

    /* فرم موبایل نمایش */
    .pay-mobile .form-control,
    .pay-mobile .form-select,
    .pay-mobile .btn {
        width: 100% !important;
    }

    .pay-mobile .row>[class^="col-"] {
        margin-bottom: .5rem;
    }
}

@media (min-width: 769px) {
    .pay-mobile {
        display: none !important;
    }

    /* فقط دسکتاپ فرم افقی */
}

/* کمی فشرده‌تر روی موبایل */
@media (max-width: 576px) {

    .table td,
    .table th {
        padding: .5rem .5rem;
    }
}
</style>

<div class="container my-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">راند قبض #<?= h($run['id']); ?> — <?= h($run['title'] ?? ''); ?></h3>
            <div class="text-muted small">دوره: <?= h($run['period'] ?? ''); ?> | هدف:
                <?= number_format((float)($run['target_amount'] ?? 0)); ?></div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="index.php?page=pettycash">بازگشت به راندها</a>
            <!-- اگر صفحهٔ ایجاد راند دارید -->
            <!-- <a class="btn btn-success" href="index.php?page=pettycash_create">ایجاد راند جدید</a> -->
        </div>
    </div>

    <?php if (!empty($_SESSION['ok'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['ok']);
                                            unset($_SESSION['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-warning"><?= h($_SESSION['error']);
                                            unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- فیلتر جستجو نام مالک -->
    <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="page" value="pettycash_run">
        <input type="hidden" name="run_id" value="<?= (int)$run['id']; ?>">
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <input type="text" name="owner" value="<?= h($owner_q ?? ''); ?>" class="form-control"
                placeholder="جستجو نام مالک">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">جستجو</button>
        </div>
        <?php if (!empty($owner_q)): ?>
        <div class="col-auto">
            <a class="btn btn-outline-secondary" href="index.php?page=pettycash_run&run_id=<?= (int)$run['id']; ?>">حذف
                فیلتر</a>
        </div>
        <?php endif; ?>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light sticky-th">
                        <tr>
                            <th class="d-none d-md-table-cell">#فاکتور</th>
                            <th class="d-none d-sm-table-cell">واحد</th>
                            <th>مالک</th>
                            <th class="text-end d-none d-lg-table-cell">مبلغ</th>
                            <th class="text-end d-none d-xl-table-cell">پرداخت‌شده</th>
                            <th class="text-end">مانده</th>
                            <th>ثبت پرداخت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">موردی یافت نشد.</td>
                        </tr>
                        <?php else: foreach ($rows as $r):
                                $amount = (float)($r['amount'] ?? 0);
                                $paid   = (float)($r['paid_amount'] ?? 0);
                                $remain = max(0, $amount - $paid);
                                $iid    = (int)$r['id'];
                            ?>
                        <tr>
                            <td class="d-none d-md-table-cell"><?= h($iid); ?></td>
                            <td class="d-none d-sm-table-cell"><?= h($r['unit_id']); ?></td>
                            <td><?= h($r['owner_name'] ?? '—'); ?></td>
                            <td class="text-end d-none d-lg-table-cell"><?= number_format($amount); ?></td>
                            <td class="text-end d-none d-xl-table-cell"><?= number_format($paid); ?></td>
                            <td class="text-end fw-semibold"><?= number_format($remain); ?></td>
                            <td>
                                <?php if ($remain <= 0): ?>
                                <span class="text-muted">—</span>
                                <?php else: ?>
                                <!-- دسکتاپ: فرم افقی -->
                                <form method="post" action="index.php?page=pettycash_pay"
                                    class="d-none d-md-flex pay-inline flex-wrap gap-1">
                                    <input type="hidden" name="_csrf" value="<?= h($csrf); ?>">
                                    <input type="hidden" name="run_id" value="<?= (int)$run['id']; ?>">
                                    <input type="hidden" name="invoice_id" value="<?= $iid; ?>">
                                    <input type="number" name="amount" min="1" max="<?= (int)$remain; ?>"
                                        class="form-control form-control-sm" style="width:120px" placeholder="مبلغ">
                                    <select name="method" class="form-select form-select-sm" style="width:120px">
                                        <?php foreach ($methods as $m): ?>
                                        <option value="<?= h($m); ?>"><?= h($m); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="ref" class="form-control form-control-sm"
                                        style="width:140px" placeholder="ارجاع">
                                    <input type="text" name="note" class="form-control form-control-sm"
                                        style="width:160px" placeholder="یادداشت">
                                    <button class="btn btn-sm btn-primary">ثبت</button>
                                </form>

                                <!-- موبایل: دکمه باز/بستن فرم -->
                                <button class="btn btn-sm btn-primary d-md-none" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#pay-<?= $iid; ?>">
                                    ثبت پرداخت
                                </button>
                                <div id="pay-<?= $iid; ?>" class="collapse mt-2 pay-mobile">
                                    <form method="post" action="index.php?page=pettycash_pay" class="row g-2">
                                        <input type="hidden" name="_csrf" value="<?= h($csrf); ?>">
                                        <input type="hidden" name="run_id" value="<?= (int)$run['id']; ?>">
                                        <input type="hidden" name="invoice_id" value="<?= $iid; ?>">
                                        <div class="col-12">
                                            <input type="number" name="amount" min="1" max="<?= (int)$remain; ?>"
                                                class="form-control" placeholder="مبلغ">
                                        </div>
                                        <div class="col-12">
                                            <select name="method" class="form-select">
                                                <?php foreach ($methods as $m): ?>
                                                <option value="<?= h($m); ?>"><?= h($m); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <input type="text" name="ref" class="form-control"
                                                placeholder="شماره ارجاع">
                                        </div>
                                        <div class="col-12">
                                            <input type="text" name="note" class="form-control" placeholder="یادداشت">
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-primary w-100">ثبت</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>