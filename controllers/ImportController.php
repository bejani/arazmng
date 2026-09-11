<?php
// controllers/ImportController.php

// اگر مدل‌ها را لازم داری، نگه دار. (در این کنترلر از PDO مستقیم استفاده می‌کنیم)
# require_once __DIR__."/../models/Unit.php";
# require_once __DIR__."/../models/Resident.php";

class ImportController {
    public function index($pdo) {
        $import_log = [];
        include __DIR__."/../views/import_excel.php";
    }

    public function upload($pdo) {
        $import_log = [];

        if (empty($_FILES['excel_file']['tmp_name'])) {
            $import_log[] = "فایلی انتخاب نشده است.";
            include __DIR__."/../views/import_excel.php";
            return;
        }

        $has_header = !empty($_POST['has_header']);
        $fname = $_FILES['excel_file']['name'];
        $tmp   = $_FILES['excel_file']['tmp_name'];
        $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

        // خواندن ردیف‌ها به آرایه $rows با کلیدهای A,B,C
        $rows = [];

        try {
            if (in_array($ext, ['xlsx','xls'])) {
                // فقط هنگام خواندن Excel واقعی autoload را لود کن
                require_once __DIR__ . '/../vendor/autoload.php';
                $type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmp);
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($type);
                $spreadsheet = $reader->load($tmp);
                $sheet = $spreadsheet->getActiveSheet();
                // خروجی با کلیدهای ستونی (A,B,C,...) می‌آید
                $rows = $sheet->toArray(null, true, true, true);
            } elseif ($ext === 'csv') {
                if (($handle = fopen($tmp, "r")) !== FALSE) {
                    $rowIndex = 1;
                    while (($data = fgetcsv($handle, 200000, ",")) !== FALSE) {
                        $rows[$rowIndex] = [
                            'A' => $data[0] ?? null, // طبقه
                            'B' => $data[1] ?? null, // نام واحد
                            'C' => $data[2] ?? null, // نام ساکن
                        ];
                        $rowIndex++;
                    }
                    fclose($handle);
                }
            } else {
                $import_log[] = "فرمت فایل پشتیبانی نمی‌شود. فقط xlsx، xls یا csv.";
                include __DIR__."/../views/import_excel.php";
                return;
            }

            if (!$rows || count($rows) === 0) {
                $import_log[] = "فایل خالی است.";
                include __DIR__."/../views/import_excel.php";
                return;
            }

            // اگر ردیف اول هدر است، حذفش کن
            if ($has_header) {
                // حذف اولین عنصر
                $firstKey = array_key_first($rows);
                if ($firstKey !== null) {
                    unset($rows[$firstKey]);
                }
            }

            // شمارنده‌ها
            $createdUnits = 0;
            $updatedUnits = 0;
            $createdResidents = 0;
            $createdLinks = 0;

            // تراکنش برای سرعت و اتمی بودن
            $pdo->beginTransaction();

            foreach ($rows as $idx => $r) {
                $floor       = trim((string)($r['A'] ?? ''));  // شماره طبقه
                $unit_name   = trim((string)($r['B'] ?? ''));  // نام واحد
                $resident_fn = trim((string)($r['C'] ?? ''));  // نام ساکن

                // رد کردن ردیف‌های خالی
                if ($unit_name === '' && $resident_fn === '') {
                    continue;
                }

                // 1) واحد: code را از نام واحد می‌گیریم و یکتا می‌کنیم
                $unit_id = $this->findOrCreateUnit($pdo, $unit_name, $floor, $createdUnits, $updatedUnits);

                // 2) ساکن: فقط بر اساس نام؛ type پیش‌فرض owner (یا از DEFAULT جدول)
                $resident_id = $this->findOrCreateResident($pdo, $resident_fn, $createdResidents);

                // 3) لینک مالکیت جاری (end_date IS NULL). اگر نبود، بساز.
                if ($unit_id && $resident_id) {
                    $exists = $pdo->prepare(
                        "SELECT COUNT(*) FROM ownerships
                         WHERE unit_id=? AND resident_id=? AND end_date IS NULL"
                    );
                    $exists->execute([$unit_id, $resident_id]);

                    if ((int)$exists->fetchColumn() === 0) {
                        $ins = $pdo->prepare(
                            "INSERT INTO ownerships (unit_id,resident_id,start_date,is_primary)
                             VALUES (?,?,CURDATE(),1)"
                        );
                        $ins->execute([$unit_id,$resident_id]);
                        $createdLinks++;
                    }
                } else {
                    $import_log[] = "⚠️ ردیف {$idx}: ایجاد واحد یا ساکن ناموفق بود.";
                }
            }

            $pdo->commit();

            $import_log[] = "واحدهای جدید: {$createdUnits}" . ($updatedUnits ? " | به‌روزرسانی واحدها: {$updatedUnits}" : "");
            $import_log[] = "ساکنان جدید: {$createdResidents}";
            $import_log[] = "رابط‌های مالکیت ایجادشده: {$createdLinks}";
            $import_log[] = "ایمپورت با موفقیت پایان یافت.";

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $import_log[] = "❌ خطا در ایمپورت: " . $e->getMessage();
        }

