<?php /* views/layout_admin.php — تم سبز تیره با CSS جدا */ ?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>مدیریت ساختمان</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- استایل اختصاصی ادمین -->
    <link rel="stylesheet" href="assets/admin.css?v=1.0">
    <!-- رنگ نوار مرورگر موبایل نزدیک به سبز تیره -->
    <meta name="theme-color" content="#0f5132">
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark admin-navbar mb-3">
        <div class="container app-container">
            <a class="navbar-brand brand" href="index.php?page=home">مدیریت ساختمان</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navAdmin">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=units">واحدها</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=residents">ساکنان</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=invoices">فاکتورها</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=payments">پرداخت‌ها</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=charges">شارژها</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=expenses">هزینه‌ها</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=utilities">قبض‌ها</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=pettycash">تنخواه</a></li>

                        <!-- <li class="nav-item"><a class="nav-link" href="index.php?page=import_units_owners">ورود از اکسل</a> -->
                        </li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=admin_announcements">📢 اعلان‌ها</a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="index.php?page=admin_tickets">🎫 تیکت‌ها</a></li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex gap-2">
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                        <a class="btn btn-sm btn-outline-light" href="index.php?page=admin_logout">خروج ادمین</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-primary" href="index.php?page=admin_login">ورود ادمین</a>
                        <a class="btn btn-sm btn-outline-info" href="index.php?page=portal_login">ورود پورتال ساکنان</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container app-container">
        <!-- CONTENT START -->
        <?= $content ?>
        <!-- CONTENT END -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- تزریق خودکار CSRF به همه فرم‌های POST -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var csrf = '<?= h(csrf_value()) ?>';
            document.querySelectorAll('form[method="POST"]').forEach(function(f) {
                if (!f.querySelector('input[name="_csrf"]')) {
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = '_csrf';
                    i.value = csrf;
                    f.appendChild(i);
                }
            });
        });
    </script>

    <!-- Jalali Datepicker (JS بدون تغییر) -->
    <script>
        /* اینجا فقط JS شما بماند؛ استایل‌های تاریخ‌نگار به admin.css منتقل شده‌اند */
        /* ... JDP code exactly as before ... */
    </script>
</body>

</html>