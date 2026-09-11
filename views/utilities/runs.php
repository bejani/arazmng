<?php
/** @var array $runs */
/** @var array $methods */
/** @var string $csrf */
require_once __DIR__ . '/../_helpers.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="container my-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">راندهای قبض</h3>
        <!-- اگر صفحهٔ ایجاد راند قبض دارید، لینک بگذارید -->
        <!-- <a class="btn btn-success" href="index.php?page=utility_create">ایجاد راند جدید</a> -->
    </div>

    <?php if (!empty($_SESSION['ok'])): ?>
    <div class="alert alert-success"><?php echo h($_SESSION['ok']); unset($_SESSION['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- دسکتاپ و تبلت: جدول (md و بزرگ‌تر) -->
    <div class="card d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#راند</th>
                            <th>عنوان</th>
                            <th>دوره</th>
                            <th class="text-end">هدف</th>
                            <th class="text-end">تعداد فاکتور</th>
                            <th class="text-end">جمع فاکتورها</th>
                            <th class="text-end">جمع پرداخت</th>
                            <th class="text-end">مانده</th>
                            <th>نمایش</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($runs)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">راندی یافت نشد.</td>
                        </tr>
                        <?php else: foreach ($runs as $r):
                            $invoiced = (float)($r['invoiced_total'] ?? 0);
                            $paid     = (float)($r['paid_total'] ?? 0);
                            $remain   = max(0, $invoiced - $paid);
                        ?>
                        <tr>
                            <td><?php echo h($r['id']); ?></td>
                            <td><?php echo h($r['title'] ?? '—'); ?></td>
                            <td><?php echo h($r['period'] ?? ''); ?></td>
                            <td class="text-end"><?php echo number_format((float)($r['target_amount'] ?? 0)); ?></td>
                            <td class="text-end"><?php echo number_format((int)($r['invoices_count'] ?? 0)); ?></td>
                            <td class="text-end"><?php echo number_format($invoiced); ?></td>
                            <td class="text-end"><?php echo number_format($paid); ?></td>
                            <td class="text-end fw-semibold"><?php echo number_format($remain); ?></td>
                            <td>
                                <a class="btn btn-sm btn-success"
                                    href="index.php?page=utility_run&run_id=<?php echo (int)$r['id']; ?>">جزئیات</a>
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
        <?php if (empty($runs)): ?>
        <div class="card">
            <div class="card-body text-center py-4">راندی یافت نشد.</div>
        </div>
        <?php else: ?>
        <div class="row row-cols-1 g-3">
            <?php foreach ($runs as $r):
                    $invoiced = (float)($r['invoiced_total'] ?? 0);
                    $paid     = (float)($r['paid_total'] ?? 0);
                    $remain   = max(0, $invoiced - $paid);
                    $ratio    = $invoiced > 0 ? max(0, min(100, (int)round(($paid / $invoiced) * 100))) : 0;
                ?>
            <div class="col">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-bold">#<?php echo h($r['id']); ?> · <?php echo h($r['title'] ?? '—'); ?>
                            </div>
                            <span class="badge <?php echo $remain > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                مانده: <?php echo number_format($remain); ?>
                            </span>
                        </div>
                        <div class="text-muted small mb-2">
                            دوره: <?php echo h($r['period'] ?? ''); ?>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>پرداخت</span>
                                <span><?php echo number_format($paid); ?> /
                                    <?php echo number_format($invoiced); ?></span>
                            </div>
                            <div class="progress" role="progressbar" aria-label="نسبت پرداخت"
                                aria-valuenow="<?php echo $ratio; ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?php echo $ratio; ?>%"><?php echo $ratio; ?>%
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 small mb-2">
                            <div class="col-6">
                                <div class="border rounded p-2 d-flex justify-content-between">
                                    <span>هدف</span>
                                    <span
                                        class="fw-semibold"><?php echo number_format((float)($r['target_amount'] ?? 0)); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2 d-flex justify-content-between">
                                    <span>تعداد فاکتور</span>
                                    <span
                                        class="fw-semibold"><?php echo number_format((int)($r['invoices_count'] ?? 0)); ?></span>
                                </div>
                            </div>
                        </div>
                        <a class="btn btn-success w-100"
                            href="index.php?page=utility_run&run_id=<?php echo (int)$r['id']; ?>">جزئیات</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>