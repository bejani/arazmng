<?php ob_start(); ?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>شارژ ماهانه</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>

<body class="p-3">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">شارژ ماهانه</h3>
            <a href="index.php?page=charge_new" class="btn btn-primary">➕ ایجاد شارژ جدید</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>دوره</th>
                        <th>تعداد فاکتور</th>
                        <th>باز (پرداخت‌نشده/جزئی)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach(($periods ?? []) as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($p['period']) ?></td>
                        <td><?= (int)$p['cnt'] ?></td>
                        <td><?= (int)$p['open_cnt'] ?></td>
                        <td><a class="btn btn-sm btn-outline-primary"
                                href="index.php?page=charge_collect&period=<?= urlencode($p['period']) ?>">دریافت‌ها</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($periods)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">هنوز دوره‌ای ایجاد نشده است.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php $content = ob_get_clean(); include __DIR__ . '/layout_finder.php'; ?>