<?php
// controllers/PettyCashController.php
declare(strict_types=1);

final class PettyCashController
{
    private const KIND_VALUE = 'petty'; // با ENUM('dues','petty','utility') سازگار

    public function __construct(private \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /* ---------- Schema helpers ---------- */
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
        if (!$c) throw new \RuntimeException("ستون مبلغ فاکتور (amount/total_amount/...) در جدول invoices یافت نشد.");
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
        $st = $this->pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $table, ':c' => $col]);
        $x = $st->fetchColumn();
        return $x ? (string)$x : null;
    }
    private function kindAccepts(string $value = self::KIND_VALUE): bool
    {
        if (!$this->hasColumn('invoices', 'kind')) return false;
        $type = $this->columnType('invoices', 'kind');
        if (!$type) return false;
        if (stripos($type, 'enum(') !== false) return stripos($type, "'$value'") !== false;
        if (preg_match('/char\((\d+)\)/i', $type, $m)) return strlen($value) <= (int)$m[1];
        return true;
    }

    /* ---------- payments helpers (جدول payment/payments) ---------- */
    private function paymentTableName(): ?string
    {
        if ($this->hasTable('payment'))  return 'payment';   // طبق اسکیمای شما
        if ($this->hasTable('payments')) return 'payments';  // fallback
        return null;
    }
    /** برگشت: ['table','invoice','amount','pay_date','method','ref','note','unit_id']  */
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
        $type = $this->columnType($tbl, 'method'); // enum('cash','card','bank','online')
        if ($type && preg_match_all("/'([^']+)'/", $type, $m)) return $m[1];
        return ['cash', 'card', 'bank', 'online'];
    }

    /* ---------- اتصال نام مالک: units + ownerships + residents ---------- */
    /**
     * خروجی: [$joins, $selectAsOwnerName, $filterExpr]  — سومی برای WHERE LIKE استفاده می‌شود.
     */
    private function ownerJoinParts(string $invAlias = 'i'): array
    {
        $joins = '';
        $sel   = "NULL AS owner_name";
        $filterExpr = null;

        // مسیر استاندارد: ownerships (unit_id → resident_id) + residents(full_name)
        $hasOwnerships = $this->hasTable('ownerships')
            && $this->hasColumn('ownerships', 'unit_id')
            && $this->hasColumn('ownerships', 'resident_id');
        $hasResidents  = $this->hasTable('residents')
            && $this->hasColumn('residents', 'id')
            && $this->hasColumn('residents', 'full_name');

        if ($hasOwnerships && $hasResidents) {
            $joins .= " LEFT JOIN (
                            SELECT unit_id, MIN(resident_id) AS resident_id
                            FROM ownerships
                            GROUP BY unit_id
                        ) ow ON ow.unit_id = {$invAlias}.unit_id
                        LEFT JOIN residents r ON r.id = ow.resident_id
            ";
            $sel = "r.full_name AS owner_name";
            $filterExpr = "r.full_name";
            return [$joins, $sel, $filterExpr];
        }

        // fallback: اگر ستون نام در units باشد
        if ($this->hasTable('units')) {
            $joins .= " LEFT JOIN units u ON u.id = {$invAlias}.unit_id\n";
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
    private function validCsrf(string $token): bool
    {
        return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    /* ---------- RENDER ---------- */
    private function render(string $view, array $vars = [], ?string $pageTitle = null): void
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $view;
        $content = ob_get_clean();
        $page_title = $pageTitle ?? 'تنخواه';

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

    /* ===================== 1) فهرست راندها ===================== */
    public function index(): void
    {
        $amtCol  = $this->firstExistingCol(['amount', 'total_amount', 'total', 'price', 'final_amount'], 'invoices') ?? 'amount';
        $hasKind = $this->hasColumn('invoices', 'kind');
        $kindAnd = ($hasKind && $this->kindAccepts(self::KIND_VALUE)) ? "AND i.kind='" . self::KIND_VALUE . "'" : "";

        $hasInvRun = $this->hasColumn('invoices', 'pettycash_run_id');
        $hasAlloc  = $this->hasTable('pettycash_allocations') && $this->hasColumn('pettycash_allocations', 'run_id') && $this->hasColumn('pettycash_allocations', 'invoice_id');

        $ps = $this->paymentsSchema();
        $pTbl = $ps['table']   ?? null;
        $pInv = $ps['invoice'] ?? null;
        $pAmt = $ps['amount']  ?? null;

        $paidSub = ($pTbl && $pInv && $pAmt)
            ? "LEFT JOIN (SELECT $pInv AS invoice_id, COALESCE(SUM($pAmt),0) AS paid_amount FROM $pTbl GROUP BY $pInv) p ON p.invoice_id = i.id"
            : "";

        if ($hasInvRun) {
            $sql = "
                SELECT
                  pr.id, pr.title, pr.period, pr.target_amount,
                  COUNT(i.id) AS invoices_count,
                  COALESCE(SUM(i.$amtCol),0)               AS invoiced_total,
                  COALESCE(SUM(COALESCE(p.paid_amount,0)),0) AS paid_total
                FROM pettycash_runs pr
                LEFT JOIN invoices i ON i.pettycash_run_id = pr.id $kindAnd
                $paidSub
                GROUP BY pr.id
                ORDER BY pr.id DESC
            ";
        } elseif ($hasAlloc) {
            $sql = "
                SELECT
                  pr.id, pr.title, pr.period, pr.target_amount,
                  COUNT(i.id) AS invoices_count,
                  COALESCE(SUM(i.$amtCol),0)               AS invoiced_total,
                  COALESCE(SUM(COALESCE(p.paid_amount,0)),0) AS paid_total
                FROM pettycash_runs pr
                LEFT JOIN pettycash_allocations pa ON pa.run_id = pr.id
                LEFT JOIN invoices i ON i.id = pa.invoice_id $kindAnd
                $paidSub
                GROUP BY pr.id
                ORDER BY pr.id DESC
            ";
        } else {
            $sql = "
                SELECT pr.id, pr.title, pr.period, pr.target_amount,
                       0 AS invoices_count, 0 AS invoiced_total, 0 AS paid_total
                FROM pettycash_runs pr
                ORDER BY pr.id DESC
            ";
        }

        try {
            $runs = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'خطا در واکشی راندها: ' . $e->getMessage();
            $runs = [];
        }

        $this->render(__DIR__ . '/../views/pettycash/runs.php', [
            'runs'    => $runs,
            'methods' => $this->paymentMethods(),
            'csrf'    => $this->ensureCsrf(),
        ], 'راندهای تنخواه');
    }

    /* ===================== 2) جزئیات راند + فیلتر نام مالک ===================== */
    public function run(): void
    {
        $runId = (int)($_GET['run_id'] ?? 0);
        if ($runId <= 0) {
            header('Location: index.php?page=pettycash');
            exit;
        }

        $ownerQuery = trim((string)($_GET['owner'] ?? '')); // فیلتر جستجو نام مالک

        $amtCol  = $this->invAmountColRequired();
        $subjCol = $this->invSubjectColNullable();
        $issueCol = $this->invIssueCol();
        $hasKind = $this->hasColumn('invoices', 'kind');
        $kindAnd = ($hasKind && $this->kindAccepts(self::KIND_VALUE)) ? "AND i.kind='" . self::KIND_VALUE . "'" : "";

        $ps = $this->paymentsSchema();
        $pTbl = $ps['table']   ?? null;
        $pInv = $ps['invoice'] ?? null;
        $pAmt = $ps['amount']  ?? null;

        $paidSub = ($pTbl && $pInv && $pAmt)
            ? "LEFT JOIN (SELECT $pInv AS invoice_id, COALESCE(SUM($pAmt),0) AS paid_amount FROM $pTbl GROUP BY $pInv) p ON p.invoice_id = i.id"
            : "LEFT JOIN (SELECT 0 AS invoice_id, 0 AS paid_amount) p ON p.invoice_id = i.id";

        [$ownerJoins, $ownerSel, $ownerFilterExpr] = $this->ownerJoinParts('i');

        $ownerFilterSql = '';
        $params = [':rid' => $runId];
        if ($ownerQuery !== '' && $ownerFilterExpr) {
            $ownerFilterSql = " AND {$ownerFilterExpr} LIKE :owner ";
            $params[':owner'] = '%' . $ownerQuery . '%';
        }

        $hasInvRun = $this->hasColumn('invoices', 'pettycash_run_id');
        $hasAlloc  = $this->hasTable('pettycash_allocations') && $this->hasColumn('pettycash_allocations', 'run_id') && $this->hasColumn('pettycash_allocations', 'invoice_id');

        if ($hasInvRun) {
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
                WHERE i.pettycash_run_id = :rid $kindAnd
                  $ownerFilterSql
                ORDER BY i.id DESC
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
        } elseif ($hasAlloc) {
            $sql = "
                SELECT 
                  i.id, i.unit_id, i.$amtCol AS amount,
                  " . ($subjCol ? "i.$subjCol" : "NULL") . " AS subject,
                  " . ($issueCol ? "i.$issueCol" : "NULL") . " AS issue_date,
                  $ownerSel,
                  COALESCE(p.paid_amount,0) AS paid_amount
                FROM pettycash_allocations pa
                JOIN invoices i ON i.id = pa.invoice_id $kindAnd
                $paidSub
                $ownerJoins
                WHERE pa.run_id = :rid
                  $ownerFilterSql
                ORDER BY i.id DESC
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
        } else {
            $_SESSION['error'] = 'نگاشت راند به فاکتورها در اسکیمای شما وجود ندارد.';
            header('Location: index.php?page=pettycash');
            exit;
        }

        $invoices = $st->fetchAll(\PDO::FETCH_ASSOC);

        // اطلاعات راند
        $runInfo = $this->pdo->prepare("SELECT id, " . ($this->hasColumn('pettycash_runs', 'title') ? 'title' : 'NULL AS title') . ", " .
            ($this->hasColumn('pettycash_runs', 'period') ? 'period' : 'NULL AS period') . ", " .
            ($this->hasColumn('pettycash_runs', 'target_amount') ? 'target_amount' : 'NULL AS target_amount') . "
                                        FROM pettycash_runs WHERE id=:id");
        $runInfo->execute([':id' => $runId]);
        $run = $runInfo->fetch(\PDO::FETCH_ASSOC) ?: ['id' => $runId, 'title' => null, 'period' => null, 'target_amount' => null];

        $this->render(__DIR__ . '/../views/pettycash/run.php', [
            'run'     => $run,
            'rows'    => $invoices,
            'methods' => $this->paymentMethods(),
            'csrf'    => $this->ensureCsrf(),
            'owner_q' => $ownerQuery,
        ], 'جزئیات راند');
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
        $amount    = (float)($_POST['amount'] ?? 0);
        $method    = trim((string)($_POST['method'] ?? 'cash'));
        $ref       = trim((string)($_POST['ref'] ?? ''));
        $note      = trim((string)($_POST['note'] ?? ''));
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

        // خواندن مبلغ فاکتور + واحد
        $amtCol = $this->invAmountColRequired();
        $stInv = $this->pdo->prepare("SELECT id, unit_id, $amtCol AS amount, " . ($this->hasColumn('invoices', 'kind') ? 'kind' : 'NULL AS kind') . " FROM invoices WHERE id=:id");
        $stInv->execute([':id' => $invoiceId]);
        $inv = $stInv->fetch(\PDO::FETCH_ASSOC);
        if (!$inv) {
            $_SESSION['error'] = 'فاکتور پیدا نشد.';
            $this->redirectBack($runIdBack);
        }
        if (isset($inv['kind']) && $inv['kind'] !== self::KIND_VALUE) {
            $_SESSION['error'] = 'این فاکتور از نوع تنخواه (petty) نیست.';
            $this->redirectBack($runIdBack);
        }

        // مجموع پرداخت‌های قبلی
        $stPaid = $this->pdo->prepare("SELECT COALESCE(SUM({$ps['amount']}),0) FROM {$ps['table']} WHERE {$ps['invoice']} = :iid");
        $stPaid->execute([':iid' => $invoiceId]);
        $paidBefore = (float)$stPaid->fetchColumn();

        if ($amount + $paidBefore > (float)$inv['amount']) {
            $_SESSION['error'] = 'مبلغ پرداخت بیش از مانده فاکتور است.';
            $this->redirectBack($runIdBack);
        }

        // INSERT به جدول payment
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
            $params[':m']   = ($method !== '' ? $method : null);
        }
        if (!empty($ps['ref'])) {
            $cols[] = $ps['ref'];
            $vals[] = ':r';
            $params[':r']   = ($ref !== '' ? $ref : null);
        }
        if (!empty($ps['note'])) {
            $cols[] = $ps['note'];
            $vals[] = ':n';
            $params[':n']   = ($note !== '' ? $note : null);
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
        if ($runIdBack > 0) {
            header('Location: index.php?page=pettycash_run&run_id=' . $runIdBack);
        } else {
            header('Location: index.php?page=pettycash');
        }
        exit;
    }

    /* ---------- ایجاد راند + ثبت ---------- */
    public function create(): void
    {
        $units = $this->pdo->query("SELECT id,name,floor FROM units WHERE is_active=1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $this->render(__DIR__ . '/../views/pettycash/create.php', ['units' => $units], 'ایجاد راند تنخواه');
    }

    public function store(): void
    {
        $token = (string)($_POST['_csrf'] ?? '');
        if (!$this->validCsrf($token)) {
            http_response_code(400);
            die('Invalid CSRF token');
        }

        $title         = trim((string)($_POST['title'] ?? ''));
        $category      = trim((string)($_POST['category'] ?? ''));
        $period        = trim((string)($_POST['period'] ?? ''));
        $target_amount = (float)($_POST['target_amount'] ?? 0);
        $method        = (string)($_POST['method'] ?? 'equal'); // equal/weights/custom
        $note          = trim((string)($_POST['note'] ?? ''));

        if ($target_amount <= 0 || !in_array($method, ['equal', 'weights', 'custom'], true)) {
            $_SESSION['error'] = 'اطلاعات راند تنخواه نامعتبر است.';
            header('Location: index.php?page=pettycash_create');
            exit;
        }

        $units = $this->pdo->query("SELECT id FROM units WHERE is_active=1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        if (!$units) {
            $_SESSION['error'] = 'هیچ واحد فعالی یافت نشد.';
            header('Location: index.php?page=pettycash_create');
            exit;
        }

        // محاسبه سهم‌ها
        $basis = [];
        foreach ($units as $u) {
            $uid = (int)$u['id'];
            if ($method === 'equal') $basis[$uid] = 1.0;
            elseif ($method === 'weights') $basis[$uid] = max(0, (float)($_POST['w'][$uid] ?? 0));
            else $basis[$uid] = max(0, (float)($_POST['c'][$uid] ?? 0));
        }
        $sumBasis = array_sum($basis);
        if ($method !== 'custom' && $sumBasis <= 0) {
            $_SESSION['error'] = 'جمع وزن‌ها باید بزرگ‌تر از صفر باشد.';
            header('Location: index.php?page=pettycash_create');
            exit;
        }

        // ستون‌های invoices
        try {
            $amtCol = $this->invAmountColRequired();
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?page=pettycash_create');
            exit;
        }
        $subjCol   = $this->invSubjectColNullable();
        $issueCol  = $this->invIssueCol();
        $kindCol   = $this->hasColumn('invoices', 'kind') ? 'kind' : null;
        $periodCol = $this->hasColumn('invoices', 'period') ? 'period' : null;
        $pcRunCol  = $this->hasColumn('invoices', 'pettycash_run_id') ? 'pettycash_run_id' : null;

        $cols = ['unit_id', $amtCol];
        $vals = [':uid',    ':amt'];
        if ($subjCol) {
            $cols[] = $subjCol;
            $vals[] = ':subj';
        }
        if ($issueCol) {
            $cols[] = $issueCol;
            $vals[] = 'CURDATE()';
        }
        $canSetKind = ($kindCol && $this->kindAccepts(self::KIND_VALUE));
        if ($canSetKind) {
            $cols[] = $kindCol;
            $vals[] = ':k';
        }
        if ($periodCol) {
            $cols[] = $periodCol;
            $vals[] = ':per';
        }
        if ($pcRunCol) {
            $cols[] = $pcRunCol;
            $vals[] = ':rid';
        }

        $sqlInv = "INSERT INTO invoices (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        $insInv = $this->pdo->prepare($sqlInv);

        // ایجاد راند
        $runId = null;
        if ($this->hasTable('pettycash_runs')) {
            $runCols = [];
            $runVals = [];
            $runParams = [];
            if ($this->hasColumn('pettycash_runs', 'title')) {
                $runCols[] = 'title';
                $runVals[] = ':t';
                $runParams[':t'] = ($title !== '' ? $title : 'تنخواه');
            }
            if ($this->hasColumn('pettycash_runs', 'category')) {
                $runCols[] = 'category';
                $runVals[] = ':c';
                $runParams[':c'] = ($category !== '' ? $category : null);
            }
            if ($this->hasColumn('pettycash_runs', 'period')) {
                $runCols[] = 'period';
                $runVals[] = ':p';
                $runParams[':p'] = ($period !== '' ? $period : null);
            }
            if ($this->hasColumn('pettycash_runs', 'target_amount')) {
                $runCols[] = 'target_amount';
                $runVals[] = ':a';
                $runParams[':a'] = $target_amount;
            }
            if ($this->hasColumn('pettycash_runs', 'method')) {
                $runCols[] = 'method';
                $runVals[] = ':m';
                $runParams[':m'] = $method;
            }
            if ($this->hasColumn('pettycash_runs', 'note')) {
                $runCols[] = 'note';
                $runVals[] = ':n';
                $runParams[':n'] = ($note !== '' ? $note : null);
            }
            if ($runCols) {
                $sqlRun = "INSERT INTO pettycash_runs (" . implode(',', $runCols) . ") VALUES (" . implode(',', $runVals) . ")";
                $this->pdo->prepare($sqlRun)->execute($runParams);
                $runId = (int)$this->pdo->lastInsertId();
            }
        }

        // محاسبه مبالغ سهم هر واحد
        $shares = [];
        if ($method === 'custom') {
            foreach ($basis as $uid => $val) $shares[$uid] = (int)round($val);
        } else {
            $running = 0.0;
            $frac = [];
            foreach ($basis as $uid => $b) {
                $raw = ($target_amount * $b / $sumBasis);
                $shares[$uid] = (int)floor($raw);
                $running += $shares[$uid];
                $frac[$uid] = $raw - $shares[$uid];
            }
            $rem = (int)round($target_amount - $running);
            if ($rem !== 0) {
                arsort($frac);
                foreach (array_keys($frac) as $uid) {
                    if ($rem === 0) break;
                    $shares[$uid] += ($rem > 0 ? 1 : -1);
                    $rem += ($rem > 0 ? -1 : 1);
                }
            }
        }

        $this->pdo->beginTransaction();
        try {
            $catLabel = ($category !== '') ? $category : 'عمومی';
            $subjTpl  = "تنخواه {$catLabel}" . ($period ? " دوره {$period}" : "");
            $insCount = 0;

            foreach ($shares as $uid => $amt) {
                $amt = (int)$amt;
                if ($amt <= 0) continue;
                $params = [':uid' => $uid, ':amt' => $amt];
                if ($subjCol)   $params[':subj'] = $subjTpl;
                if ($canSetKind) $params[':k'] = self::KIND_VALUE;
                if ($periodCol) $params[':per'] = ($period !== '' ? $period : null);
                if ($pcRunCol && $runId) $params[':rid'] = $runId;
                $insInv->execute($params);
                $insCount++;
            }

            $this->pdo->commit();
            $_SESSION['ok'] = "راند تنخواه ثبت شد. فاکتور صادرشده: {$insCount}";
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $_SESSION['error'] = 'خطا در ثبت تنخواه: ' . $e->getMessage();
        }

        header('Location: index.php?page=pettycash');
        exit;
    }
}
