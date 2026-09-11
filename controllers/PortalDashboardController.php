<?php
// controllers/PortalDashboardController.php
declare(strict_types=1);

require_once __DIR__ . '/../views/_helpers.php';

final class PortalDashboardController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    private function guard(): int
    {
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        if ($rid <= 0) {
            $_SESSION['error'] = 'برای مشاهده داشبورد، ابتدا وارد پورتال شوید.';
            header('Location: index.php?page=portal_login');
            exit;
        }
        return $rid;
    }

    /* ---- schema helpers ---- */
    private function hasColumn(string $t, string $c): bool
    {
        $st = $this->pdo->prepare("
            SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1
        ");
        $st->execute([':t' => $t, ':c' => $c]);
        return (bool)$st->fetchColumn();
    }
    private function pickCol(array $cands, string $fallback): string
    {
        foreach ($cands as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return $fallback;
    }
    private function invAmountCol(): string
    {
        // شما گفتید ستون amount دارید؛ این اولویت را نگه می‌داریم
        return $this->pickCol(['amount', 'total_amount', 'total', 'price'], 'amount');
    }
    private function invSubjectCol(): string
    {
        return $this->pickCol(['subject', 'title', 'description', 'name'], 'subject');
    }
    private function invIssueCol(): ?string
    {
        foreach (['issue_date', 'created_at', 'created', 'date'] as $c)
            if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function invDueCol(): ?string
    {
        foreach (['due_date', 'deadline', 'due'] as $c)
            if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function invPeriodCol(): ?string
    {
        return $this->hasColumn('invoices', 'period') ? 'period' : null;
    }

    public function index(): void
    {
        $rid       = $this->guard();
        $amtCol    = $this->invAmountCol();
        $subjCol   = $this->invSubjectCol();
        $issueCol  = $this->invIssueCol();
        $dueCol    = $this->invDueCol();
        $periodCol = $this->invPeriodCol();

        /* --------- لیست واحدهای منتسب به این ساکن (برای لیبل‌ها و جدول بالا) --------- */
        $uStmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.floor
            FROM units u
            JOIN ownerships o ON o.unit_id=u.id
            WHERE o.resident_id=:rid
              AND (o.end_date IS NULL OR o.end_date >= CURDATE())
            ORDER BY u.name
        ");
        $uStmt->execute([':rid' => $rid]);
        $units = $uStmt->fetchAll(PDO::FETCH_ASSOC);
        $unitIndex = [];
        foreach ($units as $u) {
            $unitIndex[(int)$u['id']] = ['name' => $u['name'] ?? '', 'floor' => isset($u['floor']) ? (int)$u['floor'] : null];
        }

        /* --------- خلاصهٔ بدهی/صورتحساب/پرداخت به تفکیک واحد (فقط dues) --------- */
        $sumSql = "
            SELECT
              i.unit_id,
              SUM(i.$amtCol)                    AS billed,
              COALESCE(SUM(pay.pay_amount), 0)  AS paid
            FROM invoices i
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS pay_amount
                FROM payments
                GROUP BY invoice_id
            ) pay ON pay.invoice_id = i.id
            WHERE TRIM(LOWER(i.kind)) = 'dues'
              AND EXISTS (
                    SELECT 1 FROM ownerships o
                    WHERE o.unit_id = i.unit_id
                      AND o.resident_id = :rid
                      AND (o.end_date IS NULL OR o.end_date >= CURDATE())
              )
            GROUP BY i.unit_id
            ORDER BY i.unit_id
        ";
        $stSum = $this->pdo->prepare($sumSql);
        $stSum->execute([':rid' => $rid]);
        $sumRows = $stSum->fetchAll(PDO::FETCH_ASSOC);

        $summary = [];
        foreach ($sumRows as $r) {
            $uid   = (int)$r['unit_id'];
            $billed = (float)($r['billed'] ?? 0);
            $paid  = (float)($r['paid']   ?? 0);
            $summary[] = [
                'unit_id'   => $uid,
                'unit_name' => $unitIndex[$uid]['name']  ?? '',
                'floor'     => $unitIndex[$uid]['floor'] ?? null,
                'billed'    => $billed,
                'paid'      => $paid,
                'due'       => max(0, round($billed - $paid, 2)),
            ];
        }

        /* --------- فاکتورهای اخیر (فقط dues) --------- */
        $invSql = "
            SELECT
              i.id, i.unit_id,
              u.name AS unit_name, u.floor,
              i.$subjCol  AS subject,
              i.$amtCol   AS invoice_amount,
              " . ($periodCol ? "i.$periodCol" : "NULL") . " AS period,
              " . ($issueCol  ? "i.$issueCol"  : "NULL") . " AS issue_date,
              " . ($dueCol    ? "i.$dueCol"    : "NULL") . " AS due_date,
              COALESCE(pay.paid_amount,0) AS paid_amount
            FROM invoices i
            JOIN units u ON u.id = i.unit_id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS paid_amount
                FROM payments
                GROUP BY invoice_id
            ) pay ON pay.invoice_id = i.id
            WHERE TRIM(LOWER(i.kind)) = 'dues'
              AND EXISTS (
                    SELECT 1 FROM ownerships o
                    WHERE o.unit_id = i.unit_id
                      AND o.resident_id = :rid
                      AND (o.end_date IS NULL OR o.end_date >= CURDATE())
              )
            ORDER BY " . ($issueCol ? "i.$issueCol DESC," : "") . " i.id DESC
            LIMIT 50
        ";
        $stInv = $this->pdo->prepare($invSql);
        $stInv->execute([':rid' => $rid]);
        $invoices = $stInv->fetchAll(PDO::FETCH_ASSOC);

        /* --------- render --------- */
        ob_start();
        include __DIR__ . '/../views/portal/dashboard.php';
        $content = ob_get_clean();

        $page_title = 'داشبورد ساکن';
        $layouts = [
            __DIR__ . '/../views/layout_portal.php',
            __DIR__ . '/../views/layout.php',
            __DIR__ . '/../layout.php',
        ];
        foreach ($layouts as $lp) {
            if (is_file($lp)) {
                include $lp;
                return;
            }
        }
        echo $content;
    }
}
