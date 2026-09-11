<?php
// controllers/ResidentPortalController.php
require_once __DIR__ . '/../views/_helpers.php'; // برای csv_output, jdate, ...

class ResidentPortalController
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    // ---------- Public pages ----------
    public function login(): void
    {
        include __DIR__ . '/../views/portal/login.php';
    }

    public function doLogin(): void
    {
        $mobile = $this->normalizeDigits($_POST['mobile'] ?? '');
        $pin    = trim((string)($_POST['pin'] ?? ''));
        if ($mobile === '' || $pin === '') {
            $_SESSION['error'] = 'موبایل و PIN الزامی است.';
            header('Location: index.php?page=portal_login');
            exit;
        }
        $row = $this->findResidentByMobilePin($mobile, $pin);
        if (!$row) {
            $_SESSION['error'] = 'اطلاعات ورود احتمالا نادرست است.';
            header('Location: index.php?page=portal_login');
            exit;
        }
        // enabled?
        if ($this->hasColumn('residents', 'portal_enabled') && (int)($row['portal_enabled'] ?? 1) !== 1) {
            $_SESSION['error'] = 'دسترسی شما غیرفعال است.';
            header('Location: index.php?page=portal_login');
            exit;
        }

        $_SESSION['portal_resident_id']   = (int)$row['id'];
        $_SESSION['portal_resident_name'] = $row['full_name'] ?? '';

        if ($this->hasColumn('residents', 'portal_last_login')) {
            $st = $this->pdo->prepare("UPDATE residents SET portal_last_login = NOW() WHERE id=:id");
            $st->execute([':id' => (int)$row['id']]);
        }

        // اگر ستون portal_must_change هست و 1 است ⇒ به فرم تغییر رمز بفرست
        $hasFlag = $this->hasColumn('residents', 'portal_must_change');
        if ($hasFlag && (int)($row['portal_must_change'] ?? 0) === 1) {
            header('Location: index.php?page=portal_change_pin&force=1');
            exit;
        }
        header('Location: index.php?page=portal_dashboard');
        exit;
    }

    public function logout(): void
    {
        unset($_SESSION['portal_resident_id'], $_SESSION['portal_resident_name']);
        $_SESSION['ok'] = 'خروج انجام شد.';
        header('Location: index.php?page=portal_login');
        exit;
    }
    public function changePinForm(): void
    {
        require_portal();
        include __DIR__ . '/../views/portal/change_pin.php';
    }

    public function changePinStore(): void
    {
        require_portal();
        $rid   = (int)($_SESSION['portal_resident_id'] ?? 0);
        $curr  = (string)($_POST['current_pin'] ?? '');
        $new1  = (string)($_POST['new_pin'] ?? '');
        $new2  = (string)($_POST['new_pin2'] ?? '');

        if ($new1 === '' || $new1 !== $new2) {
            $_SESSION['error'] = 'رمز جدید خالی است یا با تکرار آن یکسان نیست.';
            header('Location: index.php?page=portal_change_pin');
            exit;
        }
        if (strlen($new1) < 4) {
            $_SESSION['error'] = 'حداقل طول رمز ۴ کاراکتر باشد.';
            header('Location: index.php?page=portal_change_pin');
            exit;
        }

        // خواندن PIN فعلی
        $st = $this->pdo->prepare("SELECT portal_pin FROM residents WHERE id=?");
        $st->execute([$rid]);
        $hash = (string)$st->fetchColumn();

        // اعتبارسنجی PIN فعلی (پشتیبانی از legacy/plain و bcrypt)
        if ($hash !== '') {
            if (preg_match('/^\$2y\$/', $hash)) {
                if (!password_verify($curr, $hash)) {
                    $_SESSION['error'] = 'رمز فعلی نادرست است.';
                    header('Location: index.php?page=portal_change_pin');
                    exit;
                }
            } else {
                if ($curr !== $hash) {
                    $_SESSION['error'] = 'رمز فعلی نادرست است.';
                    header('Location: index.php?page=portal_change_pin');
                    exit;
                }
            }
        }

        $newHash = password_hash($new1, PASSWORD_BCRYPT);

        $sql = "UPDATE residents SET portal_pin=:p";
        $params = [':p' => $newHash, ':id' => $rid];

        if ($this->hasColumn('residents', 'portal_must_change'))    $sql .= ", portal_must_change=0";
        if ($this->hasColumn('residents', 'portal_pin_changed_at')) $sql .= ", portal_pin_changed_at=NOW()";
        $sql .= " WHERE id=:id";

        $upd = $this->pdo->prepare($sql);
        $upd->execute($params);

        $_SESSION['ok'] = 'رمز با موفقیت تغییر یافت.';
        header('Location: index.php?page=portal_dashboard');
        exit;
    }

    public function profile(): void
    {
        // دسترسی فقط برای لاگین پورتال
        require_portal(); // ⬅️ همین اول متد
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        if ($rid <= 0) {
            header('Location: index.php?page=portal_login');
            exit;
        }

        // اطلاعات ساکن (با توجه به ستون‌های موجود)
        $cols = ['id', 'full_name', 'mobile', 'email', 'national_id', 'type'];
        if ($this->hasColumn('residents', 'portal_last_login'))  $cols[] = 'portal_last_login';
        if ($this->hasColumn('residents', 'portal_enabled'))     $cols[] = 'portal_enabled';
        if ($this->hasColumn('residents', 'is_active'))          $cols[] = 'is_active';

        $sql = "SELECT " . implode(',', array_map(fn($c) => "`$c`", $cols)) . " FROM residents WHERE id=:id";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':id' => $rid]);
        $resident = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        // واحدهای مرتبط با این ساکن
        $unitIds = $this->getResidentUnitIds($rid);
        $units = [];
        if ($unitIds) {
            $in = implode(',', array_map('intval', $unitIds));
            $uCols = ['id', 'name', 'floor'];
            $st2 = $this->pdo->query("SELECT " . implode(',', $uCols) . " FROM units WHERE id IN ($in) ORDER BY name");
            $units = $st2->fetchAll(PDO::FETCH_ASSOC);
        }

        // اگر ownerships داری، تاریخ شروع مالکیت‌های فعال را هم بگیر (اختیاری)
        $ownerships = [];
        if ($this->hasTable('ownerships') && $this->hasColumn('ownerships', 'start_date') && $unitIds) {
            $keyCol = $this->hasColumn('ownerships', 'resident_id') ? 'resident_id'
                : ($this->hasColumn('ownerships', 'owner_id') ? 'owner_id' : null);
            if ($keyCol) {
                $in = implode(',', array_map('intval', $unitIds));
                $st3 = $this->pdo->prepare("
                SELECT unit_id, start_date, end_date
                FROM ownerships
                WHERE `$keyCol`=:rid AND unit_id IN ($in)
                ORDER BY start_date DESC
            ");
                $st3->execute([':rid' => $rid]);
                foreach ($st3->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $ownerships[(int)$row['unit_id']] = $row; // آخرین رکورد هر واحد
                }
            }
        }

        // پاس به ویو
        $page_title = 'پروفایل';
        ob_start();
        include __DIR__ . '/../views/portal/profile.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }


    public function dashboard(): void
    {

        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        $hasFlag = $this->hasColumn('residents', 'portal_must_change');
        if ($hasFlag) {
            $st = $this->pdo->prepare("SELECT portal_must_change FROM residents WHERE id=?");
            $st->execute([$rid]);
            if ((int)$st->fetchColumn() === 1) {
                header('Location: index.php?page=portal_change_pin&force=1');
                exit;
            }
        }

        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        if ($rid <= 0) {
            header('Location: index.php?page=portal_login');
            exit;
        }

        // واحدهای متعلق به این ساکن
        $unitIds = $this->getResidentUnitIds($rid);
        $units   = [];
        if ($unitIds) {
            $in = implode(',', array_map('intval', $unitIds));
            $st = $this->pdo->query("SELECT id,name,floor FROM units WHERE id IN ($in) ORDER BY name");
            $units = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        // ستون/وابستگی‌ها
        $invFk        = $this->paymentsFk();           // مثلا 'invoice_id' یا null
        $payCol       = $this->paymentAmountColumn();  // مثلا 'amount'
        $subjectExpr  = $this->invoiceSubjectExpr();   // i.subject یا ...
        $amountExpr   = $this->invoiceAmountExpr();    // i.amount یا ...
        $issueExpr    = $this->invoiceDateExpr('issue');
        $dueExpr      = $this->invoiceDateExpr('due');
        $statusExpr   = $this->hasColumn('invoices', 'status') ? 'i.status' : 'NULL';
        $periodExpr   = $this->hasColumn('invoices', 'period') ? 'i.period' : 'NULL';

        // فاکتورها + مبلغ پرداختی هر فاکتور
        $invoices = [];
        if ($unitIds) {
            $ids = implode(',', array_map('intval', $unitIds));

            // زیر‌کوئری پرداخت‌ها را فقط برای invoice_idهای غیر NULL جمع بزن
            if ($invFk) {
                $sql = "
                    SELECT
                        i.id,
                        i.unit_id,
                        {$subjectExpr} AS subject,
                        {$amountExpr}  AS invoice_amount,
                        {$issueExpr}   AS issue_date,
                        {$dueExpr}     AS due_date,
                        {$statusExpr}  AS status,
                        {$periodExpr}  AS period,
                        COALESCE(pay.paid_amount, 0) AS paid_amount
                    FROM invoices i
                    LEFT JOIN (
                        SELECT p.`$invFk` AS inv_id, SUM(p.`$payCol`) AS paid_amount
                        FROM payments p
                        WHERE p.`$invFk` IS NOT NULL
                        GROUP BY p.`$invFk`
                    ) pay ON pay.inv_id = i.id
                    WHERE i.unit_id IN ($ids)
                    ORDER BY i.id DESC
                    LIMIT 200";
            } else {
                $sql = "
                    SELECT
                        i.id,
                        i.unit_id,
                        {$subjectExpr} AS subject,
                        {$amountExpr}  AS invoice_amount,
                        {$issueExpr}   AS issue_date,
                        {$dueExpr}     AS due_date,
                        {$statusExpr}  AS status,
                        {$periodExpr}  AS period,
                        0 AS paid_amount
                    FROM invoices i
                    WHERE i.unit_id IN ($ids)
                    ORDER BY i.id DESC
                    LIMIT 200";
            }

            $invoices = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // محاسبهٔ مانده و وضعیت مشتق‌شده (در نبود ستون status)
            $hasStatusCol = $this->hasColumn('invoices', 'status');
            foreach ($invoices as &$row) {
                $invAmt  = (float)($row['invoice_amount'] ?? $row['amount'] ?? 0);
                $paid    = (float)($row['paid_amount']    ?? 0);
                $balance = max(0, round($invAmt - $paid, 2));
                $row['balance'] = $balance;

                // وضعیت نهایی فقط بر اساس مانده/مبلغ پرداختی
                $row['status'] = ($balance <= 0.0) ? 'paid' : (($paid > 0.0) ? 'partial' : 'unpaid');
            }
            unset($row);
        }

        // پرداختی/بدهی مجموعی به‌ازای هر واحد
        $summary = $this->residentUnitSummaries($unitIds, $invFk);

        // پرداخت‌های اخیر (اختیاری)
        $recentPays = [];
        if ($invFk && $unitIds) {
            $ids = implode(',', array_map('intval', $unitIds));
            $sql = "
                SELECT p.id,
                       p.`$payCol` AS amount,
                       " . $this->paymentDateExpr() . " AS pay_date,
                       i.id AS invoice_id,
                       {$subjectExpr} AS subject
                FROM payments p
                JOIN invoices i ON i.id = p.`$invFk`
                WHERE i.unit_id IN ($ids)
                ORDER BY p.id DESC
                LIMIT 20";
            $recentPays = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        include __DIR__ . '/../views/portal/dashboard.php';
    }

    // ---------- Expenses (Portal) ----------
    public function expenses(): void
    {
        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        if ($rid <= 0) {
            header('Location: index.php?page=portal_login');
            exit;
        }

        // فیلتر تاریخ از GET (انتظار Y-m-d از ویجت jdate در hidden)
        $from = trim((string)($_GET['from'] ?? ''));
        $to   = trim((string)($_GET['to']   ?? ''));

        // لیست واحدهای ساکن
        $unitIds = $this->getResidentUnitIds($rid);

        // WHERE
        $conds = [];
        $p = [];

        // فقط هزینه‌های عمومی + واحدهای خودش
        if ($unitIds) {
            $in = implode(',', array_map('intval', $unitIds));
            $conds[] = "(e.unit_id IS NULL OR e.unit_id IN ($in))";
        } else {
            $conds[] = "e.unit_id IS NULL";
        }

        // بازه تاریخ
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $conds[] = "e.expense_date >= :from";
            $p[':from'] = $from;
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $conds[] = "e.expense_date <= :to";
            $p[':to'] = $to;
        }

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        // SELECT — فقط ستون‌های موردنیاز گزارش + amount برای جمع
        $sql = "
            SELECT
              e.id, e.title, e.category, e.amount, e.expense_date, e.spender_name, e.created_at,
              u.name AS unit_name, u.floor
            FROM expenses e
            LEFT JOIN units u ON u.id = e.unit_id
            $where
            ORDER BY e.expense_date DESC, e.id DESC
            LIMIT 1000
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // --- مرتب‌سازی (whitelist) روی آرایه برای یکسان‌بودنِ لیست/چاپ/CSV
        $allowedSorts = ['id', 'title', 'category', 'expense_date', 'spender_name', 'created_at'];
        $sort = (string)($_GET['sort'] ?? 'expense_date');
        if ($sort === 'date') $sort = 'expense_date';
        if (!in_array($sort, $allowedSorts, true)) $sort = 'expense_date';

        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
        if ($dir !== 'asc' && $dir !== 'desc') $dir = 'desc';

        $sortValue = function (array $r, string $key) {
            switch ($key) {
                case 'id':
                    return (int)($r['id'] ?? 0);
                case 'title':
                    return mb_strtolower((string)($r['title'] ?? ''), 'UTF-8');
                case 'category':
                    return mb_strtolower((string)($r['category'] ?? ''), 'UTF-8');
                case 'spender_name':
                    return mb_strtolower((string)($r['spender_name'] ?? ''), 'UTF-8');
                case 'expense_date':
                case 'created_at':
                default:
                    $d = (string)($r[$key] ?? '');
                    $ts = strtotime($d);
                    return $ts !== false ? $ts : -INF;
            }
        };
        usort($rows, function (array $a, array $b) use ($sort, $dir, $sortValue) {
            $va = $sortValue($a, $sort);
            $vb = $sortValue($b, $sort);
            if ($va == $vb) {
                $ia = (int)($a['id'] ?? 0);
                $ib = (int)($b['id'] ?? 0);
                return $dir === 'asc' ? ($ia <=> $ib) : ($ib <=> $ia);
            }
            $cmp = ($va <=> $vb);
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        // جمع بازه (براساس همان WHERE)
        $st2 = $this->pdo->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e $where");
        $st2->execute($p);
        $sum = (float)$st2->fetchColumn();

        // === CSV Export for portal ===
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            // ردیف‌ها را برای CSV کمی غنی کنیم (تاریخ شمسی + برچسب واحد)
            $rowsForCsv = [];
            foreach ($rows as $r) {
                $unitLbl = '';
                if (!empty($r['unit_name'])) {
                    $unitLbl = trim((string)$r['unit_name']);
                    if (isset($r['floor'])) $unitLbl .= ' - طبقه ' . (int)$r['floor'];
                }
                $rowsForCsv[] = [
                    'id'                   => (int)($r['id'] ?? 0),
                    'title'                => (string)($r['title'] ?? ''),
                    'category'             => (string)($r['category'] ?? ''),
                    'amount'               => (float)($r['amount'] ?? 0),
                    'expense_date'         => (string)($r['expense_date'] ?? ''),                 // میلادی
                    'expense_date_jalali'  => (string)jdate($r['expense_date'] ?? null, 'Y/m/d'), // شمسی

                    'spender_name'         => (string)($r['spender_name'] ?? ''),
                ];
            }
            // ترتیب و عناوین ستون‌ها
            $cols = [
                'id'                  => 'شناسه',
                'title'               => 'عنوان',
                'category'            => 'دسته',
                'amount'              => 'مبلغ',
                'expense_date_jalali' => 'تاریخ (شمسی)',
                'expense_date'        => 'تاریخ (میلادی)',

                'spender_name'        => 'هزینه‌کننده',
            ];
            csv_output('expenses_portal_' . date('Ymd_His') . '.csv', $rowsForCsv, $cols);
        }

        // === Print View for portal ===
        if (isset($_GET['view']) && $_GET['view'] === 'print') {
            // از ویوی چاپ مشترک استفاده می‌کنیم (ستون مبلغ + جمع پایین جدول دارد)
            // ورودی‌های موردنیاز: $rows, $sum, $filter  (همین بالا آماده‌اند)
            include __DIR__ . '/../views/expenses/print.php';
            return; // جلوی رندر معمولی را بگیر
        }

        // qsBase برای لینک‌های مرتب‌سازی/چاپ/CSV
        $qs = $_GET;
        unset($qs['p']);
        $qs['page'] = 'portal_expenses';
        $qsBase = http_build_query($qs, '&', '&', PHP_QUERY_RFC3986);

        // رندر با لایوت پورتال
        ob_start();
        include __DIR__ . '/../views/portal/expenses.php';
        $content = ob_get_clean();
        $page_title = 'هزینه‌ها';
        include __DIR__ . '/../views/layout.php';
        return;
    }

    /* ===== اعلان‌ها ===== */
    public function announcements(): void
    {
        require_portal();

        // لیست اعلان‌های قابل نمایش، پین‌شده‌ها اول
        $st = $this->pdo->query("
        SELECT id, title, body, is_pinned, created_at
        FROM announcements
        WHERE visible_in_portal = 1
        ORDER BY is_pinned DESC, created_at DESC
        LIMIT 200
    ");
        $items = $st->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        $page_title = 'اعلان‌ها';
        include __DIR__ . '/../views/portal/announcements.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    /* ===== تیکت‌ها ===== */
    public function tickets(): void
    {
        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);

        $st = $this->pdo->prepare("
        SELECT id, subject, status, created_at, updated_at
        FROM tickets
        WHERE resident_id = :rid
        ORDER BY FIELD(status,'open','pending','closed'), updated_at DESC, created_at DESC
        LIMIT 200
    ");
        $st->execute([':rid' => $rid]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        $page_title = 'تیکت‌های من';
        include __DIR__ . '/../views/portal/tickets/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function ticketNew(): void
    {
        require_portal();
        ob_start();
        $page_title = 'ثبت تیکت جدید';
        include __DIR__ . '/../views/portal/tickets/new.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function ticketStore(): void
    {
        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);

        $subject = trim((string)($_POST['subject'] ?? ''));
        $body    = trim((string)($_POST['body'] ?? ''));

        if ($subject === '' || $body === '') {
            $_SESSION['error'] = 'عنوان و توضیحات الزامی هستند.';
            header('Location: index.php?page=portal_ticket_new');
            exit;
        }

        $filePath = $this->saveUpload('file', 'tickets');

        $st = $this->pdo->prepare("
        INSERT INTO tickets (resident_id, subject, body, status, file_path)
        VALUES (:rid, :s, :b, 'open', :f)
    ");
        $st->execute([
            ':rid' => $rid,
            ':s' => $subject,
            ':b' => $body,
            ':f' => $filePath
        ]);

        $_SESSION['ok'] = 'تیکت شما ثبت شد.';
        header('Location: index.php?page=portal_tickets');
        exit;
    }

    public function ticketShow(): void
    {
        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        $id  = (int)($_GET['id'] ?? 0);

        // تیکت فقط اگر مال کاربر است
        $st = $this->pdo->prepare("SELECT * FROM tickets WHERE id=:id AND resident_id=:rid");
        $st->execute([':id' => $id, ':rid' => $rid]);
        $ticket = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            http_response_code(404);
            die('تیکت یافت نشد.');
        }

        $msg = $this->pdo->prepare("
        SELECT sender_type, message, file_path, created_at
        FROM ticket_messages
        WHERE ticket_id=:id
        ORDER BY id ASC
    ");
        $msg->execute([':id' => $id]);
        $messages = $msg->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        $page_title = 'جزئیات تیکت';
        include __DIR__ . '/../views/portal/tickets/show.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layout.php';
    }

    public function ticketReply(): void
    {
        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        $id  = (int)($_POST['id'] ?? 0);
        $body = trim((string)($_POST['message'] ?? ''));

        // مالکیت تیکت
        $st = $this->pdo->prepare("SELECT id, resident_id, status FROM tickets WHERE id=:id AND resident_id=:rid");
        $st->execute([':id' => $id, ':rid' => $rid]);
        $ticket = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            http_response_code(404);
            die('تیکت یافت نشد.');
        }

        if ($body === '') {
            $_SESSION['error'] = 'پیام نمی‌تواند خالی باشد.';
            header('Location: index.php?page=portal_ticket_show&id=' . $id);
            exit;
        }

        $filePath = $this->saveUpload('file', 'tickets');

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, sender_type, message, file_path)
            VALUES (:tid, 'resident', :m, :f)
        ");
            $ins->execute([':tid' => $id, ':m' => $body, ':f' => $filePath]);

            // وضع را اگر بسته است، به pending برگردانیم (اختیاری)
            if ($ticket['status'] === 'closed') {
                $up = $this->pdo->prepare("UPDATE tickets SET status='pending' WHERE id=:id");
                $up->execute([':id' => $id]);
            } else {
                $up = $this->pdo->prepare("UPDATE tickets SET status='pending' WHERE id=:id AND status='open'");
                $up->execute([':id' => $id]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $_SESSION['error'] = 'اشکال در ثبت پیام.';
        }

        header('Location: index.php?page=portal_ticket_show&id=' . $id);
        exit;
    }

    public function ticketClose(): void
    {
        require_portal();
        $rid = (int)($_SESSION['portal_resident_id'] ?? 0);
        $id  = (int)($_POST['id'] ?? 0);

        $st = $this->pdo->prepare("UPDATE tickets SET status='closed' WHERE id=:id AND resident_id=:rid");
        $st->execute([':id' => $id, ':rid' => $rid]);

        $_SESSION['ok'] = 'تیکت بسته شد.';
        header('Location: index.php?page=portal_ticket_show&id=' . $id);
        exit;
    }

    /* ===== آپلود امن فایل (مشترک) ===== */
    private function saveUpload(string $field, string $subdir): ?string
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;

        // محدودیت 5MB
        if (($f['size'] ?? 0) > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'حجم فایل بیش از حد مجاز است (حداکثر ۵ مگابایت).';
            return null;
        }

        // MIME مجاز: تصاویر و PDF
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $_SESSION['error'] = 'فرمت فایل مجاز نیست.';
            return null;
        }

        // مسیر ذخیره
        $base = __DIR__ . '/../uploads';
        if (!is_dir($base)) @mkdir($base, 0775, true);
        $dir  = $base . '/' . trim($subdir, '/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        // نام امن
        $ext = ($mime === 'application/pdf') ? 'pdf' : (explode('/', $mime)[1] ?? 'bin');
        $safeName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $dir . '/' . $safeName;

        if (!move_uploaded_file($f['tmp_name'], $destPath)) {
            $_SESSION['error'] = 'ذخیره فایل ناموفق بود.';
            return null;
        }

        // مسیر نسبی برای لینک‌دهی
        return 'controllers/../uploads/' . trim($subdir, '/') . '/' . $safeName;
    }


    // ---------- Helpers ----------
    private function hasColumn(string $table, string $col): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
                                   LIMIT 1");
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    }
    private function hasTable(string $table): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM information_schema.TABLES
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
                                   LIMIT 1");
        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
    }

    private function normalizeDigits(string $s): string
    {
        $map = [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9'
        ];
        return str_replace([' ', '-', '(', ')'], '', strtr(trim($s), $map));
    }

    private function findResidentByMobilePin(string $mobile, string $pin): ?array
    {
        $target = $this->normalizeMobileCanonical($mobile);
        if ($target === '') return null;

        // جست‌وجوی اولیه با ۷ رقم آخر، بعد تطبیق دقیق در PHP
        $last7 = substr($target, -7);
        $st = $this->pdo->prepare("SELECT * FROM residents WHERE mobile LIKE :m ORDER BY id DESC LIMIT 20");
        $st->execute([':m' => '%' . $last7 . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $canon = $this->normalizeMobileCanonical($row['mobile'] ?? '');
            if ($canon !== $target) continue;

            // اگر ستون PIN داریم، هر دو حالت plain و bcrypt را قبول کن
            if ($this->hasColumn('residents', 'portal_pin')) {
                $dbPin = (string)($row['portal_pin'] ?? '');
                if ($dbPin === '') continue;
                if ($dbPin === $pin) return $row; // plain
                if (preg_match('/^\$2y\$/', $dbPin) && password_verify($pin, $dbPin)) return $row; // bcrypt
                continue;
            } else {
                // حالت تست بدون ستون PIN
                if ($pin === '0000') return $row;
            }
        }
        return null;
    }

    private function paymentsFk(): ?string
    {
        foreach (['invoice_id', 'invoiceId', 'invoice', 'factor_id', 'bill_id'] as $c) {
            if ($this->hasColumn('payments', $c)) return $c;
        }
        return null;
    }
    private function paymentAmountColumn(): string
    {
        foreach (['amount', 'price', 'total', 'value'] as $c) {
            if ($this->hasColumn('payments', $c)) return $c;
        }
        return 'amount';
    }
    private function paymentDateExpr(): string
    {
        foreach (['pay_date', 'date', 'paid_at', 'created_at', 'created'] as $c) {
            if ($this->hasColumn('payments', $c)) return "p.`$c`";
        }
        return "NULL";
    }
    private function invoiceSubjectExpr(): string
    {
        foreach (['subject', 'title', 'description', 'name'] as $c) {
            if ($this->hasColumn('invoices', $c)) return "i.`$c`";
        }
        return "CONCAT('فاکتور #', i.id)";
    }
    private function invoiceAmountExpr(): string
    {
        foreach (['total_amount', 'amount', 'total', 'price'] as $c) {
            if ($this->hasColumn('invoices', $c)) return "i.`$c`";
        }
        return "0";
    }

    private function invoiceDateExpr(string $kind): string
    {
        if ($kind === 'issue') {
            foreach (['issue_date', 'created_at', 'created', 'date'] as $c)
                if ($this->hasColumn('invoices', $c)) return "i.`$c`";
        } else {
            foreach (['due_date', 'deadline', 'due'] as $c)
                if ($this->hasColumn('invoices', $c)) return "i.`$c`";
        }
        return "NULL";
    }

    private function getResidentUnitIds(int $resident_id): array
    {
        // ownerships
        if ($this->hasTable('ownerships') && $this->hasColumn('ownerships', 'unit_id')) {
            $rc = $this->hasColumn('ownerships', 'resident_id') ? 'resident_id'
                : ($this->hasColumn('ownerships', 'owner_id') ? 'owner_id' : null);
            if ($rc) {
                $st = $this->pdo->prepare("SELECT unit_id FROM ownerships WHERE `$rc`=:r");
                $st->execute([':r' => $resident_id]);
                $ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'unit_id'));
                if ($ids) return array_values(array_unique($ids));
            }
        }
        // units.resident_id / units.owner_id
        if ($this->hasColumn('units', 'resident_id')) {
            $st = $this->pdo->prepare("SELECT id FROM units WHERE resident_id=:r");
            $st->execute([':r' => $resident_id]);
            $ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
            if ($ids) return $ids;
        }
        if ($this->hasColumn('units', 'owner_id')) {
            $st = $this->pdo->prepare("SELECT id FROM units WHERE owner_id=:r");
            $st->execute([':r' => $resident_id]);
            $ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
            if ($ids) return $ids;
        }
        return [];
    }

    private function residentUnitSummaries(array $unitIds, ?string $invFk): array
    {
        if (!$unitIds) return [];
        $ids = implode(',', array_map('intval', $unitIds));
        $amountExpr = $this->invoiceAmountExpr();   // باید با i. برگردد
        $payCol     = $this->paymentAmountColumn(); // مثلا amount

        if ($invFk) {
            $sql = "
                SELECT u.id AS unit_id, u.name AS unit_name, u.floor,
                       COALESCE(SUM({$amountExpr}),0) AS billed,
                       COALESCE((
                           SELECT SUM(p.`$payCol`)
                           FROM payments p
                           JOIN invoices i2 ON i2.id = p.`$invFk`
                           WHERE i2.unit_id = u.id
                       ),0) AS paid
                FROM units u
                LEFT JOIN invoices i ON i.unit_id = u.id
                WHERE u.id IN ($ids)
                GROUP BY u.id
                ORDER BY u.name
            ";
        } else {
            $sql = "
                SELECT u.id AS unit_id, u.name AS unit_name, u.floor,
                       COALESCE(SUM({$amountExpr}),0) AS billed,
                       0 AS paid
                FROM units u
                LEFT JOIN invoices i ON i.unit_id = u.id
                WHERE u.id IN ($ids)
                GROUP BY u.id
                ORDER BY u.name
            ";
        }

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['due'] = max(0, (float)$r['billed'] - (float)$r['paid']);
        }
        return $rows;
    }

    private function normalizeMobileCanonical(string $s): string
    {
        // ارقام فارسی/عربی → لاتین + حذف هر چی غیر رقم
        $d = $this->normalizeDigits($s);
        $d = preg_replace('/\D+/', '', $d);
        if ($d === '') return '';
        // +98XXXXXXXXXX → 0XXXXXXXXXX
        if (substr($d, 0, 2) === '98' && strlen($d) >= 12) {
            $d = '0' . substr($d, 2, 10);
        } elseif (strlen($d) === 10 && $d[0] === '9') {
            // 9XXXXXXXXX → 09XXXXXXXXX
            $d = '0' . $d;
        } elseif (strlen($d) > 11) {
            // اگر طول زیاد بود، ۱۱ رقم آخر
            $d = substr($d, -11);
        }
        return $d;
    }
}
