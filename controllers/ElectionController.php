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
        $role = (string)($_POST['candidate_role'] ?? 'trustee');
        if (!in_array($role, ['trustee', 'auditor'], true)) $role = 'trustee';
        $st = $this->pdo->prepare(
            'INSERT INTO election_candidates (election_id, candidate_role, full_name, mobile, national_id, bio, photo_url)
             VALUES (:eid, :role, :name, :mobile, :nid, :bio, :photo)'
        );
        $st->execute([
            ':eid' => $eid,
            ':role' => $role,
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
            $st = $this->pdo->prepare('SELECT candidate_role, COUNT(*) AS total FROM election_candidates WHERE election_id = :eid AND is_active=1 GROUP BY candidate_role');
            $st->execute([':eid' => $eid]);
            $counts = ['trustee' => 0, 'auditor' => 0];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[$row['candidate_role']] = (int)$row['total'];
            if ($counts['trustee'] < 4) $this->redirect('admin_elections&eid=' . $eid, 'برای شروع رأی‌گیری حداقل ۴ کاندیدای هیئت امنا لازم است.', true);
            if ($counts['auditor'] < 2) $this->redirect('admin_elections&eid=' . $eid, 'برای شروع رأی‌گیری حداقل ۲ کاندیدای بازرس لازم است.', true);
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
        $myVoteCount = 0;
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
            $myVotes = $vs->fetchAll(PDO::FETCH_COLUMN);
            $myVoteCount = count($myVotes);
            $votedCandidateId = $myVotes[0] ?? null;
            $canVote = $election['status'] === 'open' && $myVoteCount === 0;
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
        $candidateIds = array_values(array_unique(array_map('intval', (array)($_POST['candidate_ids'] ?? []))));
        $auditorId = (int)($_POST['auditor_candidate_id'] ?? 0);
        $election = $this->findElection($eid);
        if (!$election || $election['status'] !== 'open') $this->redirect('portal_election', 'این رأی‌گیری فعال نیست.', true);
        if (count($candidateIds) < 3) $this->redirect('portal_election', 'برای هیئت امنا باید حداقل ۳ کاندیدا انتخاب کنید.', true);
        if ($auditorId <= 0) $this->redirect('portal_election', 'لطفاً یک بازرس انتخاب کنید.', true);
        $allIds = array_values(array_unique(array_merge($candidateIds, [$auditorId])));
        $marks = implode(',', array_fill(0, count($allIds), '?'));
        $st = $this->pdo->prepare("SELECT id, candidate_role FROM election_candidates WHERE id IN ($marks) AND election_id=? AND is_active=1");
        $st->execute([...$allIds, $eid]);
        $valid = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($valid) !== count($allIds)) $this->redirect('portal_election', 'یکی از انتخاب‌ها معتبر نیست.', true);
        $roles = [];
        foreach ($valid as $candidate) $roles[(int)$candidate['id']] = $candidate['candidate_role'];
        foreach ($candidateIds as $candidateId) {
            if (($roles[$candidateId] ?? null) !== 'trustee') $this->redirect('portal_election', 'در بخش هیئت امنا فقط کاندیداهای هیئت امنا را انتخاب کنید.', true);
        }
        if (($roles[$auditorId] ?? null) !== 'auditor') $this->redirect('portal_election', 'انتخاب بازرس معتبر نیست.', true);
        try {
            $this->pdo->beginTransaction();
            $ins = $this->pdo->prepare('INSERT INTO election_votes (election_id, candidate_id, resident_id) VALUES (:eid,:cid,:rid)');
            foreach (array_merge($candidateIds, [$auditorId]) as $candidateId) {
                $ins->execute([':eid' => $eid, ':cid' => $candidateId, ':rid' => $rid]);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ((int)$e->errorInfo[1] === 1062) $this->redirect('portal_election', 'شما قبلاً در این انتخابات رأی داده‌اید.', true);
            throw $e;
        }
        $this->redirect('portal_election', 'رأی شما با موفقیت ثبت شد.');
    }
}
