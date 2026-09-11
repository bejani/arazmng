<?php
class Unit {
    public static function all($pdo) {
        $stmt = $pdo->query("SELECT * FROM units ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public static function create($pdo, $data) {
        $sql = "INSERT INTO units (code,name,floor,area_m2,share_ratio) VALUES (?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['code'],
            $data['name'],
            $data['floor'],
            $data['area_m2'],
            $data['share_ratio']
        ]);
    }
}