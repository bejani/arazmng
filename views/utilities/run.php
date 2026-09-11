<?php

/** @var array $run */
/** @var array $rows */
/** @var array $methods */
/** @var string $csrf */
/** @var string $owner_q */
require_once __DIR__ . '/../_helpers.php';
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>
<div class="container my-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-start align-items-md-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-0">راند قبض #<?= h($run['id']); ?> — <?= h($run['title'] ?? ''); ?></h3>
            <div class="text-muted">دوره: <?= h($run['period'] ?? ''); ?> | هدف:
                <?= number_format((float)($run['target_amount'] ?? 0)); ?></div>
        </div>
        <div class="ms-auto">
            <a class="btn btn-outline-secondary w-100 w-md-auto" href="index.php?page=utilities">بازگشت به راندها</a>
            <!-- اگر صفحهٔ ایجاد راند قبض دارید -->
            <!-- <a class="btn btn_SUCCESS ms-2 w-100 w-md-auto" href="index.php?page=utility_create">ایجاد راند جدید</a> -->
        </div>
    </div>

    <?php if (!empty($_SESSION['ok'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- فیلتر جستجو بر اساس نام مالک -->
    <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="page" value="utility_run">
        <input type="hidden" name="run_id" value="<?= (int)$run['id']; ?>">
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <input type="text" name="owner" value="<?= h($owner_q ?? ''); ?>" class="form-control"
                placeholder="جستجو نام مالک">
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-primary w-100 w-sm-auto">جستجو</button>
        </div>
        <?php if (!empty($owner_q)): ?>
        <div class="col-6 col-sm-auto">
            <a class="btn btn-outline-secondary w-100 w-sm-auto"
                href="index.php?page=utility_run&run_id=<?= (int)$run['id']; ?>">حذف فیلتر</a>
        </div>
        <?php endif; ?>
    </form>

    <!-- دسکتاپ و تبلت: جدول (md و بزرگ‌تر) -->
    <div class="card d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#فاکتور</th>
                            <th>واحد</th>
                            <th>مالک</th>
                            <th class="text-end">مبلغ</th>
                            <th class="text-end">پرداخت‌شده</th>
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
                        ?>
                        <tr>
                            <td><?= h($r['id']); ?></td>
                            <td><?= h($r['unit_id']); ?></td>
                            <td><?= h($r['owner_name'] ?? '—'); ?></td>
                            <td class="text-end"><?= number_format($amount); ?></td>
                            <td class="text-end"><?= number_format($paid); ?></td>
                            <td class="text-end fw-semibold"><?= number_format($remain); ?></td>
                            <td>
                                <?php if ($remain <= 0): ?>
                                <span class="text-muted">—</span>
                                <?php else: ?>
                                <form method="post" action="index.php?page=utility_pay" class="d-flex flex-wrap gap-1">
                                    <input type="hidden" name="_csrf" value="<?= h($csrf); ?>">
                                    <input type="hidden" name="run_id" value="<?= (int)$run['id']; ?>">
                                    <input type="hidden" name="invoice_id" value="<?= (int)$r['id']; ?>">
                                    <input type="number" name="amount" min="1" max="<?= (int)$remain; ?>"
                                        class="form-control form-control-sm" placeholder="مبلغ">
                                    <select name="method" class="form-select form-select-sm">
                                        <?php foreach ($methods as $m): ?>
                                        <option value="<?= h($m); ?>"><?= h($m); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="ref" class="form-control form-control-sm"
                                        placeholder="شماره ارجاع">
                                    <input type="text" name="note" class="form-control form-control-sm"
                                        placeholder="یادداشت">
                                    <button class="btn btn-sm btn-primary">ثبت</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- موبایل: کارت‌محور (sm و کوچک‌تر) -->
    <div class="d-md-none">
        <?php if (empty($rows)): ?>
        <div class="card">
            <div class="card-body text-center py-4">موردی یافت نشد.</div>
        </div>
        <?php else: ?>
        <div class="row row-cols-1 g-3">
            <?php foreach ($rows as $r):
                    $amount = (float)($r['amount'] ?? 0);
                    $paid   = (float)($r['paid_amount'] ?? 0);
                    $remain = max(0, $amount - $paid);
                    $ratio  = $amount > 0 ? max(0, min(100, (int)round(($paid / $amount) * 100))) : 0;
                    $cid    = 'payForm' . (int)$r['id'];
                ?>
            <div class="col">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-bold">#<?= h($r['id']); ?> · واحد <?= h($r['unit_id']); ?></div>
                            <span class="badge <?= $remain > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                مانده: <?= number_format($remain); ?>
                            </span>
                        </div>
                        <div class="text-muted small mb-1">
                            مالک: <?= h($r['owner_name'] ?? '—'); ?>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>پرداخت</span>
                                <span><?= number_format($paid); ?> / <?= number_format($amount); ?></span>
                            </div>
                            <div class="progress" role="progressbar" aria-label="نسبت پرداخت"
                                aria-valuenow="<?= $ratio; ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= $ratio; ?>%"><?= $ratio; ?>%</div>
                            </div>
                        </div>

                        <?php if ($remain > 0): ?>
                        <button class="btn btn-primary btn-sm w-100 mb-2" type="button" data-bs-toggle="collapse"
                            data-bs-target="#<?= $cid; ?>" aria-expanded="false" aria-controls="<?= $cid; ?>">
                            ثبت پرداخت
                        </button>
                        <div class="collapse" id="<?= $cid; ?>">
                            <form method="post" action="index.php?page=utility_pay" class="row g-2">
                                <input type="hidden" name="_csrf" value="<?= h($csrf); ?>">
                                <input type="hidden" name="run_id" value="<?= (int)$run['id']; ?>">
                                <input type="hidden" name="invoice_id" value="<?= (int)$r['id']; ?>">

                                <div class="col-6">
                                    <input type="number" name="amount" min="1" max="<?= (int)$remain; ?>"
                                        class="form-control form-control-sm" placeholder="مبلغ">
                                </div>
                                <div class="col-6">
                                    <select name="method" class="form-select form-select-sm">
                                        <?php foreach ($methods as $m): ?>
                                        <option value="<?= h($m); ?>"><?= h($m); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="text" name="ref" class="form-control form-control-sm"
                                        placeholder="شماره ارجاع">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="note" class="form-control form-control-sm"
                                        placeholder="یادداشت">
                                </div>
                                <div class="col-12 d-grid">
                                    <button class="btn btn-primary btn-sm">ثبت</button>
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">تسویه شده</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>