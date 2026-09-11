<?php ob_start();
require_once __DIR__ . '/../_helpers.php';

/**
 * ورودی از کنترلر:
 * $row        : رکورد هزینه (expenses.*)
 * $categories : آرایه عناوین دسته‌ها (اختیاری)
 * $units      : لیست واحدها [id,name,floor]
 * $residents  : لیست ساکنان فعال [id,full_name]
 */
$row        = $row        ?? [];
$categories = $categories ?? [];
$units      = $units      ?? [];
$residents  = $residents  ?? [];

$id            = (int)($row['id'] ?? 0);
$title         = (string)($row['title'] ?? '');
$category      = (string)($row['category'] ?? '');
$amount        = (float) ($row['amount'] ?? 0);
$gdate         = (string)($row['expense_date'] ?? ''); // YYYY-mm-dd
$unit_id       = $row['unit_id'] !== null ? (int)$row['unit_id'] : '';
$spender_id    = $row['spender_resident_id'] !== null ? (int)$row['spender_resident_id'] : '';
$spender_name  = (string)($row['spender_name'] ?? '');
$notes         = (string)($row['notes'] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">ویرایش هزینه #<?= h($id) ?></h3>
    <div class="d-flex gap-2">
        <a href="index.php?page=expenses" class="btn btn-secondary">بازگشت</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="index.php?page=expense_update" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= h($id) ?>">

            <div class="col-md-4">
                <label class="form-label">عنوان هزینه</label>
                <input type="text" name="title" class="form-control" value="<?= h($title) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">دسته</label>
                <input type="text" name="category" class="form-control" list="catlist" value="<?= h($category) ?>"
                    placeholder="مثلاً: برق">
                <datalist id="catlist">
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= h($c) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-md-2">
                <label class="form-label">مبلغ</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= h($amount) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">تاریخ (شمسی)</label>
                <!-- مقدار میلادی برای ارسال -->
                <input type="hidden" name="expense_date" value="<?= h($gdate) ?>">
                <!-- ورودی نمایش شمسی با Datepicker -->
                <input type="text" class="form-control jdate" data-target="expense_date"
                    value="<?= h(jdate($gdate, 'Y/m/d')) ?>" placeholder="۱۴۰۳/۰۶/۱۰">
                <div class="form-text">ذخیره‌سازی در دیتابیس به‌صورت میلادی انجام می‌شود.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">هزینه‌کننده</label>
                <div class="input-group">
                    <select name="spender_id" class="form-select">
                        <option value="">— انتخاب از ساکنان —</option>
                        <?php foreach ($residents as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"
                            <?= ((string)$spender_id===(string)$r['id'])?'selected':'' ?>>
                            <?= h($r['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="spender_name" class="form-control" placeholder="یا نام دستی"
                        value="<?= h($spender_name) ?>">
                </div>
                <div class="form-text">
                    اگر «نام دستی» را خالی بگذارید و از لیست ساکنان انتخاب کنید، هنگام ذخیره **نام ساکن** به‌صورت خودکار
                    ثبت می‌شود.
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">واحد</label>
                <select name="unit_id" class="form-select">
                    <option value="" <?= $unit_id===''?'selected':'' ?>>— عمومی —</option>
                    <?php foreach ($units as $u):
            $lbl = trim(($u['name']??'').' - طبقه '.(int)($u['floor']??0)); ?>
                    <option value="<?= (int)$u['id'] ?>" <?= ((string)$unit_id===(string)$u['id'])?'selected':'' ?>>
                        <?= h($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-9">
                <label class="form-label">توضیحات</label>
                <input type="text" name="notes" class="form-control" value="<?= h($notes) ?>" placeholder="توضیحات...">
            </div>

            <div class="col-12 d-flex gap-2 mt-2">
                <button class="btn btn-primary">ذخیره تغییرات</button>
                <a href="index.php?page=expenses" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$paths = [__DIR__.'/../layout.php', dirname(__DIR__,1).'/layout.php'];
foreach ($paths as $p) { if (is_file($p)) { include $p; return; } }
echo $content;