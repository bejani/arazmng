<?php ob_start(); ?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>دریافت شارژ - <?= htmlspecialchars($period ?? '') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>

<body class="p-3">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">دریافت شارژ - <?= htmlspecialchars($period ?? '') ?></h3>
            <div class="d-flex gap-2">
                <a class="btn btn-secondary" href="index.php?page=charges">← بازگشت</a>
                <a class="btn btn-primary"
                    href="index.php?page=charge_new&period=<?= urlencode($period ?? date('Y-m')) ?>">➕ ایجاد برای دوره
                    جدید</a>
            </div>
        </div>

        <div class="alert alert-info">
            مجموع بدهی باز این دوره: <b><?= number_format((float)($sum_remain ?? 0), 0) ?></b> تومان
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>واحد</th>
                        <th>شرح</th>
                        <th>مبلغ</th>
                        <th>پرداخت‌شده</th>
                        <th>باقی‌مانده</th>
                        <th>وضعیت</th>
                        <th style="width:260px">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach(($invoices ?? []) as $i => $r): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($r['unit_name']) ?></td>
                        <td><?= htmlspecialchars($r['subject']) ?></td>
                        <td><?= number_format($r['amount'], 0) ?></td>
                        <td><?= number_format($r['paid'], 0) ?></td>
                        <td class="fw-bold"><?= number_format($r['remain'], 0) ?></td>
                        <td>
                            <?php
            $labels = ['unpaid'=>'danger','partial'=>'warning','paid'=>'success','draft'=>'secondary','canceled'=>'dark'];
            $names =  ['unpaid'=>'پرداخت‌نشده','partial'=>'جزئی','paid'=>'تسویه','draft'=>'پیش‌نویس','canceled'=>'باطل'];
            $cls = $labels[$r['status']] ?? 'secondary';
            ?>
                            <span class="badge text-bg-<?= $cls ?>"><?= $names[$r['status']] ?? $r['status'] ?></span>
                        </td>
                        <td>
                            <form method="post" action="index.php?page=charge_quickpay" class="d-flex gap-2">
                                <input type="hidden" name="invoice_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="period" value="<?= htmlspecialchars($period ?? '') ?>">
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="مبلغ"
                                    value="<?= htmlspecialchars($r['remain']) ?>" style="max-width:120px">
                                <select name="method" class="form-select" style="max-width:120px">
                                    <option value="cash">نقد</option>
                                    <option value="bank">کارت‌به‌کارت</option>
                                    <option value="pos">دستگاه کارت</option>
                                    <option value="online">آنلاین</option>
                                </select>
                                <button class="btn btn-success">ثبت پرداخت</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">برای این دوره فاکتوری وجود ندارد.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php $content = ob_get_clean(); include __DIR__ . '/layout_finder.php'; ?>