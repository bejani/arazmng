<?php
// controllers/UnitController.php
// وابسته به db.php که $pdo (PDO) را فراهم می‌کند.
class UnitController {
    private PDO $pdo;
    private bool $hasActive;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->hasActive = $this->hasColumn('units', 'is_active');
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    private function hasColumn(string $table, string $col): bool {
        $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':t'=>$table, ':c'=>$col]);
        return (bool)$st->fetchColumn();
    }

    public function index(): void {
        // مرتب‌سازی امن با لیست سفید
        $allowed = ['name'=>'name', 'floor'=>'floor'];
        if ($this->hasActive) $allowed['is_active'] = 'is_active';
        $sort = $_GET['sort'] ?? 'name';
        $dir  = (isset($_GET['dir']) && strtolower($_GET['dir'])==='desc') ? 'DESC' : 'ASC';
        $orderCol = $allowed[$sort] ?? 'name';

        $cols = "id, name, floor" . ($this->hasActive ? ", is_active" : "");
        $sql = "SELECT $cols FROM units ORDER BY $orderCol $dir LIMIT 1000";
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (!$this->hasActive) { // برای سازگاری با ویو
            foreach ($rows as &$r) { $r['is_active'] = 1; }
        }
        $units = $rows;
        include __DIR__ . '/../views/units/index.php';
    }

    public function edit(): void {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $unit = null;
        if ($id) {
            $cols = "id, name, floor" . ($this->hasActive ? ", is_active" : "");
            $st = $this->pdo->prepare("SELECT $cols FROM units WHERE id=:id");
            $st->execute([':id'=>$id]);
            $unit = $st->fetch(PDO::FETCH_ASSOC);
            if ($unit && !$this->hasActive) $unit['is_active'] = 1;
        }
        include __DIR__ . '/../views/units/form.php';
    }

    public function store(): void {
        $name = trim($_POST['name'] ?? '');
        $floor_raw = trim($_POST['floor'] ?? '');
        $floor = ($floor_raw === '') ? 0 : (int)$floor_raw;
        if ($name === '') {
            $_SESSION['error'] = 'نام واحد الزامی است.';
            header("Location: index.php?page=unit_edit");
            exit;
        }
        if ($this->hasActive) {
            $st = $this->pdo->prepare("INSERT INTO units (name, floor, is_active) VALUES (:name, :floor, 1)");
        } else {
            $st = $this->pdo->prepare("INSERT INTO units (name, floor) VALUES (:name, :floor)");
        }
        $st->execute([':name'=>$name, ':floor'=>$floor]);
        $_SESSION['ok'] = 'واحد ایجاد شد.';
        header("Location: index.php?page=units");
        exit;
    }

    public function update(): void {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $floor_raw = trim($_POST['floor'] ?? '');
        $floor = ($floor_raw === '') ? 0 : (int)$floor_raw;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if ($id<=0 || $name==='') {
            $_SESSION['error'] = 'نام واحد الزامی است.';
            header("Location: index.php?page=unit_edit&id=".$id);
            exit;
        }

        if ($this->hasActive) {
            $st = $this->pdo->prepare("UPDATE units SET name=:name, floor=:floor, is_active=:a WHERE id=:id");
            $st->execute([':name'=>$name, ':floor'=>$floor, ':a'=>$is_active, ':id'=>$id]);
        } else {
            $st = $this->pdo->prepare("UPDATE units SET name=:name, floor=:floor WHERE id=:id");
            $st->execute([':name'=>$name, ':floor'=>$floor, ':id'=>$id]);
        }

        $_SESSION['ok'] = 'واحد بروزرسانی شد.';
        header("Location: index.php?page=units");
        exit;
    }

    public function toggleActive(): void {
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { header("Location: index.php?page=units"); exit; }

        if (!$this->hasActive) {
            $_SESSION['error'] = 'ستون is_active در جدول units وجود ندارد.';
            header("Location: index.php?page=units");
            exit;
        }
        $this->pdo->prepare("UPDATE units SET is_active = 1 - is_active WHERE id=:id")->execute([':id'=>$id]);
        header("Location: index.php?page=units");
        exit;
    }

    public function delete(): void {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id>0) {
            $st = $this->pdo->prepare("DELETE FROM units WHERE id=:id");
            $st->execute([':id'=>$id]);
            $_SESSION['ok'] = 'واحد حذف شد.';
        }
        header("Location: index.php?page=units");
        exit;
    }
}