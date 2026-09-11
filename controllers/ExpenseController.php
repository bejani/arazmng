<?php
// controllers/ExpenseController.php
declare(strict_types=1);

require_once __DIR__ . '/../models/Expense.php';
require_once __DIR__ . '/../views/_helpers.php';

final class ExpenseController
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /** مقدار قابل‌مقایسه برای مرتب‌سازی روی یکی از کلیدهای مجاز */
    private function sortValue(array $r, string $key)
    {
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
    }

    public function index(): void
    {
        // --- فیلترها
        $filter = [
            'from'     => normalize_date_input($_GET['from'] ?? ''), // شمسی/میلادی → Y-m-d
            'to'       => normalize_date_input($_GET['to']   ?? ''),
            'category' => trim((string)($_GET['category'] ?? '')),
            'unit_id'  => ($_GET['unit_id'] ?? '') !== '' ? (int)$_GET['unit_id'] : '',
            'spender'  => trim((string)($_GET['spender'] ?? '')),
        ];

        // --- مرتب‌سازی (فقط کلیدهای مجاز)
        $allowedSorts = ['id', 'title', 'category', 'expense_date', 'spender_name', 'created_at'];
        $sort = (string)($_GET['sort'] ?? 'expense_date');
        if ($sort === 'date') $sort = 'expense_date'; // سازگاری
        if (!in_array($sort, $allowedSorts, true)) $sort = 'expense_date';

        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
        if ($dir !== 'asc' && $dir !== 'desc') $dir = 'desc';

        // از مدل لیست را می‌گیریم
        $rows = Expense::listWithFilter($this->pdo, $filter, $sort, $dir);
        $sum  = Expense::sumWithFilter($this->pdo, $filter);

        // مرتب‌سازی نهایی روی آرایه (یکسان برای لیست/چاپ/CSV)
        usort($rows, function (array $a, array $b) use ($sort, $dir) {
            $va = $this->sortValue($a, $sort);
            $vb = $this->sortValue($b, $sort);
            if ($va == $vb) {
                // tie-breaker: id DESC
                $ia = (int)($a['id'] ?? 0);
                $ib = (int)($b['id'] ?? 0);
                return $dir === 'asc' ? ($ia <=> $ib) : ($ib <=> $ia);
            }
            $cmp = ($va <=> $vb);
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        // --- لیست دسته‌ها (برای فیلتر و فرم)
        $categories = [];
        try {
            $st = $this->pdo->query("SELECT name FROM expense_categories ORDER BY name");
            $categories = array_map(fn($r) => $r['name'], $st->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            $st = $this->pdo->query("SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category<>'' ORDER BY category");
            $categories = array_map(fn($r) => $r['category'], $st->fetchAll(PDO::FETCH_ASSOC));
        }

        // --- واحدها و ساکنان (برای فرم/فیلتر)
        $units = $this->pdo->query("SELECT id,name,floor FROM units ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $residents = $this->pdo->query("SELECT id,full_name FROM residents WHERE is_active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

        // === Print View (standalone)
        if (isset($_GET['view']) && $_GET['view'] === 'print') {
            // این ویو HTML کامل + Bootstrap CDN دارد؛ لای‌اوت لازم نیست
            $candidates = [
                __DIR__ . '/../views/expenses/print.php',   // مسیر مرسوم
                __DIR__ . '/../views/expensee/print.php',   // اگر پوشه‌ات expensee است
            ];
            foreach ($candidates as $pp) {
                if (is_file($pp)) {
                    include $pp;
                    return;
                }
            }
            http_response_code(500);
            echo 'Print view not found (expected at views/expenses/print.php or views/expensee/print.php).';
            return;
        }

        // --- CSV (فقط ستون‌های موردنظر)
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $rowsForCsv = [];
            $sumAmount  = 0.0;

            foreach ($rows as $r) {
                $amt = (float)($r['amount'] ?? 0);
                $sumAmount += $amt;

                $rowsForCsv[] = [
                    'id'                  => (int)($r['id'] ?? 0),
                    'title'               => (string)($r['title'] ?? ''),
                    'category'            => (string)($r['category'] ?? ''),
                    'amount'              => $amt,
                    'expense_date_jalali' => (string)jdate($r['expense_date'] ?? null, 'Y/m/d'),
                    'expense_date'        => (string)($r['expense_date'] ?? ''),
                    'spender_name'        => (string)($r['spender_name'] ?? ''),
                    'created_at'          => (string)($r['created_at'] ?? ''),
                    'notes'               => (string)($r['notes'] ?? ($r['note'] ?? '')),
                ];
            }

            // ردیف خالی + ردیف جمع
            $rowsForCsv[] = [
                'id' => '',
                'title' => '',
                'category' => '',
                'amount' => '',
                'expense_date_jalali' => '',
                'expense_date' => '',
                'spender_name' => '',
                'created_at' => '',
                'notes' => ''
            ];
            $rowsForCsv[] = [
                'id' => '',
                'title' => '',
                'category' => 'جمع',
                'amount' => $sumAmount,
                'expense_date_jalali' => '',
                'expense_date' => '',
                'spender_name' => '',
                'created_at' => '',
                'notes' => ''
            ];

            $cols = [
                'id'                  => 'شناسه',
                'title'               => 'عنوان',
                'category'            => 'دسته',
                'amount'              => 'مبلغ',
                'expense_date_jalali' => 'تاریخ (شمسی)',
                'expense_date'        => 'تاریخ (میلادی)',
                'spender_name'        => 'هزینه‌کننده',
                'created_at'          => 'ثبت',
                'notes'               => 'توضیح',
            ];

            csv_output('expenses_admin_' . date('Ymd_His') . '.csv', $rowsForCsv, $cols);
            return;
        }

        // --- ویوی لیست
        include __DIR__ . '/../views/expenses/index.php';
    }

    public function store(): void
    {
        $_POST['expense_date'] = normalize_date_input($_POST['expense_date'] ?? '');
        if (empty($_POST['spender_name']) && !empty($_POST['spender_id'])) {
            $st = $this->pdo->prepare("SELECT full_name FROM residents WHERE id=?");
            $st->execute([(int)$_POST['spender_id']]);
            $_POST['spender_name'] = (string)$st->fetchColumn();
        }
        Expense::create($this->pdo, $_POST);
        header('Location: index.php?page=expenses');
        exit;
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $row = Expense::find($this->pdo, $id);
        if (!$row) {
            die('هزینه پیدا نشد');
        }

        $categories = [];
        try {
            $st = $this->pdo->query("SELECT name FROM expense_categories ORDER BY name");
            $categories = array_map(fn($r) => $r['name'], $st->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
        }

        $units = $this->pdo->query("SELECT id,name,floor FROM units ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $residents = $this->pdo->query("SELECT id,full_name FROM residents WHERE is_active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../views/expenses/edit.php';
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $_POST['expense_date'] = normalize_date_input($_POST['expense_date'] ?? '');
        if (empty($_POST['spender_name']) && !empty($_POST['spender_id'])) {
            $st = $this->pdo->prepare("SELECT full_name FROM residents WHERE id=?");
            $st->execute([(int)$_POST['spender_id']]);
            $_POST['spender_name'] = (string)$st->fetchColumn();
        }
        Expense::update($this->pdo, $id, $_POST);
        header('Location: index.php?page=expenses');
        exit;
    }

    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        Expense::delete($this->pdo, $id);
        header('Location: index.php?page=expenses');
        exit;
    }
}