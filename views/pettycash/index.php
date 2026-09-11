<?php

/** @var array $rows */
/** @var string $csrf */
require_once __DIR__ . '/../_helpers.php';
if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="container my-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">تنخواه‌ها</h3>
        <a class="btn btn-success" href="index.php?page=pettycash_create">ایجاد راند جدید</a>
    </div>

    <?php if (!empty($_SESSION['ok'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['ok']);
                                            unset($_SESSION['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['error']);
                                        unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#فاکتور</th>
                            <th>راند</th>
                            <th>دوره</th>
                            <th>واحد</th>
                            <th class="text-end">مبلغ</th>
                            <th class="text-end">پرداخت‌شده</th>
                            <th class="text-end">مانده</th>
                            <th>وضعیت</th>
                            <th style="width:220px">ثبت پرداخت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">موردی یافت نشد.</td>
                            </tr>
                            <?php else: foreach ($rows as $r):
                                $amount = (float)($r['amount'] ?? 0);
                                $paid   = (float)($r['paid_amount'] ?? 0);
                                $remain = max(0, $amount - $paid);
                                $status = $remain <= 0 ? 'تسویه' : ($paid > 0 ? 'نیمه‌تسویه' : 'بدهکار');
                                $badge  = $remain <= 0 ? 'bg-success' : ($paid > 0 ? 'bg-warning text-dark' : 'bg-danger');
                                $period = '-';
                                if (!empty($r['period_start']) || !empty($r['period_end'])) {
                                    $ps = $r['period_start'] ?? null;
                                    $pe = $r['period_end'] ?? null;
                                    if ($ps && $pe && $ps !== $pe) {
                                        $period = h($ps) . ' تا ' . h($pe);
                                    } else {
                                        $period = h($ps ?: $pe);
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?php echo h($r['id'] ?? '-'); ?></td>
                                    <td><?php echo h($r['title'] ?? '—'); ?></td>
                                    <td><?php echo $period; ?></td>
                                    <td><?php echo h($r['unit_id'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format($amount); ?></td>
                                    <td class="text-end"><?php echo number_format($paid); ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format($remain); ?></td>
                                    <td><span class="badge <?php echo $badge; ?>"><?php echo h($status); ?></span></td>
                                    <td>
                                        <?php if ($remain <= 0): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <form method="post" action="index.php?page=pettycash_pay" class="d-flex gap-1">
                                                <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
                                                <input type="hidden" name="invoice_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="number" name="amount" min="1" max="<?php echo (int)$remain; ?>"
                                                    class="form-control form-control-sm" style="width:110px"
                                                    placeholder="مثلاً 100000">
                                                <input type="text" name="method" class="form-control form-control-sm"
                                                    style="width:90px" placeholder="نقد/کارت">
                                                <button class="btn btn-sm btn-primary">ثبت</button>
                                            </form>
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