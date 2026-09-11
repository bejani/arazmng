<?php
/**
 * import_units_owners_flex.php
 * - ورودی: فایل Excel/CSV با سه ستون: A=طبقه، B=نام واحد، C=نام مالک
 * - خروجی: درج/یافت واحد در units(name,floor[,+is_active?]), مالک در residents(full_name[,+type?,+is_active?]),
 *          و رابطه در ownerships(unit_id,resident_id[,+is_active?])
 * - این نسخه وجود ستون‌های اختیاری مثل is_active و type را چک می‌کند تا خطای "Unknown column 'is_active'" رخ ندهد.
 */

mb_internal_encoding('UTF-8');
require_once __DIR__ . '/db.php'; // باید $pdo = new PDO(...); را فراهم کند.

$hasSpreadsheet = false;
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        $hasSpreadsheet = true;
    }
}

function fa_to_en_digits(string $s): string {
    return strtr($s, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
}
function norm(?string $s): string {
    $s = (string)$s;
    $s = trim($s);
    return preg_replace('/\s+/u', ' ', $s);
}
function is_header_row($a, $b, $c): bool {
    $hints = ['طبقه','واحد','نام','مالک','floor','unit','owner'];
    $row = mb_strtolower($a.' '.$b.' '.$c, 'UTF-8');
    foreach ($hints as $h) {
        if (mb_strpos($row, mb_strtolower($h,'UTF-8')) !== false) return true;
    }
    return false;
}
function hasColumn(PDO $pdo, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t'=>$table, ':c'=>$column]);
    return (bool)$st->fetchColumn();
}

$errors = [];
$log = [];
$counts = ['rows'=>0,'units_new'=>0,'residents_new'=>0,'links_new'=>0,'skipped'=>0];

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// تشخیص ستون‌های اختیاری
$hasUnitActive = hasColumn($pdo, 'units', 'is_active');
$hasResActive  = hasColumn($pdo, 'residents', 'is_active');
$hasResType    = hasColumn($pdo, 'residents', 'type');
$hasOwnActive  = hasColumn($pdo, 'ownerships', 'is_active');

// آماده‌سازی statementها
$findUnit   = $pdo->prepare("SELECT id FROM units WHERE name = :name AND floor = :floor LIMIT 1");
$createUnitSql = "INSERT INTO units (name, floor".($hasUnitActive?", is_active":"").") VALUES (:name, :floor".($hasUnitActive?", 1":"").")";
$createUnit = $pdo->prepare($createUnitSql);

$findRes    = $pdo->prepare("SELECT id FROM residents WHERE full_name = :name LIMIT 1");
$resCols = ['full_name'];
$resVals = [':name'];
$paramsMap = [':name' => null];
if ($hasResType)   { $resCols[] = 'type';      $resVals[] = ':type';      $paramsMap[':type'] = 'owner'; }
if ($hasResActive) { $resCols[] = 'is_active'; $resVals[] = '1'; }
$createResSql = "INSERT INTO residents (".implode(',', $resCols).") VALUES (".implode(',', $resVals).")";
$createRes = $pdo->prepare($createResSql);

$findOwn    = $pdo->prepare("SELECT id FROM ownerships WHERE unit_id = :u AND resident_id = :r LIMIT 1");
$createOwnSql = "INSERT INTO ownerships (unit_id, resident_id".($hasOwnActive?", is_active":"").") VALUES (:u, :r".($hasOwnActive?", 1":"").")";
$createOwn  = $pdo->prepare($createOwnSql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'فایل به‌درستی آپلود نشد.';
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $name = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        try {
            $pdo->beginTransaction();

            if (in_array($ext, ['csv','txt'])) {
                if (($fh = fopen($tmp, 'r')) === false) throw new RuntimeException('امکان خواندن فایل وجود ندارد.');
                $rowNum = 0;
                while (($row = fgetcsv($fh)) !== false) {
                    $rowNum++;
                    $a = $row[0] ?? '';
                    $b = $row[1] ?? '';
                    $c = $row[2] ?? '';
                    if ($rowNum === 1 && is_header_row($a,$b,$c)) continue;
                    process_row($pdo, $a, $b, $c, $counts, $log, $findUnit, $createUnit, $findRes, $createRes, $paramsMap, $findOwn, $createOwn);
                }
                fclose($fh);
            } elseif (in_array($ext, ['xlsx','xls'])) {
                if (!$hasSpreadsheet) {
                    throw new RuntimeException('برای خواندن Excel باید PhpSpreadsheet نصب شود. یا فایل را به CSV تبدیل کنید.');
                }
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
                $sheet = $spreadsheet->getSheet(0);
                $rows = $sheet->toArray(null, true, true, true);
                $rowNum = 0;
                foreach ($rows as $r) {
                    $rowNum++;
                    $a = $r['A'] ?? '';
                    $b = $r['B'] ?? '';
                    $c = $r['C'] ?? '';
                    if ($rowNum === 1 && is_header_row((string)$a,(string)$b,(string)$c)) continue;
                    process_row($pdo, (string)$a, (string)$b, (string)$c, $counts, $log, $findUnit, $createUnit, $findRes, $createRes, $paramsMap, $findOwn, $createOwn);
                }
            } else {
                throw new RuntimeException('پسوند فایل پشتیبانی نمی‌شود. (xlsx/xls/csv)');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'خطا در پردازش: ' . $e->getMessage();
        }
    }
}

function process_row(PDO $pdo, $colA, $colB, $colC, array &$counts, array &$log,
                     PDOStatement $findUnit, PDOStatement $createUnit,
                     PDOStatement $findRes, PDOStatement $createRes, array $createResParamsMap,
                     PDOStatement $findOwn, PDOStatement $createOwn): void {
    $counts['rows']++;

    $floor = norm(fa_to_en_digits((string)$colA));
    $unitName = norm((string)$colB);
    $ownerName = norm((string)$colC);

    if ($floor === '' && $unitName === '' && $ownerName === '') { $counts['skipped']++; return; }
    if ($unitName === '' || $ownerName === '') {
        $log[] = ['row'=>$counts['rows'], 'status'=>'skip', 'msg'=>'نام واحد یا نام مالک خالی است.'];
        $counts['skipped']++;
        return;
    }

    $floorNum = ($floor === '') ? 0 : (int)$floor;

    // 1) واحد
    $findUnit->execute([':name'=>$unitName, ':floor'=>$floorNum]);
    $unitId = $findUnit->fetchColumn();
    if (!$unitId) {
        $createUnit->execute([':name'=>$unitName, ':floor'=>$floorNum]);
        $unitId = (int)$pdo->lastInsertId();
        $counts['units_new']++;
    }

    // 2) مالک
    $findRes->execute([':name'=>$ownerName]);
    $resId = $findRes->fetchColumn();
    if (!$resId) {
        $params = [];
        foreach ($createResParamsMap as $k => $v) {
            $params[$k] = ($k === ':name') ? $ownerName : $v;
        }
        $createRes->execute($params);
        $resId = (int)$pdo->lastInsertId();
        $counts['residents_new']++;
    }

    // 3) رابطه مالکیت
    $findOwn->execute([':u'=>$unitId, ':r'=>$resId]);
    $ownId = $findOwn->fetchColumn();
    if (!$ownId) {
        $createOwn->execute([':u'=>$unitId, ':r'=>$resId]);
        $counts['links_new']++;
    }

    $log[] = ['row'=>$counts['rows'], 'status'=>'ok', 'msg'=>"{$ownerName} مالک {$unitName} در طبقه {$floorNum}"];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود اطلاعات واحدها و مالک‌ها (Excel/CSV)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>

<body class="p-3">
    <div class="container">
        <h3 class="mb-3">ورود اطلاعات واحدها و مالک‌ها</h3>

        <form method="post" enctype="multipart/form-data" class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">فایل (xlsx/xls/csv)</label>
                    <input type="file" name="file" class="form-control" required>
                    <div class="form-text">ستون‌ها: A=طبقه، B=نام واحد، C=نام مالک</div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary">بارگذاری و پردازش</button>
                </div>
                <div class="col-md-3 text-muted small">
                    <?php if (!$hasSpreadsheet): ?>
                    <div class="alert alert-warning mb-0">
                        برای فایل Excel (xlsx/xls) نیاز به PhpSpreadsheet است یا فایل را به CSV تبدیل کنید.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($log)): ?>
        <div class="alert alert-info">
            ردیف‌ها: <b><?= (int)$counts['rows'] ?></b> |
            واحدهای جدید: <b><?= (int)$counts['units_new'] ?></b> |
            مالک‌های جدید: <b><?= (int)$counts['residents_new'] ?></b> |
            روابط جدید: <b><?= (int)$counts['links_new'] ?></b> |
            پرش‌شده: <b><?= (int)$counts['skipped'] ?></b>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width:80px">#</th>
                        <th>وضعیت</th>
                        <th>پیام</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $i => $row): ?>
                    <tr>
                        <td class="text-center"><?= $i+1 ?></td>
                        <td class="text-nowrap">
                            <?php if ($row['status']==='ok'): ?>
                            <span class="badge bg-success">ثبت شد</span>
                            <?php elseif ($row['status']==='skip'): ?>
                            <span class="badge bg-secondary">رد شد</span>
                            <?php else: ?>
                            <span class="badge bg-danger">خطا</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['msg']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <hr>
        <div class="small text-muted">
            توصیه: برای جلوگیری از رکوردهای تکراری، ایندکس‌های یونیک زیر را داشته باشید:
            <pre><code>ALTER TABLE units ADD UNIQUE KEY ux_units_name_floor (name, floor);
ALTER TABLE ownerships ADD UNIQUE KEY ux_ownerships_unit_resident (unit_id, resident_id);
</code></pre>
        </div>
    </div>
</body>

</html>