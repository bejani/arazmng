<?php
// controllers/OwnershipController.php — نمایش واحد با «نام + طبقه» (بدون تغییر اسکیما)
declare(strict_types=1);

final class OwnershipController
{
    public function __construct(private \PDO $pdo) {}

    /** واحد را برمی‌گرداند */
    private function fetchUnit(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM units WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** برچسب واحد: name + (طبقه X)؛ اگر name نبود، از number/code/در نهایت #id */
    private function unitLabel(?array $unit, int $fallbackId): string
    {
        if (!$unit) return '#' . $fallbackId;
        $name  = trim((string)($unit['name'] ?? ''));
        $floor = isset($unit['floor']) ? (string)$unit['floor'] : '';
        $parts = [];
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($floor !== '') {
            $parts[] = 'طبقه ' . $floor;
        }
        if (!$parts) {
            if (isset($unit['number']) && $unit['number'] !== '') return (string)$unit['number'];
            if (isset($unit['code']) && $unit['code'] !== '') return (string)$unit['code'];
            return '#' . $fallbackId;
        }
        return implode(' - ', $parts);
    }

    // فرم انتساب/تغییر مالکیت (unit-centric)
    public function reassignForm(): void
    {
        $unit_id = (int)($_GET['unit_id'] ?? 0);
        $role    = $_GET['role'] ?? 'owner'; // 'owner' | 'tenant'
        if (!in_array($role, ['owner', 'tenant'], true)) {
            $role = 'owner';
        }

        $unit = $this->fetchUnit($unit_id);
        if (!$unit) {
            http_response_code(404);
            die('واحد پیدا نشد.');
        }
        $unitLabel = $this->unitLabel($unit, $unit_id);

        // مالکیت فعال فعلی برای همین نقش (براساس residents.type)
        $cur = $this->pdo->prepare(
            "SELECT o.*, r.full_name, r.`type`
             FROM ownerships o
             JOIN residents r ON r.id = o.resident_id
             WHERE o.unit_id = ? AND r.`type` = ? AND o.end_date IS NULL
             ORDER BY o.start_date DESC
             LIMIT 1"
        );
        $cur->execute([$unit_id, $role]);
        $current = $cur->fetch(\PDO::FETCH_ASSOC) ?: null;

        // همهٔ ساکنان بدون فیلتر نقش/فعال بودن (لیست کامل برای انتخاب)
        $residents = $this->pdo->prepare(
            "SELECT id,
                    COALESCE(NULLIF(TRIM(full_name), ''), mobile, email, CONCAT('ID#', id)) AS full_name
             FROM residents
             ORDER BY full_name"
        );
        $residents->execute();
        $residents = $residents->fetchAll(\PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/ownerships/reassign.php';
    }

    // ذخیره انتساب جدید (و بستن قبلی در صورت نیاز)
    public function reassignStore(): void
    {
        $unit_id     = (int)($_POST['unit_id'] ?? 0);
        $resident_id = (int)($_POST['resident_id'] ?? 0);
        $role        = $_POST['role'] ?? 'owner';
        $start_date  = $_POST['start_date'] ?? date('Y-m-d');
        $auto_close  = !empty($_POST['auto_close']);

        if (!$unit_id || !$resident_id || !in_array($role, ['owner', 'tenant'], true)) {
            http_response_code(422);
            die('اطلاعات ناقص است.');
        }

        // تطابق نوع ساکن با نقش
        $st = $this->pdo->prepare("SELECT `type`, is_active FROM residents WHERE id = ?");
        $st->execute([$resident_id]);
        $res = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$res) {
            http_response_code(404);
            die('ساکن یافت نشد.');
        }
        if (($res['type'] ?? '') !== $role) {
            http_response_code(422);
            die('نوع ساکن با نقش انتخابی سازگار نیست.');
        }
        if ((int)$res['is_active'] !== 1) {
            http_response_code(422);
            die('ساکن غیرفعال است.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($auto_close) {
                $c = $this->pdo->prepare(
                    "UPDATE ownerships o
                     JOIN residents r ON r.id = o.resident_id
                     SET o.end_date = DATE_SUB(?, INTERVAL 1 DAY)
                     WHERE o.unit_id = ? AND r.`type` = ? AND o.end_date IS NULL"
                );
                $c->execute([$start_date, $unit_id, $role]);
            } else {
                $chk = $this->pdo->prepare(
                    "SELECT COUNT(*)
                     FROM ownerships o
                     JOIN residents r ON r.id = o.resident_id
                     WHERE o.unit_id = ? AND r.`type` = ? AND o.end_date IS NULL"
                );
                $chk->execute([$unit_id, $role]);
                if ((int)$chk->fetchColumn() > 0) {
                    throw new \RuntimeException('برای این واحد و نقش، هم‌اکنون یک مالکیت فعال وجود دارد.');
                }
            }

            // هشدار: همین ساکن با همین نقش در واحد دیگر فعال نباشد
            $warn = $this->pdo->prepare(
                "SELECT o.unit_id
                 FROM ownerships o
                 JOIN residents r ON r.id = o.resident_id
                 WHERE o.resident_id = ? AND r.`type` = ? AND o.end_date IS NULL AND o.unit_id <> ?
                 LIMIT 1"
            );
            $warn->execute([$resident_id, $role, $unit_id]);
            $otherUnitId = (int)$warn->fetchColumn();
            $other = null;
            if ($otherUnitId) {
                $otherUnit = $this->fetchUnit($otherUnitId);
                $other = $this->unitLabel($otherUnit, $otherUnitId);
            }

            // درج رکورد مالکیت
            $ins = $this->pdo->prepare("INSERT INTO ownerships (unit_id, resident_id, start_date) VALUES (?, ?, ?)");
            $ins->execute([$unit_id, $resident_id, $start_date]);

            $this->pdo->commit();

            $msg = 'انتساب انجام شد.';
            if ($other) {
                $msg .= " (توجه: این ساکن به‌عنوان {$role} در واحد «{$other}» نیز فعال بود)";
            }
            header("Location: index.php?page=units&ok=" . urlencode($msg));
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            http_response_code(500);
            die('خطا: ' . $e->getMessage());
        }
    }
}
