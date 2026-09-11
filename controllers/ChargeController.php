<?php
// controllers/ChargeController.php
declare(strict_types=1);

require_once __DIR__ . '/../views/_helpers.php';

final class ChargeController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function index(): void
    {
        // سازگاری با روتر
        $this->new();
    }

    /* ---------- Helpers: columns ---------- */
    private function hasColumn(string $table, string $col): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                                   WHERE TABLE_SCHEMA = DATABASE()
                                     AND TABLE_NAME = :t
                                     AND COLUMN_NAME = :c
                                   LIMIT 1");
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    }
    private function invoiceSubjectColumn(): string
    {
        foreach (['subject', 'title', 'description', 'name'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return 'subject';
    }
    private function invoiceAmountColumn(): string
    {
        foreach (['total_amount', 'amount', 'total', 'price'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return 'amount';
    }
    private function invoiceIssueCol(): ?string
    {
        foreach (['issue_date', 'created_at', 'created', 'date'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function invoiceDueCol(): ?string
    {
        foreach (['due_date', 'deadline', 'due'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function invoicePeriodCol(): ?string
    {
        foreach (['period'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }

    /* ---------- New (Form) ---------- */
    public function new(): void
    {
        $units = $this->pdo->query("SELECT id,name,floor FROM units WHERE is_active=1 ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/../views/charges/new.php';
    }

    /* ---------- New for single unit (optional) ---------- */
    public function newSingle(): void
    {
        // اگر فرم اختصاصی نداری، همین فرم عمومی را نشان بده
        $this->new();
    }

    /* ---------- Generate (POST, all active units) ---------- */
    public function generate(): void
    {
        // CSRF
        if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            die('Invalid CSRF token');
        }

        // ورودی‌ها را scalarize کنیم تا اگر آرایه آمد، تک‌مقداری شوند
        $scalar = function ($v) {
            return is_array($v) ? reset($v) : $v;
        };

        $amountRaw = (string)$scalar($_POST['amount'] ?? '0');
        $amountRaw = str_replace([',', ' '], '', $amountRaw);
        $amount    = is_numeric($amountRaw) ? (float)$amountRaw : 0.0;

        $jyear   = (int) en_num((string)$scalar($_POST['jyear'] ?? ''));
        $jmonth  = (int) en_num((string)$scalar($_POST['jmonth'] ?? ''));
        $subject = trim((string)$scalar($_POST['subject'] ?? ''));

        $issue   = normalize_date_input((string)$scalar($_POST['issue_date'] ?? ''));
        $due     = normalize_date_input((string)$scalar($_POST['due_date'] ?? ''));

        if ($amount <= 0 || $jyear < 1300 || $jmonth < 1 || $jmonth > 12) {
            die('ورودی نامعتبر است.');
        }

        $faMonths = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        if ($subject === '') {
            $subject = 'شارژ ماه ' . ($faMonths[$jmonth] ?? (string)$jmonth) . ' ' . $jyear;
        }
        $period = sprintf('%04d-%02d', $jyear, $jmonth);

        // ستون‌ها
        $colSub    = $this->invoiceSubjectColumn();
        $colAmt    = $this->invoiceAmountColumn();
        $colIssue  = $this->invoiceIssueCol();
        $colDue    = $this->invoiceDueCol();
        $colPeriod = $this->invoicePeriodCol();

        $cols = ['unit_id', $colSub, $colAmt];
        $vals = [':unit',   ':subj', ':amt'];
        $paramsBase = [':subj' => $subject, ':amt' => $amount];

        if ($colIssue && $issue) {
            $cols[] = $colIssue;
            $vals[] = ':issue';
            $paramsBase[':issue'] = $issue;
        }
        if ($colDue   && $due) {
            $cols[] = $colDue;
            $vals[] = ':due';
            $paramsBase[':due']  = $due;
        }
        if ($colPeriod) {
            $cols[] = $colPeriod;
            $vals[] = ':period';
            $paramsBase[':period'] = $period;
        }

        $sql = "INSERT INTO invoices (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        $ins = $this->pdo->prepare($sql);

        // چکِ تکراری بودن
        if ($colPeriod) {
            $checkSql = "SELECT 1 FROM invoices WHERE unit_id=:unit AND {$colPeriod}=:period LIMIT 1";
        } elseif ($colIssue && $issue) {
            $checkSql = "SELECT 1 FROM invoices WHERE unit_id=:unit AND {$colSub}=:subj AND {$colIssue}=:issue LIMIT 1";
        } else {
            $checkSql = "SELECT 1 FROM invoices WHERE unit_id=:unit AND {$colSub}=:subj LIMIT 1";
        }
        $chk = $this->pdo->prepare($checkSql);

        // همهٔ واحدهای فعال
        $units = $this->pdo->query("SELECT id FROM units WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);

        $this->pdo->beginTransaction();
        try {
            $created = 0;
            $skipped = 0;

            foreach ($units as $u) {
                $params = $paramsBase;
                $params[':unit'] = (int)$u['id'];

                // check duplicate
                $chkParams = [':unit' => $params[':unit']];
                if (strpos($checkSql, ':period') !== false) $chkParams[':period'] = $paramsBase[':period'] ?? null;
                if (strpos($checkSql, ':subj')   !== false) $chkParams[':subj']   = $paramsBase[':subj'];
                if (strpos($checkSql, ':issue')  !== false) $chkParams[':issue']  = $paramsBase[':issue'] ?? null;

                $chk->execute($chkParams);
                if ($chk->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $ins->execute($params);
                $created++;
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            die('خطا در ساخت فاکتورها: ' . $e->getMessage());
        }

        $_SESSION['ok'] = "فاکتورهای شارژ ماهانه با موفقیت ایجاد شد. ساخته‌شده: {$created} | تکراری‌: {$skipped}";
        header('Location: index.php?page=invoices');
        exit;
    }

    /* ---------- Generate single (POST, one unit) ---------- */
    public function generateSingle(): void
    {
        // CSRF
        if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            die('Invalid CSRF token');
        }

        $scalar = function ($v) {
            return is_array($v) ? reset($v) : $v;
        };

        $unitId = (int)$scalar($_POST['unit_id'] ?? 0);
        $amountRaw = (string)$scalar($_POST['amount'] ?? '0');
        $amountRaw = str_replace([',', ' '], '', $amountRaw);
        $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0.0;

        $jyear  = (int) en_num((string)$scalar($_POST['jyear'] ?? ''));
        $jmonth = (int) en_num((string)$scalar($_POST['jmonth'] ?? ''));
        $subject = trim((string)$scalar($_POST['subject'] ?? ''));

        $issue  = normalize_date_input((string)$scalar($_POST['issue_date'] ?? ''));
        $due    = normalize_date_input((string)$scalar($_POST['due_date'] ?? ''));

        if ($unitId <= 0 || $amount <= 0 || $jyear < 1300 || $jmonth < 1 || $jmonth > 12) {
            $_SESSION['error'] = 'ورودی نامعتبر است.';
            header('Location: index.php?page=charge_new');
            exit;
        }

        $faMonths = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        if ($subject === '') $subject = 'شارژ ماه ' . ($faMonths[$jmonth] ?? (string)$jmonth) . ' ' . $jyear;
        $period = sprintf('%04d-%02d', $jyear, $jmonth);

        $colSub    = $this->invoiceSubjectColumn();
        $colAmt    = $this->invoiceAmountColumn();
        $colIssue  = $this->invoiceIssueCol();
        $colDue    = $this->invoiceDueCol();
        $colPeriod = $this->invoicePeriodCol();

        $cols = ['unit_id', $colSub, $colAmt];
        $vals = [':unit',   ':subj', ':amt'];
        $params = [':unit' => $unitId, ':subj' => $subject, ':amt' => $amount];

        if ($colIssue && $issue) {
            $cols[] = $colIssue;
            $vals[] = ':issue';
            $params[':issue'] = $issue;
        }
        if ($colDue   && $due) {
            $cols[] = $colDue;
            $vals[] = ':due';
            $params[':due'] = $due;
        }
        if ($colPeriod) {
            $cols[] = $colPeriod;
            $vals[] = ':period';
            $params[':period'] = $period;
        }

        // duplicate check for single
        if ($colPeriod) {
            $checkSql = "SELECT 1 FROM invoices WHERE unit_id=:unit AND {$colPeriod}=:period LIMIT 1";
            $chkParams = [':unit' => $unitId, ':period' => $period];
        } elseif ($colIssue && $issue) {
            $checkSql = "SELECT 1 FROM invoices WHERE unit_id=:unit AND {$colSub}=:subj AND {$colIssue}=:issue LIMIT 1";
            $chkParams = [':unit' => $unitId, ':subj' => $subject, ':issue' => $issue];
        } else {
            $checkSql = "SELECT 1 FROM invoices WHERE unit_id=:unit AND {$colSub}=:subj LIMIT 1";
            $chkParams = [':unit' => $unitId, ':subj' => $subject];
        }
        $chk = $this->pdo->prepare($checkSql);
        $chk->execute($chkParams);
        if ($chk->fetchColumn()) {
            $_SESSION['error'] = 'برای این واحد در این دوره قبلاً فاکتور ثبت شده است.';
            header('Location: index.php?page=charge_new');
            exit;
        }

        $sql = "INSERT INTO invoices (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        $ins = $this->pdo->prepare($sql);
        try {
            $ins->execute($params);
            $_SESSION['ok'] = 'فاکتور واحد انتخاب‌شده ثبت شد.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'خطا در ثبت فاکتور: ' . $e->getMessage();
        }
        header('Location: index.php?page=invoices');
        exit;
    }

    /* ---------- پرداخت‌ها (میان‌بُر) ---------- */
    public function collect(): void
    {
        header('Location: index.php?page=payment_new');
        exit;
    }

    public function quickPay(): void
    {
        $_SESSION['error'] = 'پرداخت تجمیعی فعلاً از طریق «پرداخت جدید» انجام می‌شود.';
        header('Location: index.php?page=payment_new');
        exit;
    }
}