<?php
// controllers/UtilityController.php
declare(strict_types=1);

final class UtilityController
{
    private const KIND_VALUE = 'utility'; // مطابق ENUM('dues','petty','utility')

    public function __construct(private \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /* ---------- schema helpers ---------- */
    private function hasTable(string $t): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
        $st->execute([':t' => $t]);
        return (bool)$st->fetchColumn();
    }
    private function hasColumn(string $t, string $c): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
        $st->execute([':t' => $t, ':c' => $c]);
        return (bool)$st->fetchColumn();
    }
    private function firstExistingCol(array $cands, string $table): ?string
    {
        foreach ($cands as $c) if ($this->hasColumn($table, $c)) return $c;
        return null;
    }
    private function invAmountColRequired(): string
    {
        $c = $this->firstExistingCol(['amount', 'total_amount', 'total', 'price', 'final_amount'], 'invoices');
        if (!$c) throw new \RuntimeException("ستون مبلغ در invoices یافت نشد (amount/total_amount/...).");
        return $c;
    }
    private function invIssueCol(): ?string
    {
        foreach (['issue_date', 'created_at', 'created', 'date'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function invSubjectColNullable(): ?string
    {
        return $this->firstExistingCol(['subject', 'title', 'description', 'name'], 'invoices');
    }
    private function columnType(string $table, string $col): ?string
    {
        $st = $this->pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
        $st->execute([':t' => $table, ':c' => $col]);
        $x = $st->fetchColumn();
        return $x ?: null;
    }
    private function kindAccepts(string $value = self::KIND_VALUE): bool
    {
        if (!$this->hasColumn('invoices', 'kind')) return false;
        $type = $this->columnType('invoices', 'kind');
        if (!$type) return false;
        if (stripos($type, 'enum(') !== false) return stripos($type, "'{$value}'") !== false;
        if (preg_match('/char\((\d+)\)/i', $type, $m)) return strlen($value) <= (int)$m[1];
        return true;
    }

    /* ---------- مفرد/جمع: نام جداول و FK ---------- */
    private function runsTable(): ?string
    {
        if ($this->hasTable('utility_runs'))   return 'utility_runs';
        if ($this->hasTable('utilities_runs')) return 'utilities_runs';
        if ($this->hasTable('allocation_runs')) return 'allocation_runs'; // اگر با نام عمومی ساختی
        return null;
    }
    private function allocTable(): ?string
    {
        if ($this->hasTable('utility_allocations'))   return 'utility_allocations';
        if ($this->hasTable('utilities_allocations')) return 'utilities_allocations';
        if ($this->hasTable('allocations'))           return 'allocations'; // fallback
        return null;
    }
    private function invRunFkCol(): ?string
    {
        if ($this->hasColumn('invoices', 'utility_run_id'))     return 'utility_run_id';
        if ($this->hasColumn('invoices', 'utilities_run_id'))   return 'utilities_run_id';
        if ($this->hasColumn('invoices', 'allocation_run_id'))  return 'allocation_run_id'; // 👈 مهم
        return null;
    }

    /* ---------- payments helpers ---------- */
    private function paymentTableName(): ?string
    {
        if ($this->hasTable('payment'))  return 'payment';
        if ($this->hasTable('payments')) return 'payments';
        return null;
    }
    private function paymentsSchema(): ?array
    {
        $tbl = $this->paymentTableName();
        if (!$tbl) return null;
        $invoiceCol = $this->firstExistingCol(['invoice_id', 'inv_id'], $tbl);
        $amountCol  = $this->firstExistingCol(['amount', 'paid_amount', 'value'], $tbl);
        if (!$invoiceCol || !$amountCol) return null;
        return [
            'table'    => $tbl,
            'invoice'  => $invoiceCol,
            'amount'   => $amountCol,
            'pay_date' => $this->firstExistingCol(['pay_date', 'paid_at', 'date', 'created_at'], $tbl),
            'method'   => $this->firstExistingCol(['method', 'pay_method', 'gateway'], $tbl),
            'ref'      => $this->firstExistingCol(['ref', 'reference', 'trx'], $tbl),
            'note'     => $this->firstExistingCol(['note', 'description', 'memo'], $tbl),
            'unit_id'  => $this->firstExistingCol(['unit_id'], $tbl),
        ];
    }
    private function paymentMethods(): array
    {
        $tbl = $this->paymentTableName();
        if (!$tbl || !$this->hasColumn($tbl, 'method')) return ['cash', 'card', 'bank', 'online'];
        $type = $this->columnType($tbl, 'method');
        if ($type && preg_match_all("/'([^']+)'/", $type, $m)) return $m[1];
        return ['cash', 'card', 'bank', 'online'];
    }

    /* ---------- owner join: units + ownerships + residents ---------- */
    private function ownerJoinParts(string $invAlias = 'i'): array
    {
        $joins = '';
        $sel = "NULL AS owner_name";
        $filterExpr = null;

        $hasOwnerships = $this->hasTable('ownerships') && $this->hasColumn('ownerships', 'unit_id') && $this->hasColumn('ownerships', 'resident_id');
        $hasResidents  = $this->hasTable('residents')  && $this->hasColumn('residents', 'id')      && $this->hasColumn('residents', 'full_name');

        if ($hasOwnerships && $hasResidents) {
            $joins .= " LEFT JOIN (
                          SELECT unit_id, MIN(resident_id) AS resident_id
                          FROM ownerships
                          GROUP BY unit_id
                        ) ow ON ow.unit_id = {$invAlias}.unit_id
                        LEFT JOIN residents r ON r.id = ow.resident_id ";
            $sel = "r.full_name AS owner_name";
            $filterExpr = "r.full_name";
            return [$joins, $sel, $filterExpr];
        }

        if ($this->hasTable('units')) {
            $joins .= " LEFT JOIN units u ON u.id = {$invAlias}.unit_id ";
            foreach (['owner_name', 'owner', 'proprietor', 'landlord', 'full_name', 'fullname', 'contact_name', 'resident_name'] as $c) {
                if ($this->hasColumn('units', $c)) {
                    $sel = "u.$c AS owner_name";
                    $filterExpr = "u.$c";
                    return [$joins, $sel, $filterExpr];
                }
            }
            if ($this->hasColumn('units', 'first_name') && $this->hasColumn('units', 'last_name')) {
                $sel = "CONCAT(u.first_name,' ',u.last_name) AS owner_name";
                $filterExpr = "CONCAT(u.first_name,' ',u.last_name)";
                return [$joins, $sel, $filterExpr];
            }
        }
        return [$joins, $sel, $filterExpr];
    }

    /* ---------- CSRF ---------- */
    private function ensureCsrf(): string
    {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
    private function validCsrf(string $t): bool
    {
        return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
    }

    /* ---------- RENDER: پشتیبانی از views/utility و views/utilities ---------- */
    private function render(string $view, array $vars = [], ?string $pageTitle = null): void
    {
        extract($vars, EXTR_SKIP);
        $page_title = $pageTitle ?? 'قبض‌ها';

        $cands = [
            $view,
            __DIR__ . '/../views/utility/' . basename($view),
            __DIR__ . '/../views/utilities/' . basename($view),
        ];
        $found = null;
        foreach ($cands as $p) if (is_file($p)) {
            $found = $p;
            break;
        }
        if (!$found) {
            throw new \RuntimeException("View not found. Tried:\n - " . implode("\n - ", $cands));
        }

        ob_start();
        include $found;
        $content = ob_get_clean();

        $layouts = [
            __DIR__ . '/../views/layout_admin.php',
            __DIR__ . '/../views/layout_portal.php',
            __DIR__ . '/../views/layout.php',
            __DIR__ . '/../layout.php',
        ];
        foreach ($layouts as $lp) {
            if (file_exists($lp)) {
                include $lp;
                return;
            }
        }
        echo $content;
    }

    /* ===================== 1) لیست راندها ===================== */
    public function index(): void
    {
        $amtCol  = $this->firstExistingCol(['amount', 'total_amount', 'total', 'price', 'final_amount'], 'invoices') ?? 'amount';
        $hasKind = $this->hasColumn('invoices', 'kind');
        $kindAnd = ($hasKind && $this->kindAccepts(self::KIND_VALUE)) ? "AND i.kind='" . self::KIND_VALUE . "'" : "";

        $runsTbl = $this->runsTable();
        $allocTbl = $this->allocTable();
        $fkCol   = $this->invRunFkCol();

        $ps   = $this->paymentsSchema();
        $pTbl = $ps['table']   ?? null;
        $pInv = $ps['invoice'] ?? null;
        $pAmt = $ps['amount']  ?? null;

        $paidSub = ($pTbl && $pInv && $pAmt)
            ? "LEFT JOIN (SELECT $pInv AS invoice_id, COALESCE(SUM($pAmt),0) AS paid_amount FROM $pTbl GROUP BY $pInv) p ON p.invoice_id = i.id"
            : "";

        $selTitle  = ($runsTbl && $this->hasColumn($runsTbl, 'title'))         ? "MAX(ur.title) AS title"         : "NULL AS title";
        $selPeriod = ($runsTbl && $this->hasColumn($runsTbl, 'period'))        ? "MAX(ur.period) AS period"       : "NULL AS period";
        $selTarget = ($runsTbl && $this->hasColumn($runsTbl, 'target_amount')) ? "MAX(ur.target_amount) AS target_amount" : "0 AS target_amount";

        if ($runsTbl && $fkCol) {
            // بهترین حالت: فاکتورها مستقیماً به راند وصل‌اند
            $sql = "
            SELECT ur.id, $selTitle, $selPeriod, $selTarget,
                   COUNT(i.id) AS invoices_count,
                   COALESCE(SUM(i.$amtCol),0)                 AS invoiced_total,
                   COALESCE(SUM(COALESCE(p.paid_amount,0)),0) AS paid_total
            FROM $runsTbl ur
            LEFT JOIN invoices i ON i.$fkCol = ur.id $kindAnd
            $paidSub
            GROUP BY ur.id
            ORDER BY ur.id DESC
        ";
        } elseif ($runsTbl && $allocTbl) {
            // فقط اگر ستونی برای اتصال تخصیص→فاکتور داشته باشیم
            $allocInvCol = $this->firstExistingCol(['invoice_id', 'inv_id', 'invoice'], $allocTbl);
            if ($allocInvCol) {
                $sql = "
                SELECT ur.id, $selTitle, $selPeriod, $selTarget,
                       COUNT(i.id) AS invoices_count,
                       COALESCE(SUM(i.$amtCol),0)                 AS invoiced_total,
                       COALESCE(SUM(COALESCE(p.paid_amount,0)),0) AS paid_total
                FROM $runsTbl ur
                LEFT JOIN $allocTbl ua ON ua.run_id = ur.id
                LEFT JOIN invoices i ON i.id = ua.$allocInvCol $kindAnd
                $paidSub
                GROUP BY ur.id
                ORDER BY ur.id DESC
            ";
            } else {
                // ستون اتصال پیدا نشد → صفحه بدون ارور ولی بدون آمار
                if (empty($_SESSION['error'])) {
                    $_SESSION['error'] = "هشدار: در جدول $allocTbl ستونی مثل invoice_id برای اتصال به فاکتور پیدا نشد؛ آمارها صفر نمایش داده شدند.";
                }
                $sql = "
                SELECT ur.id, $selTitle, $selPeriod, $selTarget,
                       0 AS invoices_count, 0 AS invoiced_total, 0 AS paid_total
                FROM $runsTbl ur
                ORDER BY ur.id DESC
            ";
            }
        } elseif ($runsTbl) {
            $sql = "
            SELECT ur.id, $selTitle, $selPeriod, $selTarget,
                   0 AS invoices_count, 0 AS invoiced_total, 0 AS paid_total
            FROM $runsTbl ur
            ORDER BY ur.id DESC
        ";
        } else {
            $sql = "SELECT 0 AS id, NULL AS title, NULL AS period, 0 AS target_amount,
                       0 AS invoices_count, 0 AS invoiced_total, 0 AS paid_total
                LIMIT 0";
        }

        try {
            $runs = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'خطا در واکشی راند قبض‌ها: ' . $e->getMessage();
            $runs = [];
        }

        $this->render(__DIR__ . '/../views/utility/runs.php', [
            'runs'    => $runs,
            'methods' => $this->paymentMethods(),
            'csrf'    => $this->ensureCsrf(),
        ], 'راندهای قبض');
    }



    /* ===================== 2) جزئیات راند + فیلتر مالک ===================== */
    public function run(): void
    {
        $runId = (int)($_GET['run_id'] ?? 0);
        if ($runId <= 0) {
            header('Location: index.php?page=utility');
            exit;
        }

        $ownerQuery = trim((string)($_GET['owner'] ?? ''));

        // ستون‌های پویا از invoices
        $amtCol   = $this->invAmountColRequired();
        $subjCol  = $this->invSubjectColNullable();
        $issueCol = $this->invIssueCol();
        $hasKind  = $this->hasColumn('invoices', 'kind');
        $kindAnd  = ($hasKind && $this->kindAccepts(self::KIND_VALUE)) ? "AND i.kind='" . self::KIND_VALUE . "'" : "";

        // پرداخت‌ها (همیشه p داشته باشیم تا SELECT خطا ندهد)
        $ps   = $this->paymentsSchema();
        $pTbl = $ps['table']   ?? null;
        $pInv = $ps['invoice'] ?? null;
        $pAmt = $ps['amount']  ?? null;
        $paidSub = ($pTbl && $pInv && $pAmt)
            ? "LEFT JOIN (SELECT $pInv AS invoice_id, COALESCE(SUM($pAmt),0) AS paid_amount FROM $pTbl GROUP BY $pInv) p ON p.invoice_id = i.id"
            : "LEFT JOIN (SELECT 0 AS invoice_id, 0 AS paid_amount) p ON p.invoice_id = i.id";

        // اتصال نام مالک + عبارت فیلتر
        [$ownerJoins, $ownerSel, $ownerFilterExpr] = $this->ownerJoinParts('i');
        $ownerFilterSql = '';
        $params = [':rid' => $runId];
        if ($ownerQuery !== '' && $ownerFilterExpr) {
            $ownerFilterSql = " AND {$ownerFilterExpr} LIKE :owner ";
            $params[':owner'] = '%' . $ownerQuery . '%';
        }

        // نام جداول/کلیدها (مفرد/جمع)
        $runsTbl = $this->runsTable();     // utility_runs | utilities_runs | allocation_runs
        $allocTbl = $this->allocTable();    // utility_allocations | utilities_allocations | allocations
        $fkCol   = $this->invRunFkCol();   // invoices.utility_run_id | invoices.utilities_run_id | invoices.allocation_run_id

        try {
            if ($runsTbl && $fkCol) {
                // بهترین حالت: فاکتور مستقیماً به راند وصل است
                $sql = "
                SELECT 
                  i.id, i.unit_id, i.$amtCol AS amount,
                  " . ($subjCol ? "i.$subjCol" : "NULL") . " AS subject,
                  " . ($issueCol ? "i.$issueCol" : "NULL") . " AS issue_date,
                  $ownerSel,
                  COALESCE(p.paid_amount,0) AS paid_amount
                FROM invoices i
                $paidSub
                $ownerJoins
                WHERE i.$fkCol = :rid $kindAnd
                  $ownerFilterSql
                ORDER BY i.id DESC
            ";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
            } elseif ($runsTbl && $allocTbl) {
                // حالت اتصال از جدول تخصیص — فقط اگر ستون اتصال به فاکتور وجود داشته باشد
                $allocInvCol = $this->firstExistingCol(['invoice_id', 'inv_id', 'invoice'], $allocTbl);
                if (!$allocInvCol) {
                    $fkSample = $fkCol ?: 'utility_run_id';
                    $_SESSION['error'] = 'برای مشاهدهٔ جزئیات، یا ستون ' . $fkSample . ' را در جدول invoices اضافه کن، یا ستونی مثل invoice_id را به جدول ' . $allocTbl . ' اضافه کن.';
                    header('Location: index.php?page=utility');
                    exit;
                }
                $sql = "
                SELECT 
                  i.id, i.unit_id, i.$amtCol AS amount,
                  " . ($subjCol ? "i.$subjCol" : "NULL") . " AS subject,
                  " . ($issueCol ? "i.$issueCol" : "NULL") . " AS issue_date,
                  $ownerSel,
                  COALESCE(p.paid_amount,0) AS paid_amount
                FROM {$allocTbl} ua
                JOIN invoices i ON i.id = ua.$allocInvCol $kindAnd
                $paidSub
                $ownerJoins
                WHERE ua.run_id = :rid
                  $ownerFilterSql
                ORDER BY i.id DESC
            ";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
            } else {
                $_SESSION['error'] = 'جدول راند قبض یا نگاشت آن در اسکیمای شما یافت نشد.';
                header('Location: index.php?page=utility');
                exit;
            }

            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'خطا در واکشی فاکتورهای این راند: ' . $e->getMessage();
            header('Location: index.php?page=utility');
            exit;
        }

        // اطلاعات راند (ستون‌ها به‌صورت پویا)
        $run = ['id' => $runId, 'title' => null, 'period' => null, 'target_amount' => null];
        if ($runsTbl) {
            $selTitle  = $this->hasColumn($runsTbl, 'title')         ? 'title'         : "NULL AS title";
            $selPeriod = $this->hasColumn($runsTbl, 'period')        ? 'period'        : "NULL AS period";
            $selTarget = $this->hasColumn($runsTbl, 'target_amount') ? 'target_amount' : "NULL AS target_amount";
            try {
                $stRun = $this->pdo->prepare("SELECT id, $selTitle, $selPeriod, $selTarget FROM {$runsTbl} WHERE id=:id");
                $stRun->execute([':id' => $runId]);
                $row = $stRun->fetch(\PDO::FETCH_ASSOC);
                if ($row) $run = $row;
            } catch (\Throwable $e) { /* نادیده بگیر */
            }
        }

        // رندر
        $this->render(__DIR__ . '/../views/utility/run.php', [
            'run'     => $run,
            'rows'    => $rows,
            'methods' => $this->paymentMethods(),
            'csrf'    => $this->ensureCsrf(),
            'owner_q' => $ownerQuery,
        ], 'جزئیات راند قبض');
    }


    /* ===================== 3) ثبت پرداخت ===================== */
    public function pay(): void
    {
        $token = (string)($_POST['_csrf'] ?? '');
        if (!$this->validCsrf($token)) {
            http_response_code(400);
            die('Invalid CSRF token');
        }
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim((string)($_POST['method'] ?? 'cash'));
        $ref = trim((string)($_POST['ref'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $runIdBack = (int)($_POST['run_id'] ?? 0);
        if ($invoiceId <= 0 || $amount <= 0) {
            $_SESSION['error'] = 'اطلاعات پرداخت نامعتبر است.';
            $this->redirectBack($runIdBack);
        }

        $ps = $this->paymentsSchema();
        if (!$ps) {
            $_SESSION['error'] = 'جدول payment/payments یا ستون‌های لازم وجود ندارد.';
            $this->redirectBack($runIdBack);
        }

        $amtCol = $this->invAmountColRequired();
        $stInv = $this->pdo->prepare("SELECT id,unit_id,$amtCol AS amount," . ($this->hasColumn('invoices', 'kind') ? 'kind' : 'NULL AS kind') . " FROM invoices WHERE id=:id");
        $stInv->execute([':id' => $invoiceId]);
        $inv = $stInv->fetch(\PDO::FETCH_ASSOC);
        if (!$inv) {
            $_SESSION['error'] = 'فاکتور پیدا نشد.';
            $this->redirectBack($runIdBack);
        }
        if (isset($inv['kind']) && $inv['kind'] !== self::KIND_VALUE) {
            $_SESSION['error'] = 'این فاکتور از نوع قبض (utility) نیست.';
            $this->redirectBack($runIdBack);
        }

        $stPaid = $this->pdo->prepare("SELECT COALESCE(SUM({$ps['amount']}),0) FROM {$ps['table']} WHERE {$ps['invoice']}=:iid");
        $stPaid->execute([':iid' => $invoiceId]);
        $paidBefore = (float)$stPaid->fetchColumn();
        if ($amount + $paidBefore > (float)$inv['amount']) {
            $_SESSION['error'] = 'مبلغ پرداخت بیش از مانده فاکتور است.';
            $this->redirectBack($runIdBack);
        }

        $cols = [$ps['invoice'], $ps['amount']];
        $vals = [':iid', ':amt'];
        $params = [':iid' => $invoiceId, ':amt' => $amount];
        if (!empty($ps['pay_date'])) {
            $cols[] = $ps['pay_date'];
            $vals[] = 'NOW()';
        }
        if (!empty($ps['unit_id'])) {
            $cols[] = $ps['unit_id'];
            $vals[] = ':uid';
            $params[':uid'] = (int)$inv['unit_id'];
        }
        if (!empty($ps['method'])) {
            $cols[] = $ps['method'];
            $vals[] = ':m';
            $params[':m'] = ($method !== '' ? $method : null);
        }
        if (!empty($ps['ref'])) {
            $cols[] = $ps['ref'];
            $vals[] = ':r';
            $params[':r'] = ($ref !== '' ? $ref : null);
        }
        if (!empty($ps['note'])) {
            $cols[] = $ps['note'];
            $vals[] = ':n';
            $params[':n'] = ($note !== '' ? $note : null);
        }

        $sql = "INSERT INTO {$ps['table']} (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        try {
            $this->pdo->prepare($sql)->execute($params);
            $_SESSION['ok'] = 'پرداخت ثبت شد.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'خطا در ثبت پرداخت: ' . $e->getMessage();
        }
        $this->redirectBack($runIdBack);
    }

    private function redirectBack(int $runIdBack): void
    {
        header('Location: index.php?page=' . ($runIdBack > 0 ? 'utility_run&run_id=' . $runIdBack : 'utility'));
        exit;
    }
}
