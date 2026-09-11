<?php
// auth.php — احراز هویت ادمین/پورتال  تایم‌اوت
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// برای csrf_value()/csrf_field()
require_once __DIR__ . '/views/_helpers.php';  // حتماً require_once باشد

// --- وضعیت‌ها
if (!function_exists('is_admin')) {
  function is_admin(): bool
  {
    return !empty($_SESSION['is_admin']);
  }
}
if (!function_exists('is_portal')) {
  function is_portal(): bool
  {
    return !empty($_SESSION['portal_resident_id']);
  }
}

// --- گارد ادمین
if (!function_exists('require_admin')) {
  function require_admin(): void
  {
    if (!is_admin()) {
      header('Location: index.php?page=admin_login');
      exit;
    }
  }
}

// --- گارد پورتال (ساکن)
// auth.php
if (!function_exists('require_portal')) {
  function require_portal(): void
  {
    if (empty($_SESSION['portal_resident_id'])) {
      $_SESSION['error'] = 'برای دسترسی به این بخش، ابتدا وارد پورتال شوید.';
      header('Location: index.php?page=portal_login');
      exit;
    }
  }
}


// --- بررسی CSRF (توکن در _helpers ساخته می‌شود)
if (!function_exists('require_csrf')) {
  function require_csrf(?string $token = null, string $field = '_csrf'): void
  {
    $tok = $token ?? ($_POST[$field] ?? $_GET[$field] ?? '');
    if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)$tok)) {
      http_response_code(400);
      echo 'Invalid CSRF token';
      exit;
    }
  }
}

// --- مدیریت تایم‌اوت نشست ادمین
if (!function_exists('check_admin_timeout')) {
  /**
   * اگر آخرین فعالیت بیش از $idleMinutes دقیقه قبل بوده باشد، نشست ادمین را منقضی و به لاگین هدایت می‌کند.
   * در حالت نرمال، هر بار که صدا زده می‌شود، آخرین فعالیت را به الآن به‌روزرسانی می‌کند.
   */
  function check_admin_timeout(int $idleMinutes = 30): void
  {
    if (!is_admin()) return;
    $now  = time();
    $last = (int)($_SESSION['admin_last_activity'] ?? $now);
    if ($now - $last > $idleMinutes * 60) {
      unset($_SESSION['is_admin'], $_SESSION['admin_last_activity']);
      $_SESSION['error'] = 'نشست ادمین به دلیل عدم فعالیت منقضی شد.';
      header('Location: index.php?page=admin_login');
      exit;
    }
    $_SESSION['admin_last_activity'] = $now; // touch
  }
}
