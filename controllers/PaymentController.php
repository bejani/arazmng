<?php
// controllers/PaymentController.php
declare(strict_types=1);

require_once __DIR__ . '/../views/_helpers.php'; // normalize_date_input, csrf_field(), jdate, money, h

final class PaymentController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /* --------------------- Helpers --------------------- */
    private function invSubjectExpr(): string
    {
        // Flexible subject column for invoices
        $st = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='invoices'");
        $cols = $st->fetchAll(PDO::FETCH_COLUMN);
        foreach (['subject', 'title', 'description', 'name'] as $c) {
            if (in_array($c, $cols, true)) return "i.`$c`";
        }
        return "CONCAT('فاکتور #', i.id)";
    }
    private function invAmountExpr(): string
    {
        $st = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='invoices'");
        $cols = $st->fetchAll(PDO::FETCH_COLUMN);
        foreach (['amount', 'total_amount', 'total', 'price'] as $c) {
            if (in_array($c, $cols, true)) return "i.`$c`";
        }
        return "0";
    }
    private function residentNameExpr(string $alias = 'r'): string
    {
        $st = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='residents'");
        $cols = $st->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('full_name', $cols, true)) return "TRIM({$alias}.full_name)";
        if (in_array('name', $cols, true))      return "TRIM({$alias}.name)";
        $parts = [];
        if (in_array('first_name', $cols, true)) $parts[] = "COALESCE({$alias}.first_name,'')";
        if (in_array('last_name',  $cols, true)) $parts[] = "COALESCE({$alias}.last_name,'')";
        if ($parts) return "TRIM(CONCAT(" . implode(", ' ', ", $parts) . "))";
        return "CONCAT('ID ', {$alias}.id)";
    }

    /** Subquery to resolve owner at pay_date from ownerships (id,unit_id,resident_id,start_date,end_date,is_primary) */
    private function ownerNameSubqueryExprStrict(): string
    {
        $nameExpr = $this->residentNameExpr('r2');
        // Pick the ownership of that unit where pay_date is within [start_date, end_date], prefer is_primary=1, then latest start_date
        // If pay_date is NULL, use CURRENT_DATE
        return "
            (SELECT {$nameExpr}
             FROM ownerships o2
             JOIN residents r2 ON r2.id = o2.resident_id
             WHERE o2.unit_id = p.unit_id
               AND (o2.start_date IS NULL OR o2.start_date <= COALESCE(p.pay_date, CURRENT_DATE))
               AND (o2.end_date   IS NULL OR o2.end_date   >= COALESCE(p.pay_date, CURRENT_DATE))
             ORDER BY o2.is_primary DESC, o2.start_date DESC, o2.id DESC
             LIMIT 1)";
    }

    /* --------------------- Render --------------------- */
    private function render(string $viewPath, array $vars = [], ?string $pageTitle = null): void
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $viewPath;    // ویو فقط بدنه تولید می‌کند
        $content = ob_get_clean();

        $layoutRouter = __DIR__ . '/../views/layout.php';
        if (is_file($layoutRouter)) {
            $page_title = $pageTitle ?? ($page_title ?? null);
            include $layoutRouter;
            return;
        }
        echo $content;
    }

    /* --------------------- لیست پرداخت‌ها --------------------- */
    public function index(): void
    {
        // فیلترها
        $filter = [
            'unit_id' => ($_GET['unit_id'] ?? '') !== '' ? (int)$_GET['unit_id'] : null,
            'from'    => normalize_date_input($_GET['from'] ?? ''),
            'to'      => normalize_date_input($_GET['to']   ?? ''),
            'method'  => trim((string)($_GET['method'] ?? '')),
            'q'       => trim((string)($_GET['q'] ?? '')),
        ];

        // مرتب‌سازی
        $allowedSort = ['id', 'unit_name', 'invoice_id', 'subject', 'amount', 'pay_date', 'method', 'ref', 'note', 'payer_name'];
        $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort, true) ? (string)$_GET['sort'] : 'pay_date';
        $dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

        // شرط‌ها
        $conds = [];
        $p     = [];
        if ($filter['unit_id']) {
            $conds[] = "p.unit_id = :uid";
            $p[':uid'] = $filter['unit_id'];
        }
        if ($filter['from']) {
            $conds[] = "p.pay_date >= :f";
            $p[':f'] = $filter['from'];
        }
        if ($filter['to']) {
            $conds[] = "p.pay_date <= :t";
            $p[':t'] = $filter['to'];
        }
        if ($filter['method'] !== '') {
            $conds[] = "p.method = :m";
            $p[':m'] = $filter['method'];
        }
        if ($filter['q'] !== '') {
            $conds[] = "(p.ref LIKE :q OR p.note LIKE :q)";
            $p[':q'] = '%' . $filter['q'] . '%';
        }

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        $subjExpr  = $this->invSubjectExpr();
        $payerExpr = $this->ownerNameSubqueryExprStrict();

        $sortMap = [
            'id'         => 'p.id',
            'unit_name'  => 'u.name',
            'invoice_id' => 'p.invoice_id',
            'subject'    => $subjExpr,
            'amount'     => 'p.amount',
            'pay_date'   => 'p.pay_date',
            'method'     => 'p.method',
            'ref'        => 'p.ref',
            'note'       => 'p.note',
            'payer_name' => $payerExpr,
        ];
        $orderByExpr = $sortMap[$sort] ?? 'p.pay_date';

        $sql = "
            SELECT p.id, p.invoice_id, p.unit_id, p.amount, p.pay_date, p.method, p.ref, p.note, p.created_at,
                   u.name AS unit_name, u.floor,
                   {$subjExpr} AS subject,
                   {$payerExpr} AS payer_name
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            JOIN units u    ON u.id = p.unit_id
            $where
            ORDER BY {$orderByExpr} {$dir}, p.id DESC
            LIMIT 500
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // واحدها برای فیلتر
        $units = $this->pdo->query("SELECT id,name,floor FROM units ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $this->render(__DIR__ . '/../views/payments/index.php', [
            'rows'    => $rows,
            'units'   => $units,
            'filter'  => $filter,
            'current_sort' => strtolower((string)($_GET['sort'] ?? 'pay_date')),
            'current_dir'  => strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ], 'پرداخت‌ها');
    }

    /* --------------------- فرم پرداخت جدید --------------------- */
    public function new(): void
    {
        $selUnit = ($_GET['unit_id'] ?? '') !== '' ? (int)$_GET['unit_id'] : null;
        $selInv  = ($_GET['invoice_id'] ?? '') !== '' ? (int)$_GET['invoice_id'] : null;

        // واحدها
        $units = $this->pdo->query("SELECT id,name,floor FROM units WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        // فاکتورهای باز برای واحد انتخاب‌شده
        $openInvoices = [];
        $selectedInvoice = null;
        $selectedBalance = null;

        $amtExpr = $this->invAmountExpr();
        $subj    = $this->invSubjectExpr();

        if ($selUnit) {
            $sql = "
                SELECT i.id, i.unit_id,
                       {$subj} AS subject,
                       {$amtExpr} AS invoice_amount,
                       COALESCE(pay.paid_amount,0) AS paid_amount,
                       GREATEST(({$amtExpr}) - COALESCE(pay.paid_amount,0), 0) AS balance
                FROM invoices i
                LEFT JOIN (
                    SELECT p.invoice_id, SUM(p.amount) AS paid_amount
                    FROM payments p
                    GROUP BY p.invoice_id
                ) pay ON pay.invoice_id = i.id
                WHERE i.unit_id = :u
                HAVING balance > 0
                ORDER BY i.id ASC
                LIMIT 200
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([':u' => $selUnit]);
            $openInvoices = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($selInv) {
            $sql = "
                SELECT i.id, i.unit_id, {$subj} AS subject, {$amtExpr} AS invoice_amount,
                       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) AS paid_amount
                FROM invoices i
                WHERE i.id = :id
                LIMIT 1
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $selInv]);
            $selectedInvoice = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedInvoice) {
                $selectedBalance = max(0, (float)$selectedInvoice['invoice_amount'] - (float)$selectedInvoice['paid_amount']);
                $selUnit = (int)$selectedInvoice['unit_id'];
            }
        }

        $this->render(__DIR__ . '/../views/payments/new.php', [
            'units'           => $units,
            'selUnit'         => $selUnit,
            'openInvoices'    => $openInvoices,
            'selectedInvoice' => $selectedInvoice,
            'selectedBalance' => $selectedBalance,
        ], 'ثبت پرداخت');
    }

    /* --------------------- ثبت پرداخت (POST) --------------------- */
    public function store(): void
    {
        // CSRF
        if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            die('Invalid CSRF token');
        }

        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $amount     = (float)($_POST['amount'] ?? 0);
        $method     = (string)($_POST['method'] ?? 'cash'); // cash/bank/card
        $ref        = trim((string)($_POST['ref'] ?? ''));
        $note       = trim((string)($_POST['note'] ?? ''));
        $pay_date   = normalize_date_input($_POST['pay_date'] ?? ''); // شمسی/میلادی → Y-m-d

        if ($invoice_id <= 0 || $amount <= 0 || !$pay_date) {
            $_SESSION['error'] = 'ورودی نامعتبر است.';
            header('Location: index.php?page=payment_new');
            exit;
        }

        $this->pdo->beginTransaction();
        try {
            // فاکتور + مانده (lock)
            $amtExpr = $this->invAmountExpr();
            $st = $this->pdo->prepare("
                SELECT i.unit_id, {$amtExpr} AS invoice_amount,
                       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) AS paid_amount
                FROM invoices i
                WHERE i.id = :id
                FOR UPDATE
            ");
            $st->execute([':id' => $invoice_id]);
            $inv = $st->fetch(PDO::FETCH_ASSOC);
            if (!$inv) throw new \RuntimeException('فاکتور یافت نشد.');

            $balance = (float)$inv['invoice_amount'] - (float)$inv['paid_amount'];
            if ($amount - $balance > 1e-9) {
                throw new \RuntimeException('مبلغ بزرگ‌تر از مانده فاکتور است.');
            }

            // درج پرداخت
            $ins = $this->pdo->prepare("
                INSERT INTO payments (invoice_id, unit_id, amount, pay_date, method, ref, note)
                VALUES (:inv, :unit, :amt, :d, :m, :ref, :n)
            ");
            $ins->execute([
                ':inv'  => $invoice_id,
                ':unit' => (int)$inv['unit_id'],
                ':amt'  => $amount,
                ':d'    => $pay_date,
                ':m'    => $method ?: 'cash',
                ':ref'  => $ref,
                ':n'    => $note,
            ]);

            $this->pdo->commit();
            $_SESSION['ok'] = 'پرداخت ثبت شد.';
            header('Location: index.php?page=payments');
            exit;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $_SESSION['error'] = 'خطا در ثبت پرداخت: ' . $e->getMessage();
            header('Location: index.php?page=payment_new');
            exit;
        }
    }

    /* --------------------- فرم ویرایش پرداخت --------------------- */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'شناسهٔ پرداخت نامعتبر است.';
            header('Location: index.php?page=payments');
            exit;
        }

        $amtExpr = $this->invAmountExpr();
        $subj    = $this->invSubjectExpr();

        // پرداخت + اطلاعات فاکتور جاری + سقف مجاز برای ویرایش (با احتساب پرداخت فعلی)
        $sql = "
            SELECT p.*,
                   {$subj} AS subject,
                   {$amtExpr} AS invoice_amount,
                   COALESCE((SELECT SUM(pp.amount) FROM payments pp WHERE pp.invoice_id = p.invoice_id AND pp.id <> p.id),0) AS paid_except_this
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            WHERE p.id = :id
            LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $payment = $st->fetch(PDO::FETCH_ASSOC);
        if (!$payment) {
            $_SESSION['error'] = 'پرداخت یافت نشد.';
            header('Location: index.php?page=payments');
            exit;
        }

        $maxAllowed = max(0.0, (float)$payment['invoice_amount'] - (float)$payment['paid_except_this'] + (float)$payment['amount']); // مبلغ مجاز جدید

        // فاکتورهای بازِ همان واحد (برای جابجایی پرداخت)، به اضافهٔ فاکتور فعلی
        $sql2 = "
            SELECT i.id, i.unit_id,
                   {$subj} AS subject,
                   {$amtExpr} AS invoice_amount,
                   COALESCE((SELECT SUM(p2.amount) FROM payments p2 WHERE p2.invoice_id=i.id AND p2.id <> :pid),0) AS paid_amount_ex,
                   GREATEST(({$amtExpr}) - COALESCE((SELECT SUM(p3.amount) FROM payments p3 WHERE p3.invoice_id=i.id AND p3.id <> :pid),0), 0) AS balance
            FROM invoices i
            WHERE i.unit_id = :u
            HAVING balance > 0 OR i.id = :curr
            ORDER BY i.id ASC
            LIMIT 200
        ";
        $st2 = $this->pdo->prepare($sql2);
        $st2->execute([':u' => (int)$payment['unit_id'], ':pid' => $id, ':curr' => (int)$payment['invoice_id']]);
        $openInvoices = $st2->fetchAll(PDO::FETCH_ASSOC);

        $methods = ['cash', 'bank', 'card'];

        $this->render(__DIR__ . '/../views/payments/edit.php', [
            'payment'      => $payment,
            'openInvoices' => $openInvoices,
            'maxAllowed'   => $maxAllowed,
            'methods'      => $methods,
        ], 'ویرایش پرداخت');
    }

    /* --------------------- بروزرسانی پرداخت (POST) --------------------- */
    public function update(): void
    {
        // CSRF
        if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            die('Invalid CSRF token');
        }

        $id         = (int)($_POST['id'] ?? 0);
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $amount     = (float)($_POST['amount'] ?? 0);
        $method     = (string)($_POST['method'] ?? 'cash');
        $ref        = trim((string)($_POST['ref'] ?? ''));
        $note       = trim((string)($_POST['note'] ?? ''));
        $pay_date   = normalize_date_input($_POST['pay_date'] ?? '');

        if ($id <= 0 || $invoice_id <= 0 || $amount <= 0 || !$pay_date) {
            $_SESSION['error'] = 'ورودی نامعتبر است.';
            header('Location: index.php?page=payment_edit&id=' . $id);
            exit;
        }

        $this->pdo->beginTransaction();
        try {
            // قفل پرداخت برای ویرایش
            $stP = $this->pdo->prepare("SELECT * FROM payments WHERE id = :id FOR UPDATE");
            $stP->execute([':id' => $id]);
            $old = $stP->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                throw new \RuntimeException('پرداخت یافت نشد.');
            }

            // قفل فاکتور مقصد و محاسبه سقف مجاز (بدون احتساب همین پرداخت)
            $amtExpr = $this->invAmountExpr();
            $stI = $this->pdo->prepare("
                SELECT i.unit_id, {$amtExpr} AS invoice_amount,
                       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id AND p.id <> :pid),0) AS paid_except_this
                FROM invoices i
                WHERE i.id = :inv
                FOR UPDATE
            ");
            $stI->execute([':inv' => $invoice_id, ':pid' => $id]);
            $inv = $stI->fetch(PDO::FETCH_ASSOC);
            if (!$inv) {
                throw new \RuntimeException('فاکتور مقصد یافت نشد.');
            }

            $balance = (float)$inv['invoice_amount'] - (float)$inv['paid_except_this'];
            if ($amount - $balance > 1e-9) {
                throw new \RuntimeException('مبلغ بزرگ‌تر از مانده فاکتور است.');
            }

            // بروزرسانی پرداخت
            $upd = $this->pdo->prepare("
                UPDATE payments
                SET invoice_id = :inv,
                    unit_id    = :unit,
                    amount     = :amt,
                    pay_date   = :d,
                    method     = :m,
                    ref        = :ref,
                    note       = :n
                WHERE id = :id
                LIMIT 1
            ");
            $upd->execute([
                ':inv'  => $invoice_id,
                ':unit' => (int)$inv['unit_id'],
                ':amt'  => $amount,
                ':d'    => $pay_date,
                ':m'    => $method ?: 'cash',
                ':ref'  => $ref,
                ':n'    => $note,
                ':id'   => $id,
            ]);

            $this->pdo->commit();
            $_SESSION['ok'] = 'پرداخت بروزرسانی شد.';
            header('Location: index.php?page=payments');
            exit;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $_SESSION['error'] = 'خطا در بروزرسانی پرداخت: ' . $e->getMessage();
            header('Location: index.php?page=payment_edit&id=' . $id);
            exit;
        }
    }

    /* --------------------- حذف پرداخت --------------------- */
    public function delete(): void
    {
        // فقط POST با CSRF (اگر می‌خواهی GET هم کار کند، شرط را تغییر بده)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            die('Method Not Allowed');
        }
        if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            die('Invalid CSRF token');
        }

        $id      = (int)($_POST['id'] ?? 0);
        $run_id  = (int)($_POST['run_id'] ?? 0); // برای بازگشت به صفحهٔ راند (اختیاری)
        $redirect = (string)($_POST['redirect'] ?? '');

        if ($id <= 0) {
            $_SESSION['error'] = 'شناسهٔ پرداخت نامعتبر است.';
            $this->redirect($redirect, $run_id);
        }

        try {
            $st = $this->pdo->prepare("DELETE FROM payments WHERE id = :id LIMIT 1");
            $st->execute([':id' => $id]);
            if ($st->rowCount() > 0) {
                $_SESSION['ok'] = 'پرداخت حذف شد.';
            } else {
                $_SESSION['error'] = 'پرداختی با این شناسه یافت نشد.';
            }
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'خطا در حذف پرداخت: ' . $e->getMessage();
        }

        $this->redirect($redirect, $run_id);
    }

    /* --------------------- Redirect Helper --------------------- */
    private function redirect(string $redirect, int $run_id): void
    {
        if (!empty($redirect)) {
            header('Location: ' . $redirect);
            exit;
        }
        if ($run_id > 0) {
            header('Location: index.php?page=utility_run&run_id=' . $run_id);
            exit;
        }
        $fallback = $_SERVER['HTTP_REFERER'] ?? 'index.php?page=payments';
        header('Location: ' . $fallback);
        exit;
    }
}
