<?php
// models/Expense.php
declare(strict_types=1);

final class Expense
{
    /* ---------- Introspection helpers ---------- */
    private static function hasTable(PDO $db, string $table): bool {
        $st = $db->prepare("
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
            LIMIT 1
        ");
        $st->execute([':t'=>$table]);
        return (bool)$st->fetchColumn();
    }

    private static function hasColumn(PDO $db, string $table, string $col): bool {
        $st = $db->prepare("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
            LIMIT 1
        ");
        $st->execute([':t'=>$table, ':c'=>$col]);
        return (bool)$st->fetchColumn();
    }

    /* ---------- Public APIs ---------- */
    public static function all(PDO $db, string $sort='expense_date', string $dir='desc'): array {
        return self::listWithFilter($db, [], $sort, $dir);
    }

    public static function listWithFilter(PDO $db, array $filter, string $sort='expense_date', string $dir='desc'): array {
        $allowed = ['title','category','amount','expense_date','unit_id','created_at','id','spender_name'];
        if (!in_array($sort, $allowed, true)) $sort = 'expense_date';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $hasResidents    = self::hasTable($db,'residents') && self::hasColumn($db,'residents','full_name');
        $hasSpenderId    = self::hasColumn($db,'expenses','spender_id');
        $hasSpenderName  = self::hasColumn($db,'expenses','spender_name');

        [$where,$params] = self::buildWhere($db, $filter, $hasResidents, $hasSpenderId, $hasSpenderName);

        $select = [
            'e.*',
            'u.name AS unit_name',
            'u.floor',
        ];
        $joins = [
            'LEFT JOIN units u ON u.id = e.unit_id',
        ];
        if ($hasResidents && $hasSpenderId) {
            $select[] = 'r.full_name AS resident_full_name';
            $joins[]  = 'LEFT JOIN residents r ON r.id = e.spender_id';
        }

        $sql = "SELECT ".implode(', ', $select)."
                FROM expenses e
                ".implode(' ', $joins)."
                $where
                ORDER BY {$sort} {$dir}, e.id DESC
                LIMIT 1000";
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function sumWithFilter(PDO $db, array $filter): float {
        $hasResidents    = self::hasTable($db,'residents') && self::hasColumn($db,'residents','full_name');
        $hasSpenderId    = self::hasColumn($db,'expenses','spender_id');
        $hasSpenderName  = self::hasColumn($db,'expenses','spender_name');

        [$where,$params] = self::buildWhere($db, $filter, $hasResidents, $hasSpenderId, $hasSpenderName);

        $joins = [];
        if ($hasResidents && $hasSpenderId) {
            $joins[] = 'LEFT JOIN residents r ON r.id = e.spender_id';
        }

        $sql = "SELECT COALESCE(SUM(e.amount),0)
                FROM expenses e
                ".implode(' ', $joins)."
                $where";
        $st = $db->prepare($sql);
        $st->execute($params);
        return (float)$st->fetchColumn();
    }

    private static function buildWhere(PDO $db, array $f, bool $hasResidents, bool $hasSpenderId, bool $hasSpenderName): array {
        $where = []; $p = [];

        $from = trim((string)($f['from'] ?? ''));
        $to   = trim((string)($f['to']   ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'e.expense_date >= :from'; $p[':from'] = $from; }
        if ($to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'e.expense_date <= :to';   $p[':to']   = $to;   }

        $cat = trim((string)($f['category'] ?? ''));
        if ($cat !== '') { $where[] = 'e.category = :cat'; $p[':cat'] = $cat; }

        $uid = $f['unit_id'] ?? '';
        if ($uid !== '' && is_numeric($uid)) { $where[] = 'e.unit_id = :uid'; $p[':uid'] = (int)$uid; }

        $spender = trim((string)($f['spender'] ?? ''));
        if ($spender !== '') {
            $conds = [];
            if ($hasSpenderName)            $conds[] = 'e.spender_name LIKE :sp';
            if ($hasResidents && $hasSpenderId) $conds[] = 'r.full_name LIKE :sp';
            if (!$conds) { // هیچ ستونی برای فیلتر وجود ندارد، حداقل روی عنوان جست‌وجو کنیم
                $conds[] = 'e.title LIKE :sp';
            }
            $where[] = '('.implode(' OR ', $conds).')';
            $p[':sp'] = '%'.$spender.'%';
        }

        $w = $where ? 'WHERE '.implode(' AND ', $where) : '';
        return [$w, $p];
    }

    public static function find(PDO $db, int $id): ?array {
        $st = $db->prepare("SELECT * FROM expenses WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(PDO $db, array $data): int {
        $hasSpenderId   = self::hasColumn($db,'expenses','spender_id');
        $hasSpenderName = self::hasColumn($db,'expenses','spender_name');

        $cols = ['title','category','amount','expense_date','unit_id','notes'];
        $vals = [':title',':category',':amount',':expense_date',':unit_id',':notes'];
        $params = [
            ':title'        => trim((string)($data['title'] ?? '')),
            ':category'     => trim((string)($data['category'] ?? '')) ?: null,
            ':amount'       => (float)($data['amount'] ?? 0),
            ':expense_date' => ($data['expense_date'] ?? '') ?: null,
            ':unit_id'      => ($data['unit_id'] ?? '') !== '' ? (int)$data['unit_id'] : null,
            ':notes'        => trim((string)($data['notes'] ?? '')) ?: null,
        ];
        if ($hasSpenderId) {
            $cols[] = 'spender_id';   $vals[] = ':spender_id';
            $params[':spender_id'] = ($data['spender_id'] ?? '') !== '' ? (int)$data['spender_id'] : null;
        }
        if ($hasSpenderName) {
            $cols[] = 'spender_name'; $vals[] = ':spender_name';
            $params[':spender_name'] = trim((string)($data['spender_name'] ?? '')) ?: null;
        }

        $sql = "INSERT INTO expenses (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
        $st = $db->prepare($sql);
        $st->execute($params);
        return (int)$db->lastInsertId();
    }

    public static function update(PDO $db, int $id, array $data): void {
        $hasSpenderId   = self::hasColumn($db,'expenses','spender_id');
        $hasSpenderName = self::hasColumn($db,'expenses','spender_name');

        $sets = [
            'title = :title',
            'category = :category',
            'amount = :amount',
            'expense_date = :expense_date',
            'unit_id = :unit_id',
            'notes = :notes',
        ];
        $params = [
            ':title'        => trim((string)($data['title'] ?? '')),
            ':category'     => trim((string)($data['category'] ?? '')) ?: null,
            ':amount'       => (float)($data['amount'] ?? 0),
            ':expense_date' => ($data['expense_date'] ?? '') ?: null,
            ':unit_id'      => ($data['unit_id'] ?? '') !== '' ? (int)$data['unit_id'] : null,
            ':notes'        => trim((string)($data['notes'] ?? '')) ?: null,
            ':id'           => $id,
        ];
        if ($hasSpenderId) {
            $sets[] = 'spender_id = :spender_id';
            $params[':spender_id'] = ($data['spender_id'] ?? '') !== '' ? (int)$data['spender_id'] : null;
        }
        if ($hasSpenderName) {
            $sets[] = 'spender_name = :spender_name';
            $params[':spender_name'] = trim((string)($data['spender_name'] ?? '')) ?: null;
        }

        $sql = "UPDATE expenses SET ".implode(', ', $sets)." WHERE id=:id";
        $st = $db->prepare($sql);
        $st->execute($params);
    }

    public static function delete(PDO $db, int $id): void {
        $st = $db->prepare("DELETE FROM expenses WHERE id=?");
        $st->execute([$id]);
    }
}