<?php ob_start();
require_once __DIR__ . '/../_helpers.php';
$rows  = $rows  ?? [];
$units = $units ?? [];
$f     = $filter ?? ['unit_id' => null, 'utility_type' => '', 'period' => '', 'status' => ''];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">قبض‌های من</h3>
</div>

<div class="card card-elevated mb-3">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="portal_invoices">

            <div class="col-6 col-md-3">
                <label class="form-label">واحد</label>
                <select name="unit_id" class="form-select">
                    <option value="">— همه —</option>
                    <?php foreach ($units as $u):
                        $sel = ((string)($f['unit_id'] ?? '') === (string)$u['id']) ? 'selected' : '';
                        $lbl = trim(($u['name'] ?? '') . ' - طبقه ' . (int)($u['floor'] ?? 0));
                    ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $sel ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label">نوع قبض</label>
                <select name="utility_type" class="form-select">
                    <option value="">— همه —</option>
                    <option value="gas" <?= (($f['utility_type'] ?? '') === 'gas' ? 'selected' : '') ?>>گاز</option>
                    <option value="water" <?= (($f['utility_type'] ?? '') === 'water' ? 'selected' : '') ?>>آب</option>
                    <option value="electric" <?= (($f['utility_type'] ?? '') === 'electric' ? 'selected' : '') ?>>برق</option>
                    <option value="other" <?= (($f['utility_type'] ?? '') === 'other' ? 'selected' : '') ?>>سایر</option>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label">دوره (YYYY-MM)</label>
                <input type="text" name="period" class="form-control" value="<?= h($f['period'] ?? '') ?>"
                    placeholder="1403-06">
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label">وضعیت</label>
                <select name="status" class="form-select">
                    <option value="" <?= (($f['status'] ?? '') === '' ? 'selected' : '') ?>>همه</option>
                    <option value="unpaid" <?= (($f['status'] ?? '') === 'unpaid' ? 'selected' : '') ?>>باز</option>
                    <option value="partial" <?= (($f['status'] ?? '') === 'partial' ? 'selected' : '') ?>>پرداخت بخشی</option>
                    <option value="paid" <?= (($f['status'] ?? '') === 'paid' ? 'selected' : '') ?>>تسویه شده</option>
                </select>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">اعمال فیلتر</button>
                <a href="index.php?page=portal_invoices" class="btn btn-outline-secondary">پاکسازی</a>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-modern align-middle responsive-cards">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>واحد</th>
                <th>نوع</th>
                <th>دوره</th>
                <th>عنوان</th>
                <th class="text-end">مبلغ</th>
                <th class="text-end">پرداخت‌شده</th>
                <th class="text-end">مانده</th>
                <th>وضعیت</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">قبضی یافت نشد.</td>
                </tr>
                <?php else: foreach ($rows as $i => $r):
                    $amount  = (float)($r['invoice_amount'] ?? 0);
                    $paid    = (float)($r['paid_amount'] ?? 0);
                    $balance = max(0, $amount - $paid);
                    $st = $r['_status'] ?? 'unpaid';
                    $chipClass = $st === 'paid' ? 'chip-paid' : ($st === 'partial' ? 'chip-partial' : 'chip-unpaid');
                    $utFa = match ($r['utility_type'] ?? '') {
                        'gas' => 'گاز',
                        'water' => 'آب',
                        'electric' => 'برق',
                        default => 'سایر'
                    };
                ?>
                    <tr>
                        <td data-label="#"><?= $i + 1 ?></td>
                        <td data-label="واحد"><?= h(trim(($r['unit_name'] ?? '') . ' - طبقه ' . (int)($r['floor'] ?? 0))) ?></td>
                        <td data-label="نوع"><?= h($utFa) ?></td>
                        <td data-label="دوره"><?= h($r['period'] ?? '—') ?></td>
                        <td data-label="عنوان"><?= h($r['subject'] ?? '') ?></td>
                        <td data-label="مبلغ" class="text-end"><strong><?= h(money($amount)) ?></strong></td>
                        <td data-label="پرداخت‌شده" class="text-end"><?= h(money($paid)) ?></td>
                        <td data-label="مانده" class="text-end"><?= h(money($balance)) ?></td>
                        <td data-label="وضعیت">
                            <span class="chip <?= $chipClass ?>">
                                <?= $st === 'paid' ? 'تسویه' : ($st === 'partial' ? 'بخشی' : 'باز') ?>
                            </span>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>

<?php
// $content = ob_get_clean();
// include __DIR__ . '/../layout.php';