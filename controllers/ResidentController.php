<?php
// controllers/ResidentController.php
declare(strict_types=1);

require_once __DIR__ . "/../models/Resident.php";

error_log(message: "STORE HIT with POST keys: " . implode(',', array_keys($_POST)));


class ResidentController
{
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo instanceof PDO) {
            $this->pdo = $pdo;
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /** گرفتن PDO از سازنده یا آرگومان متد (سازگار با هر دو سبک روتر) */
    private function db(?PDO $maybe = null): PDO
    {
        if ($maybe instanceof PDO) {
            $this->pdo = $maybe;
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('PDO connection is missing.');
        }
        return $this->pdo;
    }

    /** بررسی وجود ستون در جدول (برای پشتیبانی اختیاری portal_pin) */
    private function hasColumn(string $table, string $col): bool
    {
        $db = $this->db();
        $st = $db->prepare("SELECT 1 FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c
                           LIMIT 1");
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    }

    /* ========== List ========== */
    public function index(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);

        /* ---------- ورودی‌های فیلتر و مرتب‌سازی ---------- */
        $q         = trim((string)($_GET['q'] ?? ''));
        $activeStr = (string)($_GET['active'] ?? 'all');
        $hasUnit   = isset($_GET['has_unit']) && $_GET['has_unit'] === '1';

        $sort = (string)($_GET['sort'] ?? 'full_name');
        $dir  = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['id', 'full_name', 'mobile', 'is_active', 'unit'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'full_name';

        /* ---------- واکشی ساکنان با فیلتر پایه ---------- */
        $conds = [];
        $params = [];
        if ($q !== '') {
            $conds[] = "(full_name LIKE :q OR mobile LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($activeStr === '1' || $activeStr === '0') {
            $conds[] = "is_active = :a";
            $params[':a'] = (int)$activeStr;
        }
        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
        $st = $db->prepare("SELECT id, full_name, mobile, is_active FROM residents $where");
        $st->execute($params);
        $residents = $st->fetchAll(PDO::FETCH_ASSOC);

        /* ---------- Helpers کشف اسکیمای واحد/مالکیت ---------- */
        $hasTable = function (string $t) use ($db): bool {
            $st = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t LIMIT 1");
            $st->execute([':t' => $t]);
            return (bool)$st->fetchColumn();
        };
        $hasCol = function (string $t, string $c) use ($db): bool {
            $st = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
            $st->execute([':t' => $t, ':c' => $c]);
            return (bool)$st->fetchColumn();
        };

        /* ---------- ساخت برچسب «واحد / طبقه» + شناسه‌های واحد ---------- */
        $unit_labels = [];                 // [resident_id => 'نام - طبقه …، …']
        $unit_units  = [];                 // [resident_id => [ ['id'=>unit_id,'label'=>"..."], ... ]]

        if ($hasTable('ownerships') && $hasCol('ownerships', 'unit_id')) {
            if ($hasCol('ownerships', 'resident_id')) {
                $sql = "SELECT o.resident_id AS rid, u.id AS unit_id, u.name, u.floor
                        FROM ownerships o JOIN units u ON u.id=o.unit_id
                        WHERE o.resident_id IS NOT NULL";
                foreach ($db->query($sql) as $r) {
                    $rid = (int)$r['rid'];
                    if (!$rid) continue;
                    $nm = trim((string)($r['name'] ?? ''));
                    $fl = isset($r['floor']) ? (' - طبقه ' . (int)$r['floor']) : '';
                    if ($nm !== '') {
                        $unit_units[$rid][] = ['id' => (int)$r['unit_id'], 'label' => $nm . $fl];
                        $unit_labels[$rid][] = $nm . $fl;
                    }
                }
            }
            if ($hasCol('ownerships', 'owner_id')) {
                $sql = "SELECT o.owner_id AS rid, u.id AS unit_id, u.name, u.floor
                        FROM ownerships o JOIN units u ON u.id=o.unit_id
                        WHERE o.owner_id IS NOT NULL";
                foreach ($db->query($sql) as $r) {
                    $rid = (int)$r['rid'];
                    if (!$rid) continue;
                    $nm = trim((string)($r['name'] ?? ''));
                    $fl = isset($r['floor']) ? (' - طبقه ' . (int)$r['floor']) : '';
                    if ($nm !== '') {
                        $unit_units[$rid][] = ['id' => (int)$r['unit_id'], 'label' => $nm . $fl];
                        $unit_labels[$rid][] = $nm . $fl;
                    }
                }
            }
        } else {
            if ($hasCol('units', 'resident_id')) {
                $sql = "SELECT resident_id AS rid, id AS unit_id, name, floor FROM units WHERE resident_id IS NOT NULL";
                foreach ($db->query($sql) as $r) {
                    $rid = (int)$r['rid'];
                    if (!$rid) continue;
                    $nm = trim((string)($r['name'] ?? ''));
                    $fl = isset($r['floor']) ? (' - طبقه ' . (int)$r['floor']) : '';
                    if ($nm !== '') {
                        $unit_units[$rid][] = ['id' => (int)$r['unit_id'], 'label' => $nm . $fl];
                        $unit_labels[$rid][] = $nm . $fl;
                    }
                }
            }
            if ($hasCol('units', 'owner_id')) {
                $sql = "SELECT owner_id AS rid, id AS unit_id, name, floor FROM units WHERE owner_id IS NOT NULL";
                foreach ($db->query($sql) as $r) {
                    $rid = (int)$r['rid'];
                    if (!$rid) continue;
                    $nm = trim((string)($r['name'] ?? ''));
                    $fl = isset($r['floor']) ? (' - طبقه ' . (int)$r['floor']) : '';
                    if ($nm !== '') {
                        $unit_units[$rid][] = ['id' => (int)$r['unit_id'], 'label' => $nm . $fl];
                        $unit_labels[$rid][] = $nm . $fl;
                    }
                }
            }
        }
        // یکتا و به رشته
        foreach ($unit_labels as $rid => $list) {
            $unit_labels[$rid] = implode('، ', array_unique($list));
        }
        // یکتاسازی واحدها (با id)
        foreach ($unit_units as $rid => $list) {
            $seen = [];
            $clean = [];
            foreach ($list as $x) {
                if (!isset($seen[$x['id']])) {
                    $clean[] = $x;
                    $seen[$x['id']] = true;
                }
            }
            $unit_units[$rid] = $clean;
        }

        /* ---------- فیلتر «فقط دارای واحد» ---------- */
        if ($hasUnit) {
            $residents = array_values(array_filter($residents, function ($r) use ($unit_units) {
                $rid = (int)($r['id'] ?? 0);
                return !empty($unit_units[$rid]);
            }));
        }

        /* ---------- مرتب‌سازی ---------- */
        $keyFn = function (array $r) use ($unit_labels, $sort): array|string|int {
            $id = (int)($r['id'] ?? 0);
            return match ($sort) {
                'id'         => $id,
                'mobile'     => (string)($r['mobile'] ?? ''),
                'is_active'  => (int)($r['is_active'] ?? 0),
                'unit'       => mb_strtolower((string)($unit_labels[$id] ?? ''), 'UTF-8'),
                default      => mb_strtolower((string)($r['full_name'] ?? ''), 'UTF-8'),
            };
        };
        usort($residents, function ($a, $b) use ($keyFn, $dir) {
            $ka = $keyFn($a);
            $kb = $keyFn($b);
            $cmp = (is_int($ka) && is_int($kb)) ? ($ka <=> $kb) : strcmp((string)$ka, (string)$kb);
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        /* ---------- ارسال به ویو ---------- */
        $unit_labels_for_view = $unit_labels;
        $unit_units_for_view  = $unit_units;   // 👈 جدید
        $current_sort = $sort;
        $current_dir = $dir;
        $current_filters = ['q' => $q, 'active' => $activeStr, 'has_unit' => $hasUnit ? '1' : ''];

        include __DIR__ . "/../views/residents/index.php";
    }



    /* ========== Create ========== */
    public function create(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);

        $has_portal_pin = $this->hasColumn('residents', 'portal_pin');
        include __DIR__ . '/../views/residents/create.php';
    }

    public function store(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);
        error_log("RESIDENT_STORE called; DB=" . ($db->query('SELECT DATABASE()')->fetchColumn() ?: '?'));

        $data = $_POST;

        // یکتایی موبایل (در صورت پر بودن)
        if (!empty($data['mobile'])) {
            $chk = $db->prepare("SELECT COUNT(*) FROM residents WHERE mobile = ?");
            $chk->execute([$data['mobile']]);
            if ((int)$chk->fetchColumn() > 0) {
                die("شماره موبایل تکراری است.");
            }
        }

        // اگر فیلد portal_pin در فرم آمده و ستون در DB وجود دارد، bcrypt کن
        if (!empty($data['portal_pin']) && $this->hasColumn('residents', 'portal_pin')) {
            $data['portal_pin'] = password_hash((string)$data['portal_pin'], PASSWORD_BCRYPT);
        } else {
            unset($data['portal_pin']); // خالی یا ستون موجود نیست
        }

        if ($this->hasColumn('residents', 'portal_must_change')) {
            $_POST['portal_must_change'] = 1;
        }


        try {
            $id = Resident::create($db, $data);
            error_log("RESIDENT_STORE inserted id=$id");
            $_SESSION['ok'] = "ساکن جدید ذخیره شد. (ID $id)";
            header("Location: index.php?page=residents");
        } catch (Throwable $e) {
            http_response_code(500);
            die('خطا در ذخیره: ' . $e->getMessage());
        }
    }



    /* ========== Edit Form ========== */
    public function edit(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);
        $id = (int)($_GET['id'] ?? 0);

        $stmt = $db->prepare("SELECT * FROM residents WHERE id = ?");
        $stmt->execute([$id]);
        $resident = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resident) {
            die("ساکن یافت نشد");
        }
        include __DIR__ . "/../views/residents/edit.php";
    }

    /* ========== Update ========== */
    public function update(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);

        $id        = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $mobile    = trim($_POST['mobile'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $national  = trim($_POST['national_id'] ?? '');
        $type      = ($_POST['type'] ?? 'owner');

        if (array_key_exists('is_active', $_POST)) {
            $is_active = (int)($_POST['is_active'] ? 1 : 0);
        } else {
            // اگر فیلد ارسال نشد (مثلاً در نسخه‌های قدیمی فرم)، مقدار فعلی دیتابیس را نگه دار
            $cur = $db->prepare("SELECT is_active FROM residents WHERE id = ?");
            $cur->execute([$id]);
            $is_active = (int)$cur->fetchColumn();
        }

        if ($full_name === '') {
            die("نام کامل الزامی است.");
        }

        // موبایل یکتا (به جز خود رکورد) اگر خالی نیست
        if ($mobile !== '') {
            $chk = $db->prepare("SELECT COUNT(*) FROM residents WHERE mobile = ? AND id <> ?");
            $chk->execute([$mobile, $id]);
            if ((int)$chk->fetchColumn() > 0) {
                die("شماره موبایل تکراری است.");
            }
        }

        $pin = trim($_POST['portal_pin'] ?? '');

        if ($pin !== '' && $this->hasColumn('residents', 'portal_pin')) {
            // با PIN
            $stmt = $db->prepare("
            UPDATE residents
            SET full_name=?, mobile=?, email=?, national_id=?, type=?, is_active=?, portal_pin=?
            WHERE id=?
        ");
            $stmt->execute([
                $full_name,
                $mobile ?: null,
                $email ?: null,
                $national ?: null,
                $type,
                $is_active,
                password_hash($pin, PASSWORD_BCRYPT),
                $id
            ]);
        } else {
            // بدون تغییر PIN
            $stmt = $db->prepare("
            UPDATE residents
            SET full_name=?, mobile=?, email=?, national_id=?, type=?, is_active=?
            WHERE id=?
        ");
            $stmt->execute([
                $full_name,
                $mobile ?: null,
                $email ?: null,
                $national ?: null,
                $type,
                $is_active,
                $id
            ]);
        }

        $_SESSION['ok'] = 'اطلاعات ساکن بروزرسانی شد.';
        header("Location: index.php?page=residents");
    }


    /* ========== Toggle Active ========== */
    public function toggleActive(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $db->prepare("UPDATE residents SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: index.php?page=residents");
    }

    /* ========== Delete (safe) ========== */
    public function delete(?PDO $pdo = null): void
    {
        $db = $this->db($pdo);
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $stmt = $db->prepare("DELETE FROM residents WHERE id = ?");
            $stmt->execute([$id]);
        } catch (Throwable $e) {
            // اگر FK اجازه حذف نمی‌دهد، غیرفعال کن
            $stmt = $db->prepare("UPDATE residents SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
        }

        header("Location: index.php?page=residents");
    }
    private function hasTable(string $table): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM information_schema.TABLES
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t LIMIT 1");
        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
    }
    private function residentUnitsLabel(int $residentId): string
    {
        // اگر ownerships داری، از اون استفاده کن (پشتیبانی از resident_id یا owner_id)
        if ($this->hasTable('ownerships')) {
            $sql = "
                SELECT u.name, u.floor
                FROM ownerships o
                JOIN units u ON u.id = o.unit_id
                WHERE (o.resident_id = :id OR o.owner_id = :id)
                ORDER BY u.name
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $residentId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // فالبک: اگر ستون resident_id/owner_id روی جدول units استفاده می‌کنی
            $sql = "
                SELECT name, floor
                FROM units
                WHERE (resident_id = :id OR owner_id = :id)
                ORDER BY name
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $residentId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!$rows) return '—';
        $labels = [];
        foreach ($rows as $r) {
            $nm = trim((string)($r['name'] ?? ''));
            $fl = isset($r['floor']) ? (' - طبقه ' . (int)$r['floor']) : '';
            if ($nm !== '') $labels[] = $nm . $fl;
        }
        return $labels ? implode('، ', $labels) : '—';
    }
}
