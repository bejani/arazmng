<?php
class Ownership {
    public static function all($pdo) {
        $sql = "SELECT o.*, u.code AS unit_code, r.full_name AS resident_name
                FROM ownerships o
                JOIN units u ON u.id = o.unit_id
                JOIN residents r ON r.id = o.resident_id
                ORDER BY o.start_date DESC";
        return $pdo->query($sql)->fetchAll();
    }

    public static function create($pdo, $data) {
        $sql = "INSERT INTO ownerships (unit_id, resident_id, start_date, end_date, is_primary)
                VALUES (?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['unit_id'],
            $data['resident_id'],
            $data['start_date'],
            $data['end_date'] ?: null,
            $data['is_primary'] ?? 1
        ]);
    }
}