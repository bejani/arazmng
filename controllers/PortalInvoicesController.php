<?php
// controllers/PortalInvoicesController.php
declare(strict_types=1);

require_once __DIR__ . '/../views/_helpers.php';

final class PortalInvoicesController
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
            $_SESSION['error'] = 'برای مشاهده قبض‌ها لطفاً وارد پورتال شوید.';
            header('Location: index.php?page=portal_login');
            exit;
        }
        return $rid;
    }

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
    private function invSubjectCol(): string
    {
        return $this->pickCol(['subject', 'title', 'description', 'name'], 'subject');
    }
    private function invAmountCol(): string
    {
        return $this->pickCol(['amount', 'total_amount', 'total', 'price'], 'amount');
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

        // واحدهای منتسب
        $uStmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.floor
            FROM units u
            JOIN ownerships o ON o.unit_id=u.id
            WHERE o.resident_id=:rid
              AND (o.end_date IS NULL OR o.end_date >= CURDATE())
            ORDER BY u.name
        ");
        $uStmt->execute([':rid' => $rid]);
        $unitList = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        // فیلتر فرم
        $filter = [
            'unit_id'      => ($_GET['unit_id'] ?? '') !== '' ? (int)$_GET['unit_id'] : null,
            'utility_type' => trim((string)($_GET['utility_type'] ?? '')),
            'period'       => trim((string)($_GET['period'] ?? '')),
            'status'       => trim((string)($_GET['status'] ?? '')), // '', unpaid, partial, paid
        ];

        $conds = [
            // STRICT: فقط utility؛ NULL/خالی دیگر وارد نمی‌شوند
            "TRIM(LOWER(i.kind)) = 'utility'",
            "EXISTS (
                SELECT 1 FROM ownerships oo
                WHERE oo.unit_id = i.unit_id
                  AND oo.resident_id = :rid
                  AND (oo.end_date IS NULL OR oo.end_date >= CURDATE())
            )",
        ];
        $p = [':rid' => $rid];

        if ($filter['unit_id']) {
            $conds[] = "i.unit_id=:uid";
            $p[':uid'] = $filter['unit_id'];
        }
        if ($filter['utility_type'] !== '' && $this->hasColumn('invoices', 'utility_type')) {
            $conds[] = "i.utility_type=:ut";
            $p[':ut'] = $filter['utility_type'];
        }
        if ($filter['period'] !== '' && $periodCol) {
            $conds[] = "i.$periodCol=:per";
            $p[':per'] = $filter['period'];
        }

        $where = 'WHERE ' . implode(' AND ', $conds);

        $sql = "
            SELECT
              i.id, i.unit_id,
              u.name AS unit_name, u.floor,
              " . ($periodCol ? "i.$periodCol" : "NULL") . " AS period,
              i.$subjCol AS subject,
              i.$amtCol  AS invoice_amount,
              " . ($issueCol ? "i.$issueCol" : "NULL") . " AS issue_date,
              " . ($dueCol   ? "i.$dueCol"   : "NULL") . " AS due_date,
              " . ($this->hasColumn('invoices', 'utility_type') ? "i.utility_type" : "NULL") . " AS utility_type,
              COALESCE(pay.paid_amount,0) AS paid_amount
            FROM invoices i
            JOIN units u ON u.id = i.unit_id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS paid_amount
                FROM payments
                GROUP BY invoice_id
            ) pay ON pay.invoice_id = i.id
            $where
            ORDER BY " . ($periodCol ? "i.$periodCol DESC," : "") . ($issueCol ? " i.$issueCol DESC," : "") . " i.id DESC
            LIMIT 1000
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // وضعیت
        $rowsFiltered = [];
        foreach ($rows as $r) {
            $amount  = (float)($r['invoice_amount'] ?? 0);
            $paid    = (float)($r['paid_amount']    ?? 0);
            $balance = max(0, round($amount - $paid, 2));
            $status  = ($balance <= 0.0) ? 'paid' : (($paid > 0.0) ? 'partial' : 'unpaid');

            if ($filter['status'] === '' || $filter['status'] === $status) {
                $r['_status']  = $status;
                $r['_balance'] = $balance;
                $rowsFiltered[] = $r;
            }
        }

        // render
        ob_start();
        include __DIR__ . '/../views/portal/invoices.php';
        $content = ob_get_clean();

        $page_title = 'قبض‌های من';
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
