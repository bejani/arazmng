<?php ob_start(); ?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ایجاد شارژ برای واحد <?= htmlspecialchars($unit['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>

<body class="p-3">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">ایجاد شارژ — <?= htmlspecialchars($unit['name']) ?> (ط
                <?= htmlspecialchars($unit['floor']) ?>)</h3>
            <a class="btn btn-secondary"
                href="index.php?page=charge_collect&period=<?= urlencode($period) ?>&unit_id=<?= (int)$unit['id'] ?>">←
                بازگشت</a>
        </div>

        <form method="post" action="index.php?page=charge_generate_single" class="card p-3">
            <input type="hidden" name="unit_id" value="<?= (int)$unit['id'] ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">دوره (YYYY-MM)</label>
                    <input type="text" class="form-control" name="period" required
                        value="<?= htmlspecialchars($period) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">سررسید</label>
                    <input type="date" class="form-control" name="due_date" value="<?= htmlspecialchars($due_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">مبلغ (تومان)</label>
                    <input type="number" step="0.01" class="form-control" name="amount" required
                        value="<?= htmlspecialchars($amount) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">عنوان فاکتور</label>
                    <input type="text" class="form-control" name="subject_tpl"
                        value="<?= htmlspecialchars($subject_tpl) ?>">
                    <div class="form-text">از <code>{period}</code>، <code>{unit}</code> و <code>{floor}</code>
                        می‌توانید استفاده کنید.</div>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">ایجاد فاکتور</button>
            </div>
        </form>
    </div>
</body>

</html>
<?php
$content = ob_get_clean();
$candidates = [__DIR__ . '/../layout.php', __DIR__ . '/../../layout.php'];
$layout = null; foreach ($candidates as $p) { if (file_exists($p)) { $layout = $p; break; } }
if ($layout) { include $layout; } else { echo $content; }
?>