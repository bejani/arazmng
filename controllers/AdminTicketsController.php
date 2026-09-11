<?php
// controllers/AdminTicketsController.php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../views/_helpers.php';

final class AdminTicketsController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_admin();
    }

    public function index(): void
    {
        $status = $_GET['status'] ?? 'all';
        $q = trim((string)($_GET['q'] ?? ''));

        $conds = [];
        $p = [];
        if (in_array($status, ['open', 'pending', 'closed'], true)) {
            $conds[] = "t.status = :s";
            $p[':s'] = $status;
        }
        if ($q !== '') {
            $conds[] = "(t.subject LIKE :q OR r.full_name LIKE :q)";
            $p[':q'] = '%' . $q . '%';
        }
        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        $sql = "
      SELECT t.id, t.subject, t.status, t.created_at, t.updated_at,
             r.full_name
      FROM tickets t
      JOIN residents r ON r.id = t.resident_id
      $where
      ORDER BY FIELD(t.status,'open','pending','closed'), t.updated_at DESC, t.created_at DESC
      LIMIT 500
    ";
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        include __DIR__ . '/../views/admin/tickets/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $t  = $this->pdo->prepare("
      SELECT t.*, r.full_name, r.mobile
      FROM tickets t
      JOIN residents r ON r.id = t.resident_id
      WHERE t.id=:id
    ");
        $t->execute([':id' => $id]);
        $ticket = $t->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            http_response_code(404);
            die('تیکت یافت نشد.');
        }

        $m = $this->pdo->prepare("
      SELECT sender_type, message, file_path, created_at
      FROM ticket_messages
      WHERE ticket_id=:id
      ORDER BY id ASC
    ");
        $m->execute([':id' => $id]);
        $messages = $m->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        include __DIR__ . '/../views/admin/tickets/show.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function reply(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $msg = trim((string)($_POST['message'] ?? ''));
        if ($id <= 0 || $msg === '') {
            $_SESSION['error'] = 'پیام خالی است.';
            header('Location: index.php?page=admin_ticket_show&id=' . $id);
            exit;
        }

        $filePath = $this->saveUpload('file', 'tickets');

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare("
        INSERT INTO ticket_messages (ticket_id, sender_type, message, file_path)
        VALUES (:tid, 'admin', :m, :f)
      ");
            $ins->execute([':tid' => $id, ':m' => $msg, ':f' => $filePath]);

            $this->pdo->prepare("UPDATE tickets SET status='pending' WHERE id=:id")->execute([':id' => $id]);

            $this->pdo->commit();
            $_SESSION['ok'] = 'پاسخ ثبت شد.';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $_SESSION['error'] = 'ثبت پاسخ ناموفق بود.';
        }
        header('Location: index.php?page=admin_ticket_show&id=' . $id);
        exit;
    }

    public function close(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->pdo->prepare("UPDATE tickets SET status='closed' WHERE id=:id")->execute([':id' => $id]);
        $_SESSION['ok'] = 'تیکت بسته شد.';
        header('Location: index.php?page=admin_ticket_show&id=' . $id);
        exit;
    }

    public function reopen(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->pdo->prepare("UPDATE tickets SET status='open' WHERE id=:id")->execute([':id' => $id]);
        $_SESSION['ok'] = 'تیکت باز شد.';
        header('Location: index.php?page=admin_ticket_show&id=' . $id);
        exit;
    }

    /* ========== Upload helper (تصاویر/PDF تا 5MB) ========== */
    private function saveUpload(string $field, string $subdir): ?string
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;
        if (($f['size'] ?? 0) > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'حداکثر حجم ۵MB است.';
            return null;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $_SESSION['error'] = 'فرمت مجاز نیست.';
            return null;
        }

        $base = __DIR__ . '/../uploads';
        if (!is_dir($base)) @mkdir($base, 0775, true);
        $dir = $base . '/' . trim($subdir, '/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $ext = ($mime === 'application/pdf') ? 'pdf' : (explode('/', $mime)[1] ?? 'bin');
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $_SESSION['error'] = 'ذخیره فایل ناموفق بود.';
            return null;
        }

        return 'controllers/../uploads/' . trim($subdir, '/') . '/' . $name;
    }
}
