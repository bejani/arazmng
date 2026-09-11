<?php
// models/ExpenseCategory.php
declare(strict_types=1);

final class ExpenseCategory
{
    public static function all(PDO $db, bool $onlyActive=false): array {
        $sql = "SELECT * FROM expense_categories"
             . ($onlyActive ? " WHERE is_active=1" : "")
             . " ORDER BY name";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(PDO $db, int $id): ?array {
        $st = $db->prepare("SELECT * FROM expense_categories WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function create(PDO $db, string $name): int {
        $st = $db->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        $st->execute([trim($name)]);
        return (int)$db->lastInsertId();
    }

    public static function update(PDO $db, int $id, string $name, int $is_active): void {
        $st = $db->prepare("UPDATE expense_categories SET name=?, is_active=? WHERE id=?");
        $st->execute([trim($name), $is_active ? 1 : 0, $id]);
    }

    public static function delete(PDO $db, int $id): void {
        $st = $db->prepare("DELETE FROM expense_categories WHERE id=?");
        $st->execute([$id]);
    }
}