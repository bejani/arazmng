<?php
class Resident
{
    public static function all($pdo)
    {
        $stmt = $pdo->query("SELECT * FROM residents ORDER BY id DESC");
        return $stmt->fetchAll();
    }


    public static function create(PDO $pdo, array $data): int
    {
        // پایه‌ها
        $cols = ['full_name', 'mobile', 'email', 'national_id', '`type`', 'is_active'];
        $vals = [
            $data['full_name']   ?? null,
            $data['mobile']      ?? null,
            $data['email']       ?? null,
            $data['national_id'] ?? null,
            (in_array($data['type'] ?? 'owner', ['owner', 'tenant'], true) ? ($data['type'] ?? 'owner') : 'owner'),
            1
        ];

        // تابع کمکی: بررسی وجود ستون
        $has = function (string $col) use ($pdo): bool {
            $q = $pdo->query("
                      SELECT 1 FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'residents' AND COLUMN_NAME = '{$col}'
                      LIMIT 1
                  ");
            return (bool)$q->fetchColumn();
        };

        // اختیاری‌ها
        if ($has('portal_pin') && !empty($data['portal_pin'])) {
            $cols[] = 'portal_pin';
            $vals[] = $data['portal_pin']; // قبلاً در کنترلر bcrypt شده
        }
        if ($has('portal_must_change') && isset($data['portal_must_change'])) {
            $cols[] = 'portal_must_change';
            $vals[] = (int)$data['portal_must_change']; // معمولاً 1 برای اجبار تغییر رمز
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO residents (" . implode(',', $cols) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        return (int)$pdo->lastInsertId();
    }
}
