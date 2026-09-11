<?php
// controllers/AdminAnnouncementsController.php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../views/_helpers.php';

final class AdminAnnouncementsController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_admin();
    }

    public function index(): void
    {
        $st = $this->pdo->query("
      SELECT id, title, is_pinned, visible_in_portal, created_at, updated_at
      FROM announcements
      ORDER BY is_pinned DESC, created_at DESC
      LIMIT 500
    ");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        include __DIR__ . '/../views/admin/announcements/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function new(): void
    {
        $ann = ['title' => '', 'body' => '', 'is_pinned' => 0, 'visible_in_portal' => 1];
        $mode = 'new';
        ob_start();
        include __DIR__ . '/../views/admin/announcements/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function store(): void
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $body  = trim((string)($_POST['body']  ?? ''));
        $pin   = !empty($_POST['is_pinned']) ? 1 : 0;
        $vis   = !empty($_POST['visible_in_portal']) ? 1 : 0;

        if ($title === '' || $body === '') {
            $_SESSION['error'] = 'عنوان و متن اعلان الزامی است.';
            header('Location: index.php?page=admin_announcement_new');
            exit;
        }

        $st = $this->pdo->prepare("
      INSERT INTO announcements (title, body, is_pinned, visible_in_portal, created_by)
      VALUES (:t, :b, :p, :v, :u)
    ");
        $st->execute([
            ':t' => $title,
            ':b' => $body,
            ':p' => $pin,
            ':v' => $vis,
            ':u' => ($_SESSION['admin_user_id'] ?? null),
        ]);

        $_SESSION['ok'] = 'اعلان ثبت شد.';
        header('Location: index.php?page=admin_announcements');
        exit;
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $st = $this->pdo->prepare("SELECT * FROM announcements WHERE id=:id");
        $st->execute([':id' => $id]);
        $ann = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ann) {
            http_response_code(404);
            die('اعلان یافت نشد.');
        }
        $mode = 'edit';
        ob_start();
        include __DIR__ . '/../views/admin/announcements/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function update(): void
    {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $body  = trim((string)($_POST['body']  ?? ''));
        $pin   = !empty($_POST['is_pinned']) ? 1 : 0;
        $vis   = !empty($_POST['visible_in_portal']) ? 1 : 0;

        if ($id <= 0 || $title === '' || $body === '') {
            $_SESSION['error'] = 'ورودی نامعتبر است.';
            header('Location: index.php?page=admin_announcements');
            exit;
        }

        $st = $this->pdo->prepare("
      UPDATE announcements
      SET title=:t, body=:b, is_pinned=:p, visible_in_portal=:v
      WHERE id=:id
    ");
        $st->execute([':t' => $title, ':b' => $body, ':p' => $pin, ':v' => $vis, ':id' => $id]);

        $_SESSION['ok'] = 'اعلان بروزرسانی شد.';
        header('Location: index.php?page=admin_announcements');
        exit;
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $st = $this->pdo->prepare("DELETE FROM announcements WHERE id=:id");
        $st->execute([':id' => $id]);
        $_SESSION['ok'] = 'اعلان حذف شد.';
        header('Location: index.php?page=admin_announcements');
        exit;
    }

    public function togglePin(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->pdo->prepare("UPDATE announcements SET is_pinned = 1 - is_pinned WHERE id=:id")->execute([':id' => $id]);
        header('Location: index.php?page=admin_announcements');
        exit;
    }

    public function toggleVisibility(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->pdo->prepare("UPDATE announcements SET visible_in_portal = 1 - visible_in_portal WHERE id=:id")->execute([':id' => $id]);
        header('Location: index.php?page=admin_announcements');
        exit;
    }
}
