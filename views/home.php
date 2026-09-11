<?php ob_start(); ?>
<h2 class="mb-4">داشبورد ساختمان</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-bg-primary shadow">
            <div class="card-body text-center">
                <h4><?= $units ?></h4>
                <p class="card-text">واحد</p>
                <a href="index.php?page=units" class="btn btn-light btn-sm">مشاهده</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-success shadow">
            <div class="card-body text-center">
                <h4><?= $residents ?></h4>
                <p class="card-text">ساکن</p>
                <a href="index.php?page=residents" class="btn btn-light btn-sm">مشاهده</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-warning shadow">
            <div class="card-body text-center">
                <h4><?= $invoices ?></h4>
                <p class="card-text">صورتحساب</p>
                <a href="index.php?page=invoices" class="btn btn-light btn-sm">مشاهده</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-danger shadow">
            <div class="card-body text-center">
                <h4><?= $payments ?></h4>
                <p class="card-text">پرداخت</p>
                <a href="index.php?page=payments" class="btn btn-light btn-sm">مشاهده</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card app-card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                    <div class="app-emoji me-2">🧾</div>
                    <h5 class="card-title mb-0">هزینه‌ها</h5>
                </div>
                <p class="text-muted small mb-3">ثبت و مدیریت هزینه‌های عمومی یا منتسب به واحدها.</p>
                <div class="mt-auto"><a href="index.php?page=expenses" class="btn btn-outline-primary w-100">مشاهده</a>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="alert alert-info flex-grow-1 me-3">
        👋 خوش آمدید! از منوی بالا یا دکمه‌های زیر می‌توانید واحدها، ساکنان، صورتحساب‌ها و پرداخت‌ها را مدیریت کنید.
    </div>
    <a href="index.php?page=import" class="btn btn-outline-primary">
        📂 ایمپورت اکسل
    </a>
    <a href="index.php?page=import_pdf" class="btn btn-outline-secondary ms-2">📄 PDF → Excel</a>
</div>




<?php $content = ob_get_clean(); include 'layout.php'; ?>