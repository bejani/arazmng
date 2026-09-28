<?php /* views/layout_portal.php — نسخه موبایل‌پسند با تم سبز/آبی نفتی و CSS جدا */ ?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پورتال ساکنان</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- استایل جداگانه‌ی پورتال -->
    <link rel="stylesheet" href="assets/portal.css?v=1.0">
    <!-- رنگ نوار مرورگر موبایل مطابق هدر -->
    <meta name="theme-color" content="#0b7285">
</head>

<body class="bg-white">
    <?php
    // Helper برای اکتیو کردن آیتم‌ها
    $page = $_GET['page'] ?? '';
    $isActive = function (array $names) use ($page) {
        return in_array($page, $names, true) ? 'active' : '';
    };

    $showBillingLinks = false;

    // شمارنده‌های ساده (اختیاری)
    $unpaidCount = (int)($_SESSION['_ui_unpaid_count'] ?? 0);
    ?>

    <!-- نوار بالا (Offcanvas روی موبایل) -->
    <nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
        <div class="container app-container">
            <a class="navbar-brand brand" href="index.php?page=portal_dashboard">پورتال ساکن</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="offcanvas offcanvas-end" tabindex="-1" id="offNav">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">منو</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body d-lg-flex align-items-center">
                    <?php
                    $page = $_GET['page'] ?? '';
                    $isActive = function (array $names) use ($page) {
                        return in_array($page, $names, true) ? 'active' : '';
                    };
                    ?>
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link <?= $isActive(['portal_dashboard']) ?>"
                                href="index.php?page=portal_dashboard">داشبورد</a></li>
                        <li class="nav-item"><a class="nav-link <?= $isActive(['portal_announcements']) ?>"
                                href="index.php?page=portal_announcements">اعلان‌ها</a></li>
                        <li class="nav-item"><a class="nav-link <?= $isActive(['portal_election']) ?>"
                                href="index.php?page=portal_election">انتخابات هیئت امنا</a></li>
                        <li class="nav-item"><a class="nav-link <?= $isActive(['portal_expenses']) ?>"
                                href="index.php?page=portal_expenses">هزینه‌های عمومی</a></li>
                        <li class="nav-item">
                            <a class="nav-link <?= $isActive(['portal_invoices']) ?>"
                                href="index.php?page=portal_invoices">قبض‌ها</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= $isActive(['portal_tickets', 'portal_ticket_new', 'portal_ticket_show']) ?>"
                                href="index.php?page=portal_tickets">پشتیبانی</a>
                        </li>
                    </ul>

                    <div class="d-flex gap-2 align-items-center ps-lg-3">
                        <?php if (!empty($_SESSION['portal_resident_name'])): ?>
                            <span class="navbar-text small d-none d-lg-inline text-white-50">
                                سلام، <?= h($_SESSION['portal_resident_name']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($_SESSION['portal_resident_id'])): ?>
                            <a class="btn btn-sm btn-outline-light" href="index.php?page=portal_change_pin">تغییر رمز</a>
                            <a class="btn btn-sm btn-light" href="index.php?page=portal_profile">پروفایل</a>
                            <a class="btn btn-sm btn-danger" href="index.php?page=portal_logout">خروج</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-light" href="index.php?page=portal_login">ورود</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- محتوا -->
    <div class="container app-container content-pad">
        <?php if (!empty($_SESSION['ok'])): ?>
            <div class="alert alert-success auto-dismiss"><?= h($_SESSION['ok']);
                                                            unset($_SESSION['ok']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger auto-dismiss"><?= h($_SESSION['error']);
                                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>

    <!-- Bottom Tab (فقط موبایل) -->
    <?php if (!empty($_SESSION['portal_resident_id'])): ?>
        <?php
        $unreadAnnouncements = (int)($_SESSION['_ui_ann_unread'] ?? 0);
        $openTickets         = (int)($_SESSION['_ui_ticket_open'] ?? 0);
        ?>
        <nav class="bottom-tab d-lg-none" role="navigation" aria-label="منوی پایین">
            <div class="container app-container py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <a class="btn btn-link <?= $isActive(['portal_dashboard']) ?>" href="index.php?page=portal_dashboard"
                        aria-label="خانه">🏠
                        <div class="small">خانه</div>
                    </a>

                    <a class="btn btn-link <?= $isActive(['portal_announcements']) ?>"
                        href="index.php?page=portal_announcements" aria-label="اعلان‌ها">🔔
                        <?php if ($unreadAnnouncements > 0): ?>
                            <span class="badge text-bg-danger ms-1"><?= (int)$unreadAnnouncements ?></span>
                        <?php endif; ?>
                        <div class="small">اعلان‌ها</div>
                    </a>

                    <a class="btn btn-link <?= $isActive(['portal_election']) ?>" href="index.php?page=portal_election" aria-label="انتخابات">🗳️<div class="small">انتخابات</div></a>
                    <a class="btn btn-link <?= $isActive(['portal_expenses']) ?>" href="index.php?page=portal_expenses"
                        aria-label="هزینه‌ها">📊
                        <div class="small">هزینه‌ها</div>
                    </a>
                    <a class="btn btn-link <?= $isActive(['portal_invoices']) ?>" href="index.php?page=portal_invoices"
                        aria-label="قبض‌ها">🧾
                        <div class="small">قبض‌ها</div>
                    </a>

                    <a class="btn btn-link <?= $isActive(['portal_tickets', 'portal_ticket_new', 'portal_ticket_show']) ?>"
                        href="index.php?page=portal_tickets" aria-label="پشتیبانی">💬
                        <?php if ($openTickets > 0): ?>
                            <span class="badge text-bg-warning ms-1"><?= (int)$openTickets ?></span>
                        <?php endif; ?>
                        <div class="small">پشتیبانی</div>
                    </a>

                    <a class="btn btn-link <?= $isActive(['portal_profile']) ?>" href="index.php?page=portal_profile"
                        aria-label="پروفایل">👤
                        <div class="small">پروفایل</div>
                    </a>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Auto-dismiss flash messages -->
    <script>
        setTimeout(function() {
            document.querySelectorAll('.auto-dismiss').forEach(function(el) {
                el.style.opacity = '0';
                setTimeout(function() {
                    el.remove();
                }, 700);
            });
        }, 3500);
    </script>

    <!-- CSRF inject برای تمام فرم‌های POST -->
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
    <!-- کد تقویم جلالی شما (بدون تغییر) -->
</body>

</html>