        include __DIR__."/../views/import_excel.php";
    }

    // ---------- Helpers ----------

    /**
     * پیدا/ایجاد واحد با code بر اساس نام واحد و تضمین یکتایی.
     * اگر واحد موجود باشد، نام/طبقه را به‌روزرسانی سبک می‌کند.
     */
    private function findOrCreateUnit($pdo, $unit_name, $floor, &$createdRef, &$updatedRef) {
        if ($unit_name === '') return null;

        // سعی کن با code = نام واحد پیدا کنی
        $sel = $pdo->prepare("SELECT id, name, floor FROM units WHERE code=? LIMIT 1");
        $sel->execute([$unit_name]);
        $row = $sel->fetch();

        if ($row) {
            // آپدیت سبک اگر چیزی تغییر کرده
            $needUpdate = false;
            $newName = $row['name'] ?: $unit_name;
            if ($newName !== $row['name'] || $floor !== $row['floor']) {
                $upd = $pdo->prepare("UPDATE units SET name=?, floor=? WHERE id=?");
                $upd->execute([$newName, $floor, $row['id']]);
                $updatedRef++;
            }
            return (int)$row['id'];
        }

        // کد یکتا بساز (بر پایه نام واحد)
        $codeCandidate = $unit_name;
        $n = 1;
        while (true) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM units WHERE code=?");
            $check->execute([$codeCandidate]);
            if ((int)$check->fetchColumn() === 0) break;
            // اگر نام واحد تکراری بود، پسوند اضافه کن
            $codeCandidate = $unit_name . '-' . ($floor !== '' ? $floor : $n);
            $n++;
        }

        $ins = $pdo->prepare("INSERT INTO units (code,name,floor,area_m2,is_active) VALUES (?,?,?,?,1)");
        $ok = $ins->execute([$codeCandidate, $unit_name, $floor, 0]);
        if ($ok) {
            $createdRef++;
            return (int)$pdo->lastInsertId();
        }
        return null;
    }

    /**
     * پیدا/ایجاد ساکن بر اساس نام کامل. بقیه فیلدها NULL/پیش‌فرض.
     * اگر در اسکیما type پیش‌فرض owner است، نیازی به ست‌کردن آن نیست.
     */
    private function findOrCreateResident($pdo, $full_name, &$createdRef) {
        if ($full_name === '') return null;

        $sel = $pdo->prepare("SELECT id FROM residents WHERE full_name=? LIMIT 1");
        $sel->execute([$full_name]);
        $id = $sel->fetchColumn();
        if ($id) return (int)$id;

        // اگر ستون type پیش‌فرض owner دارد، همین کافی است:
        $ins = $pdo->prepare("INSERT INTO residents (full_name, mobile, email, national_id) VALUES (?,?,?,?)");
        $ok = $ins->execute([$full_name, null, null, null]);

        // اگر DEFAULT ندارید و می‌خواهید صراحتاً owner بگذارید، از این یکی استفاده کنید:
        // $ins = $pdo->prepare("INSERT INTO residents (full_name, mobile, email, national_id, type, is_active) VALUES (?,?,?,?, 'owner', 1)");
        // $ok = $ins->execute([$full_name, null, null, null]);

        if ($ok) {
            $createdRef++;
            return (int)$pdo->lastInsertId();
        }
        return null;
    }
}