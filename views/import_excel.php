<?php ob_start(); ?>
<h2 class="mb-3">ایمپورت از اکسل/CSV (A=طبقه، B=نام واحد، C=نام ساکن)</h2>

<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="index.php?page=import_excel" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-6">
                <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="has_header" id="has_header">
                    <label class="form-check-label" for="has_header">ردیف اول هدر است</label>
                </div>
            </div>
            <div class="col-md-3">
                <button class="btn btn-success w-100">📂 آپلود و ایمپورت</button>
            </div>
        </form>
        <small class="text-muted d-block mt-2">
            فرمت ستون‌ها: <strong>A=شماره طبقه</strong>، <strong>B=نام واحد</strong>، <strong>C=نام ساکن</strong>.
            اگر فایل شما هدر دارد (مثلاً «طبقه/نام واحد/نام ساکن»)، تیک بالا را بزنید.
        </small>
    </div>
</div>

<?php if(!empty($import_log)): ?>
<div class="card">
    <div class="card-header">نتیجه ایمپورت</div>
    <div class="card-body">
        <ul class="mb-0">
            <?php foreach($import_log as $msg): ?>
            <li><?= htmlspecialchars($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php $content = ob_get_clean(); include 'layout.php'; ?>