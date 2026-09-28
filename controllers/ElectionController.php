<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../views/_helpers.php';

final class ElectionController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    private function admin(): void { require_admin(); }
    private function portal(): int { require_portal(); return (int)($_SESSION['portal_resident_id'] ?? 0); }

    private function redirect(string $page, string $message, bool $error = false): never
    {
        $_SESSION[$error ? 'error' : 'ok'] = $message;
        header('Location: index.php?page=' . $page);
        exit;
    }

    private function findElection(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM elections WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function validateDateTime(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $value = str_replace('T', ' ', $value);
        $ts = strtotime($value);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    public function adminIndex(): void
    {
        $this->admin();
        $elections = $this->pdo->query('SELECT * FROM elections ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
        $selectedId = (int)($_GET['eid'] ?? ($elections[0]['id'] ?? 0));
        $selected = $selectedId ? $this->findElection($selectedId) : null;
        $candidates = [];
        $totalVotes = 0;
        if ($selected) {
            $st = $this->pdo->prepare(
                'SELECT c.*, COUNT(v.id) AS votes_count
                 FROM election_candidates c
                 LEFT JOIN election_votes v ON v.candidate_id = c.id
                 WHERE c.election_id = :eid
                 GROUP BY c.id
                 ORDER BY c.id ASC'
            );
            $st->execute([':eid' => $selectedId]);
            $candidates = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candidates as $c) $totalVotes += (int)$c['votes_count'];
        }
        ob_start();
        $page_title = 'انتخابات هیئت امنا';
        include __DIR__ . '/../views/admin/elections/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function adminElectionStore(): void
    {
        $this->admin();
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $startsAt = $this->validateDateTime($_POST['starts_at'] ?? null);
        $endsAt = $this->validateDateTime($_POST['ends_at'] ?? null);
        if ($title === '') $this->redirect('admin_elections', 'عنوان انتخابات الزامی است.', true);
        if ($startsAt && $endsAt && strtotime($endsAt) <= strtotime($startsAt)) {
            $this->redirect('admin_elections', 'زمان پایان باید بعد از زمان شروع باشد.', true);
        }
        $st = $this->pdo->prepare(
            'INSERT INTO elections (title, description, status, starts_at, ends_at)
             VALUES (:title, :description, "draft", :starts_at, :ends_at)'
        );
        $st->execute([':title' => $title, ':description' => $description ?: null, ':starts_at' => $startsAt, ':ends_at' => $endsAt]);
        $this->redirect('admin_elections', 'انتخابات جدید ایجاد شد.');
    }

    public function adminCandidateStore(): void
    {
        $this->admin();
        $eid = (int)($_POST['election_id'] ?? 0);
        $name = trim((string)($_POST['full_name'] ?? ''));
        if (!$this->findElection($eid)) $this->redirect('admin_elections', 'انتخابات یافت نشد.', true);
        if ($name === '') $this->redirect('admin_elections&eid=' . $eid, 'نام کاندیدا الزامی است.', true);
        $st = $this->pdo->prepare(
            'INSERT INTO election_candidates (election_id, full_name, mobile, national_id, bio, photo_url)
             VALUES (:eid, :name, :mobile, :nid, :bio, :photo)'
        );
        $st->execute([
            ':eid' => $eid,
            ':name' => $name,
            ':mobile' => trim((string)($_POST['mobile'] ?? '')) ?: null,
            ':nid' => trim((string)($_POST['national_id'] ?? '')) ?: null,
            ':bio' => trim((string)($_POST['bio'] ?? '')) ?: null,
            ':photo' => trim((string)($_POST['photo_url'] ?? '')) ?: null,
        ]);
        $this->redirect('admin_elections&eid=' . $eid, 'کاندیدا ثبت شد.');
    }

    public function adminCandidateDelete(): void
    {
        $this->admin();
        $id = (int)($_POST['id'] ?? 0);
        $st = $this->pdo->prepare('SELECT election_id FROM election_candidates WHERE id = :id');
        $st->execute([':id' => $id]);
        $eid = (int)$st->fetchColumn();
        if ($eid <= 0) $this->redirect('admin_elections', 'کاندیدا یافت نشد.', true);
        $this->pdo->prepare('DELETE FROM election_candidates WHERE id = :id')->execute([':id' => $id]);
        $this->redirect('admin_elections&eid=' . $eid, 'کاندیدا حذف شد.');
    }

    public function adminStatus(): void
    {
        $this->admin();
        $eid = (int)($_POST['election_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $election = $this->findElection($eid);
        if (!$election) $this->redirect('admin_elections', 'انتخابات یافت نشد.', true);
        if ($action === 'open') {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM election_candidates WHERE election_id = :eid AND is_active=1');
            $st->execute([':eid' => $eid]);
            if ((int)$st->fetchColumn() < 1) $this->redirect('admin_elections&eid=' . $eid, 'برای شروع رأی‌گیری حداقل یک کاندیدا ثبت کنید.', true);
            $this->pdo->prepare('UPDATE elections SET status="open", results_published=0 WHERE id=:id')->execute([':id' => $eid]);
            $this->redirect('admin_elections&eid=' . $eid, 'رأی‌گیری باز شد.');
        }
        if ($action === 'close') {
            $this->pdo->prepare('UPDATE elections SET status="closed" WHERE id=:id')->execute([':id' => $eid]);
            $this->redirect('admin_elections&eid=' . $eid, 'رأی‌گیری بسته شد.');
        }
        if ($action === 'publish') {
            if ($election['status'] !== 'closed') $this->redirect('admin_elections&eid=' . $eid, 'ابتدا رأی‌گیری را ببندید.', true);
            $this->pdo->prepare('UPDATE elections SET results_published=1 WHERE id=:id')->execute([':id' => $eid]);
            $this->redirect('admin_elections&eid=' . $eid, 'نتایج برای کاربران منتشر شد.');
        }
        $this->redirect('admin_elections&eid=' . $eid, 'عملیات نامعتبر است.', true);
    }

    public function portalIndex(): void
    {
        $rid = $this->portal();
        $st = $this->pdo->query(
            'SELECT * FROM elections
             WHERE status IN ("open", "closed") OR results_published=1
             ORDER BY CASE WHEN status="open" THEN 0 ELSE 1 END, id DESC LIMIT 1'
        );
        $election = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $candidates = [];
        $votedCandidateId = null;
        $canVote = false;
        if ($election) {
            $cs = $this->pdo->prepare(
                'SELECT c.*, COUNT(v.id) AS votes_count
                 FROM election_candidates c LEFT JOIN election_votes v ON v.candidate_id=c.id
                 WHERE c.election_id=:eid AND c.is_active=1 GROUP BY c.id ORDER BY c.id'
            );
            $cs->execute([':eid' => (int)$election['id']]);
            $candidates = $cs->fetchAll(PDO::FETCH_ASSOC);
            $vs = $this->pdo->prepare('SELECT candidate_id FROM election_votes WHERE election_id=:eid AND resident_id=:rid');
            $vs->execute([':eid' => (int)$election['id'], ':rid' => $rid]);
            $votedCandidateId = $vs->fetchColumn();
            $canVote = $election['status'] === 'open' && $votedCandidateId === false;
        }
        ob_start();
        $page_title = 'انتخابات هیئت امنا';
        include __DIR__ . '/../views/portal/election.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function portalVote(): void
    {
        $rid = $this->portal();
        $eid = (int)($_POST['election_id'] ?? 0);
        $cid = (int)($_POST['candidate_id'] ?? 0);
        $election = $this->findElection($eid);
        if (!$election || $election['status'] !== 'open') $this->redirect('portal_election', 'این رأی‌گیری فعال نیست.', true);
        $st = $this->pdo->prepare('SELECT id FROM election_candidates WHERE id=:cid AND election_id=:eid AND is_active=1');
        $st->execute([':cid' => $cid, ':eid' => $eid]);
        if (!$st->fetchColumn()) $this->redirect('portal_election', 'کاندیدای انتخاب‌شده معتبر نیست.', true);
        try {
            $ins = $this->pdo->prepare('INSERT INTO election_votes (election_id, candidate_id, resident_id) VALUES (:eid,:cid,:rid)');
            $ins->execute([':eid' => $eid, ':cid' => $cid, ':rid' => $rid]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) $this->redirect('portal_election', 'شما قبلاً در این انتخابات رأی داده‌اید.', true);
            throw $e;
        }
        $this->redirect('portal_election', 'رأی شما با موفقیت ثبت شد.');
    }
}
