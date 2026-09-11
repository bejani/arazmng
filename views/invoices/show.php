<?php ob_start(); ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>فاکتور #<?= (int)($invoice['id'] ?? 0) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>
<body class="p-3">
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">فاکتور #<?= (int)$invoice['id'] ?> — <?= htmlspecialchars($invoice['unit_name'] ?? '') ?></h3>
    <a class="btn btn-secondary" href="index.php?page=invoices">← بازگشت</a>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">مشخصات</h5>
          <div class="row">
            <div class="col-5 text-muted">عنوان:</div><div class="col-7"><?= htmlspecialchars($invoice['subject'] ?? '') ?></div>
            <div class="col-5 text-muted">مبلغ:</div><div class="col-7"><?= number_format((float)$invoice['amount'],0) ?></div>
            <div class="col-5 text-muted">دوره:</div><div class="col-7"><?= htmlspecialchars($invoice['period'] ?? '-') ?></div>
            <div class="col-5 text-muted">صدور:</div><div class="col-7"><?= htmlspecialchars($invoice['issue_date'] ?? '') ?></div>
            <div class="col-5 text-muted">سررسید:</div><div class="col-7"><?= htmlspecialchars($invoice['due_date'] ?? '') ?></div>
            <div class="col-5 text-muted">وضعیت:</div>
            <?php
              $labels = ['unpaid'=>'danger','partial'=>'warning','paid'=>'success','draft'=>'secondary','canceled'=>'dark'];
              $names  = ['unpaid'=>'پرداخت‌نشده','partial'=>'جزئی','paid'=>'تسویه','draft'=>'پیش‌نویس','canceled'=>'باطل'];
              $cls = $labels[$invoice['status'] ?? ''] ?? 'secondary';
              $nm  = $names[$invoice['status'] ?? ''] ?? ($invoice['status'] ?? '-');
            ?>
            <div class="col-7"><span class="badge text-bg-<?= $cls ?>"><?= $nm ?></span></div>
            <div class="col-5 text-muted">پرداخت‌شده:</div><div class="col-7"><?= number_format((float)$invoice['paid'],0) ?></div>
            <div class="col-5 text-muted">باقی‌مانده:</div><div class="col-7 fw-bold"><?= number_format((float)$invoice['remain'],0) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">ثبت پرداخت</h5>
          <form method="post" action="index.php?page=payment_store" class="row g-2">
            <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
            <div class="col-md-4">
              <label class="form-label">تاریخ</label>
              <input class="form-control" type="date" name="pay_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">مبلغ</label>
              <input class="form-control" type="number" step="0.01" name="amount" value="<?= htmlspecialchars($invoice['remain']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">روش</label>
              <select name="method" class="form-select">
                <option value="cash">نقد</option>
                <option value="bank">کارت‌به‌کارت</option>
                <option value="pos">دستگاه کارت</option>
                <option value="online">آنلاین</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">رسید</label>
              <input class="form-control" type="text" name="ref" placeholder="کد پیگیری/رسید">
            </div>
            <div class="col-md-6">
              <label class="form-label">یادداشت</label>
              <input class="form-control" type="text" name="note" placeholder="یادداشت اختیاری">
            </div>
            <div class="col-12 mt-2">
              <button class="btn btn-success">ثبت پرداخت</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-body">
      <h5 class="card-title">پرداخت‌ها</h5>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>تاریخ</th>
              <th>مبلغ</th>
              <th>روش</th>
              <th>رسید</th>
              <th>یادداشت</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($payments ?? []) as $i => $p): ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($p['pay_date'] ?? '') ?></td>
                <td><?= number_format((float)$p['amount'], 0) ?></td>
                <td><?= htmlspecialchars($p['method'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['ref'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['note'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?>
              <tr><td colspan="6" class="text-center text-muted">پرداختی ثبت نشده است.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
<?php
$content = ob_get_clean();
$candidates = [__DIR__ . '/../layout.php', __DIR__ . '/../../layout.php'];
$layout = null; foreach ($candidates as $p) { if (file_exists($p)) { $layout = $p; break; } }
if ($layout) { include $layout; } else { echo $content; }
?>
