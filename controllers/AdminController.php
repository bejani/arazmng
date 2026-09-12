<?php
// controllers/AdminController.php
declare(strict_types=1);

final class AdminController
{
    public function __construct(private PDO $pdo) {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function login(): void {
        include __DIR__ . '/../views/admin/login.php';
    }

    public function doLogin(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
    
        $pwd = trim((string)($_POST['password'] ?? ''));
    
        // 1) از ENV بخوان (با trim)
        $hash = trim((string)(getenv('ADMIN_PASSWORD_HASH') ?: ''));
        $plain = trim((string)(getenv('ADMIN_PASSWORD') ?: ''));
    
        // 2) اگر فایل محلی داشتیم، ازش override کنیم (برای Laragon راحت‌تره)
        // فایل: project_root/admin.secret.php  محتوا:
        // <?php return ['hash'=>'$2y$10$....'] یا ['password'=>'mySecret'];
        $secretFile = __DIR__ . '/../admin.secret.php';
        if (is_file($secretFile)) {
            $cfg = include $secretFile;
            if (is_array($cfg)) {
                if (!empty($cfg['hash']))    { $hash  = trim((string)$cfg['hash']); }
                if (!empty($cfg['password'])){ $plain = trim((string)$cfg['password']); }
            }
        }
    
        $ok = false;
        if ($hash !== '') {
            $ok = password_verify($pwd, $hash);
        } elseif ($plain !== '') {
            // Plain-text fallback is retained only for local legacy deployments.
            $ok = hash_equals($plain, $pwd);
        }
    
        if ($ok) {
            $_SESSION['is_admin'] = 1;
            session_regenerate_id(true); // جلوگیری از session fixation
            $_SESSION['ok'] = 'ورود ادمین انجام شد.';
            header('Location: index.php?page=home'); exit;
        }
        $_SESSION['error'] = 'رمز ادمین نادرست است.';
        header('Location: index.php?page=admin_login'); exit;
    }
        public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($_SESSION['is_admin'], $_SESSION['admin_last_activity']);
        $_SESSION['ok'] = 'خروج ادمین انجام شد.';
        header('Location: index.php?page=home'); exit;
    }
}
