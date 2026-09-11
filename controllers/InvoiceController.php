<?php
// controllers/InvoiceController.php
declare(strict_types=1);

final class InvoiceController
{
    public function __construct(private \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /* ---------- columns helpers ---------- */
    private function hasColumn(string $table, string $col): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = :t
                AND COLUMN_NAME  = :c
              LIMIT 1"
        );
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    }
    private function colSub(): string
    {
        foreach (['subject', 'title', 'description', 'name'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return 'subject';
    }
    private function colAmt(): string
    {
        foreach (['amount', 'total_amount', 'total', 'price'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return 'amount';
    }
    private function colIssue(): ?string
    {
        foreach (['issue_date', 'created_at', 'created', 'date'] as $c) if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function colDue(): ?string
    {
        foreach (['due_date', 'deadline', 'due'] as $c)         if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }
    private function colPeriod(): ?string
    {
        foreach (['period'] as $c)                             if ($this->hasColumn('invoices', $c)) return $c;
        return null;
    }

    /** اگر ستون period ندارید، از تاریخ صدور به شکل YYYY-MM می‌سازیم */
    private function periodExpr(?string $colPeriod, ?string $colIssue): string
    {
        if ($colPeriod) return "i.$colPeriod";
        if ($colIssue)  return "DATE_FORMAT(i.$colIssue, '%Y-%m')";
        return "'بدون-دوره'";
    }

    /* ---------- GET /invoices (لیست فاکتورها) ---------- */
    public function index(): void
    {
        $colSub    = $this->colSub();
        $colAmt    = $this->colAmt();
        $colIssue  = $this->colIssue();
        $colDue    = $this->colDue();
        $colPeriod = $this->colPeriod();

        // filters
        $where  = [];
        $params = [];

        if ($colPeriod && isset($_GET['period']) && $_GET['period'] !== '') {
            $where[] = "i.$colPeriod = :period";
            $params[':period'] = trim((string)$_GET['period']);
        }
        if (isset($_GET['unit_id']) && $_GET['unit_id'] !== '') {
            $where[] = "i.unit_id = :uid";
            $params[':uid'] = (int)$_GET['unit_id'];
        }
        if (isset($_GET['q']) && $_GET['q'] !== '') {
            $where[] = "i.$colSub LIKE :q";
            $params[':q'] = '%' . trim((string)$_GET['q']) . '%';
        }

        // pagination
        $perPage = isset($_GET['per_page']) ? max(5, min((int)$_GET['per_page'], 200)) : 25;
        $page    = max(1, (int)($_GET['p'] ?? 1));

        $countSql = "SELECT COUNT(*) FROM invoices i" . (count($where) ? " WHERE " . implode(" AND ", $where) : "");
        $stc = $this->pdo->prepare($countSql);
        $stc->execute($params);
        $total = (int)$stc->fetchColumn();

        $lastPage = max(1, (int)ceil($total / $perPage));
        if ($page > $lastPage) $page = $lastPage;
        $offset = ($page - 1) * $perPage;

        // main query
        $sql = "SELECT i.id, i.unit_id, u.name AS unit_name,
                        u.floor AS unit_floor,
                       i.$colSub  AS subject,
                       i.$colAmt  AS amount"
            . ($colIssue  ? ", i.$colIssue  AS issue_date" : "")
            . ($colDue    ? ", i.$colDue    AS due_date"   : "")
            . ($colPeriod ? ", i.$colPeriod AS period"     : "")
            . " FROM invoices i
                 LEFT JOIN units u ON u.id = i.unit_id"
            . (count($where) ? " WHERE " . implode(" AND ", $where) : "")
            . " ORDER BY "
            . ($colPeriod ? "i.$colPeriod DESC, " : "")
            . ($colIssue  ? "i.$colIssue DESC, "  : "")
            . "i.id DESC
               LIMIT :limit OFFSET :offset";

        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $st->execute();
        $invoices = $st->fetchAll(\PDO::FETCH_ASSOC);


        // dropdown data
        $units = $this->pdo->query("SELECT id,name,floor FROM units WHERE is_active=1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $periods = [];
        if ($colPeriod) {
            $periods = $this->pdo->query("SELECT DISTINCT $colPeriod AS p FROM invoices ORDER BY p DESC")->fetchAll(\PDO::FETCH_COLUMN);
        }

        // keep filters in pagination links
        $qs = $_GET;
        unset($qs['p']);
        $qs['page'] = 'invoices';
        $qsBase = http_build_query($qs, '', '&', \PHP_QUERY_RFC3986);

        // === CSV Export ===
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            // در SELECT باید u.floor را هم داشته باشی: u.floor AS unit_floor
            $cols = [
                'id'         => 'شناسه فاکتور',
                'unit_name'  => 'واحد',
                'unit_floor' => 'طبقه',
                'subject'    => 'عنوان',
                'amount'     => 'مبلغ',
                // ستون‌های اختیاری بسته به وجودشان:
                'issue_date' => 'تاریخ صدور',
                'due_date'   => 'سررسید',
                'period'     => 'دوره',
            ];
            csv_output('invoices_' . date('Ymd_His') . '.csv', $invoices, $cols);
        }

        // === Print View ===
        if (isset($_GET['view']) && $_GET['view'] === 'print') {
            // اگر تاریخ شمسی لازم داری، اینجا مشابه payments می‌شود اضافه کرد
            ob_start();
            include __DIR__ . '/../views/invoices/print.php';
            $content = ob_get_clean();
            // استفاده از همان لایوت معمولی:
            $page_title = 'چاپ فاکتورها';
            $layoutRouter = __DIR__ . '/../views/layout.php';
            if (is_file($layoutRouter)) {
                include $layoutRouter;
            } else {
                echo $content;
            }
            return;
        }



        // متغیرهایی که ویو نیاز دارد در همین اسکوپ هستند:
        // $invoices, $units, $periods, $total, $page, $perPage, $lastPage, $qsBase, $colIssue, $colDue, $colPeriod

        // --- render with layout ---
        ob_start();
        include __DIR__ . '/../views/invoices/index.php'; // ویوی لیست فاکتورها
        $content = ob_get_clean();

        // اگر لایوت ادمین موجود است، اولویت با آن؛ بعد پرتال؛ بعد لایوت عمومی
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
        // اگر هیچ لایوتی نبود، محتوای خام را نشان بده
        echo $content;
    }

    /* ---------- GET /invoices?action=periods (نمای کلی دوره‌ها) ---------- */
    public function periods(): void
    {
        $colSub    = $this->colSub();
        $colAmt    = $this->colAmt();
        $colIssue  = $this->colIssue();
        $colPeriod = $this->colPeriod();
        $pExpr     = $this->periodExpr($colPeriod, $colIssue); // کلید دوره

        // فیلترها (واحد و جستجو)
        $where = [];
        $params = [];
        if (isset($_GET['unit_id']) && $_GET['unit_id'] !== '') {
            $where[] = "i.unit_id = :uid";
            $params[':uid'] = (int)$_GET['unit_id'];
        }
        if (isset($_GET['q']) && $_GET['q'] !== '') {
            $where[] = "i.$colSub LIKE :q";
            $params[':q'] = '%' . trim((string)$_GET['q']) . '%';
        }

        // صفحه‌بندی «خود دوره‌ها»
        $perPage = isset($_GET['per_page']) ? max(6, min((int)$_GET['per_page'], 120)) : 24;
        $page    = max(1, (int)($_GET['p'] ?? 1));

        // تعداد دوره‌های یکتا
        $countSql = "SELECT COUNT(*) FROM (
                       SELECT DISTINCT {$pExpr} AS p
                         FROM invoices i
                        " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
                     ) t";
        $stc = $this->pdo->prepare($countSql);
        $stc->execute($params);
        $total = (int)$stc->fetchColumn();

        $lastPage = max(1, (int)ceil($total / $perPage));
        if ($page > $lastPage) $page = $lastPage;
        $offset = ($page - 1) * $perPage;

        // خلاصه‌ی دوره‌ها
        $sql = "SELECT {$pExpr} AS period,
                       COUNT(*)        AS invoice_count,
                       SUM(i.$colAmt)  AS total_amount
                  FROM invoices i
                 " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
                 GROUP BY period
                 ORDER BY period DESC
                 LIMIT :limit OFFSET :offset";
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        // گروه‌بندی به تفکیک سال برای نمایش
        $byYear = [];
        foreach ($rows as $r) {
            $p = (string)($r['period'] ?? 'بدون-دوره');
            $year = preg_match('/^\d{4}/', $p) ? substr($p, 0, 4) : 'نامشخص';
            $byYear[$year][] = $r;
        }
        krsort($byYear);

        // داده‌های فرم فیلتر
        $units = $this->pdo->query("SELECT id,name FROM units WHERE is_active=1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

        // querystring پایه (حفظ فیلترها)
        $qs = $_GET;
        unset($qs['p']);
        $qs['page'] = 'invoices';
        $qs['action'] = 'periods';
        $qsBase = http_build_query($qs, '', '&', \PHP_QUERY_RFC3986);

        // --- render with layout ---
        ob_start();
        include __DIR__ . '/../views/invoices/periods.php'; // ویوی خلاصه دوره‌ها
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
}
