<?php ob_start(); ?>
<h2 class="mb-3">تبدیل هزینه‌ها از PDF به Excel</h2>

<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="index.php?page=import_pdf_upload" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-8">
                <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100">📄→📊 تبدیل به اکسل</button>
            </div>
        </form>
        <small class="text-muted d-block mt-2">
            نکته: این ابزار برای PDFهای متنی طراحی شده است. اگر PDF اسکن (عکس) باشد، خروجی نخواهد گرفت.
        </small>
    </div>
</div>

<?php if(!empty($result['messages'])): ?>
<div class="card mb-3">
    <div class="card-header">نتیجه پردازش</div>
    <div class="card-body">
        <ul class="mb-0">
            <?php foreach($result['messages'] as $msg): ?>
            <li><?= htmlspecialchars($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if(!empty($result['xlsx_path'])): ?>
<div class="alert alert-success">
    ✅ فایل اکسل آماده است:
    <a href="<?= htmlspecialchars($result['xlsx_path']) ?>" class="btn btn-success btn-sm ms-2">دانلود Excel</a>
</div>
<?php endif; ?>

<?php $content = ob_get_clean(); include 'layout.php'; ?>