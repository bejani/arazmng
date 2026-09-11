<?php /* تیکت جدید */ ?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">ثبت تیکت جدید</h4>
    <a class="btn btn-secondary" href="index.php?page=portal_tickets">بازگشت</a>
</div>

<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
    <?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<form method="POST" action="index.php?page=portal_ticket_store" enctype="multipart/form-data"
    class="row g-3 needs-validation" novalidate>
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>

    <div class="col-12">
        <label class="form-label">عنوان تیکت</label>
        <input type="text" name="subject" class="form-control" required maxlength="200">
        <div class="invalid-feedback">عنوان را وارد کنید.</div>
    </div>

    <div class="col-12">
        <label class="form-label">شرح مشکل/درخواست</label>
        <textarea name="body" rows="5" class="form-control" required></textarea>
        <div class="form-text">جزئیات بیشتر کمک می‌کند سریع‌تر رسیدگی شود.</div>
        <div class="invalid-feedback">توضیحات را وارد کنید.</div>
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label">ضمیمه (اختیاری)</label>
        <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf">
        <div class="form-text">حداکثر ۵MB. فرمت‌های مجاز: JPG/PNG/GIF/PDF.</div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary">ثبت تیکت</button>
    </div>
</form>

<script>
(function() {
    const form = document.querySelector('form.needs-validation');
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
})();
</script>