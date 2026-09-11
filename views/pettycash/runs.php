<?php

/** @var array $runs */
/** @var array $methods */
/** @var string $csrf */
require_once __DIR__ . '/../_helpers.php';
if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>
<style>
    /* ریسپانسیو: تیترِ جدول بچسبد بالا */
    .sticky-th th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8f9fa;
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">راندهای تنخواه</h3>
        <!-- اگر صفحه ایجاد راند دارید -->
        <!-- <a class="btn btn-success" href="index.php?page=utility_create">ایجاد راند جدید</a> -->
    </div>

    <?php if (!empty($_SESSION['ok'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['ok']);
                                            unset($_SESSION['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-warning"><?php echo h($_SESSION['error']);
                                            unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light sticky-th">
                        <tr>
                            <th class="d-none d-md-table-cell">#راند</th>
                            <th>عنوان</th>
                            <th class="d-none d-sm-table-cell">دوره</th>
                            <th class="text-end d-none d-lg-table-cell">هدف</th>
                            <th class="text-end d-none d-xl-table-cell">تعداد فاکتور</th>
                            <th class="text-end d-none d-lg-table-cell">جمع فاکتورها</th>
                            <th class="text-end d-none d-xl-table-cell">جمع پرداخت</th>
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
                                    <td class="d-none d-md-table-cell"><?php echo h($r['id']); ?></td>
                                    <td><?php echo h($r['title'] ?? '—'); ?></td>
                                    <td class="d-none d-sm-table-cell"><?php echo h($r['period'] ?? ''); ?></td>
                                    <td class="text-end d-none d-lg-table-cell">
                                        <?php echo number_format((float)($r['target_amount'] ?? 0)); ?></td>
                                    <td class="text-end d-none d-xl-table-cell">
                                        <?php echo number_format((int)($r['invoices_count'] ?? 0)); ?></td>
                                    <td class="text-end d-none d-lg-table-cell"><?php echo number_format($invoiced); ?></td>
                                    <td class="text-end d-none d-xl-table-cell"><?php echo number_format($paid); ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format($remain); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary w-100 w-sm-auto"
                                            href="index.php?page=pettycash_run&run_id=<?php echo (int)$r['id']; ?>">جزئیات</a>
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