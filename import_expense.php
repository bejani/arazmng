<?php
/**
 * import_expenses.php
 * وب‌فرم ساده برای ایمپورت اکسل/CSV به جدول expenses
 * - قرار دهید کنار config/db.php
 * - نیازمندی: phpoffice/phpspreadsheet (برای xlsx/xls). CSV بدون vendor هم پشتیبانی می‌شود.
 */

declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

// اتصال PDO
require_once __DIR__.'/config/db.php';

// هلپرهای نرمال‌سازی
function fa_norm(string $s): string {
    $s = str_replace(["\u{200c}","\u{200f}","‌","‏"], " ", $s);
    $s = str_replace(["ي","ك"], ["ی","ک"], $s);
    $s = preg_replace('/\s+/u',' ', $s);
    // تبدیل ارقام فارسی
    $s = strtr($s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>',','٫'=>'.']);
    return trim($s);
}
function norm_amount($x) {
    if ($x === null) return null;
    $s = fa_norm((string)$x);
    $s = str_replace([',',' ','تومان','ريال','ریال'],'', $s);
    if ($s === '') return null;
    // فقط رقم و نقطه
    if (!preg_match('/-?\d+(\.\d+)?/',$s,$m)) return null;
    $f = (float)$m[0];
    return $f;
}
function parse_date_string(string $s, bool $assume_jalali=false): ?string {
    $s = fa_norm($s);
    $s = str_replace(['.','\\'], ['-','-'], $s);
    $s = str_replace(['/'], '-', $s);

    // اگر تاریخ عدد سریال اکسل است (فقط هنگام اکسل)
    if (is_numeric($s) && (float)$s > 25000) {
        // نیاز به PhpSpreadsheet
        if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$s);
            return $dt->format('Y-m-d');
        }
    }

    // الگوهای متنی: YYYY-MM-DD یا DD-MM-YYYY
    if (preg_match('/^(13|14|19|20)\d{2}-\d{1,2}-\d{1,2}$/',$s)) {
        [$y,$m,$d] = array_map('intval', explode('-',$s));
        // اگر سال جلالی است
        if ($y>=1300 && $y<1500) {
            [$gy,$gm,$gd] = j2g($y,$m,$d);
            return sprintf('%04d-%02d-%02d',$gy,$gm,$gd);
        }
        return sprintf('%04d-%02d-%02d',$y,$m,$d);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-((?:13|14|19|20)\d{2})$/',$s,$m)) {
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
        if ($y>=1300 && $y<1500) { [$gy,$gm,$gd]=j2g($y,$mo,$d); return sprintf('%04d-%02d-%02d',$gy,$gm,$gd); }
        return sprintf('%04d-%02d-%02d',$y,$mo,$d);
    }

    // اگر بیان مبهم بود و کاربر تیک «تاریخ‌ها جلالی هستند» زده، تلاش برای تبدیل
    if ($assume_jalali && preg_match('/^(13|14)\d{2}[- ]\d{1,2}[- ]\d{1,2}$/',$s)) {
        [$y,$m,$d] = array_map('intval', preg_split('/[- ]/',$s));
        [$gy,$gm,$gd] = j2g($y,$m,$d);
        return sprintf('%04d-%02d-%02d',$gy,$gm,$gd);
    }
    return null;
}

/** تبدیل جلالی→میلادی؛ الگوریتم استاندارد (بدون وابستگی خارجی) */
function j2g($jy,$jm,$jd): array {
    $jy = (int)$jy; $jm=(int)$jm; $jd=(int)$jd;
    $jy += 1595;
    $days = -355668 + (365*$jy) + (int)($jy/33)*8 + (int)((($jy%33)+3)/4) + $jd + (($jm<7)?($jm-1)*31:(($jm-7)*30+186));
    $gy = 400*(int)($days/146097); $days%=146097;
    if ($days>36524){ $gy += 100*(int)(--$days/36524); $days%=36524; if ($days>=365) $days++; }
    $gy += 4*(int)($days/1461); $days%=1461;
    if ($days>365){ $gy += (int)(($days-1)/365); $days = ($days-1)%365; }
    $gd = $days+1;
    $sal_a = [0,31, ( ($gy%4==0 && $gy%100!=0) || ($gy%400==0) ) ? 29 : 28 ,31,30,31,30,31,31,30,31,30,31];
    for($gm=1;$gm<=12;$gm++){
        $v=$sal_a[$gm];
        if($gd<=$v) break;
        $gd-=$v;
    }
    return [$gy,$gm,$gd];
}

// تشخیص هدرها (کلیدهای ممکن فارسی/انگلیسی)
$HEADER_MAP = [
    'category'   => ['category','دسته','دسته‌بندی','گروه','سرفصل'],
    'spent_at'   => ['spent_at','date','تاریخ','تاريخ','تاریخ هزینه','تاریخ پرداخت'],
    'amount'     => ['amount','مبلغ','هزینه','مبلغ کل','price','sum'],
    'vendor'     => ['vendor','payee','recipient','دریافت کننده','تامین کننده','فروشنده','طرف حساب','بدهکار','بستانکار'],
    'description'=> ['description','شرح','بابت','توضیحات','عنوان','شرح هزینه'],
];

// پردازش
$log = [];
$done = 0; $skipped = 0; $errors = 0;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $has_header   = !empty($_POST['has_header']);
    $jalali_dates = !empty($_POST['jalali_dates']);
    $skip_dups    = !empty($_POST['skip_duplicates']);

    if (empty($_FILES['file']['tmp_name'])) {
        $log[] = "فایل انتخاب نشده است.";
    } else {
        $name = $_FILES['file']['name'];
        $tmp  = $_FILES['file']['tmp_name'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        try {
            $rows = [];
            $headers = [];
            if (in_array($ext,['xlsx','xls'])) {
                require_once __DIR__.'/vendor/autoload.php';
                $type  = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmp);
                $rdr   = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($type);
                $sheet = $rdr->load($tmp)->getActiveSheet();
                $arr   = $sheet->toArray(null, true, true, true); // Letter keys
                if ($has_header) {
                    $first = array_shift($arr);
                    foreach ($first as $k=>$v) { $headers[$k] = fa_norm(mb_strtolower((string)$v)); }
                }
                foreach ($arr as $r) {
                    $rows[] = $r; // associative by letters A,B,C...
                }
            } elseif ($ext==='csv') {
                if (($h=fopen($tmp,'r'))!==false) {
                    $i=0;
                    while (($r=fgetcsv($h, 100000, ","))!==false) {
                        $i++;
                        if ($i==1 && $has_header) {
                            foreach ($r as $idx=>$val) { $headers[$idx] = fa_norm(mb_strtolower((string)$val)); }
                            continue;
                        }
                        $rows[] = $r; // numeric keys
                    }
                    fclose($h);
                }
            } else {
                throw new RuntimeException("پسوند فایل پشتیبانی نمی‌شود. فقط xlsx/xls/csv");
            }

            // نگاشت هدرها به فیلدها
            $colIdx = ['category'=>null,'spent_at'=>null,'amount'=>null,'vendor'=>null,'description'=>null];

            $findHeader = function($needleList, $headers) {
                foreach ($headers as $idx=>$label) {
                    foreach ($needleList as $needle) {
                        if (mb_strpos($label, mb_strtolower($needle)) !== false) return $idx;
                    }
                }
                return null;
            };

            if (!empty($headers)) {
                // headers keyed either by letters (A,B,...) or numeric 0,1...
                foreach ($colIdx as $field => $_) {
                    $colIdx[$field] = $findHeader($HEADER_MAP[$field], $headers);
                }
            } else {
                // بدون هدر: ترتیب پیش‌فرض A..E یا 0..4
                $colIdx = ['category'=>'A','spent_at'=>'B','amount'=>'C','vendor'=>'D','description'=>'E'];
            }

            // اعتبار حداقل ستون‌ها
            if ($colIdx['spent_at']===null || $colIdx['amount']===null) {
                $log[] = "ستون‌های تاریخ و مبلغ پیدا نشدند. لطفاً هدرها را بررسی کنید یا بدون هدر با ترتیب A:category,B:date,C:amount,D:vendor,E:description ارسال کنید.";
            } else {
                // آماده درج
                $pdo->beginTransaction();
                $stmtIns = $pdo->prepare("INSERT INTO expenses (category, spent_at, amount, vendor, description) VALUES (?,?,?,?,?)");
                $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category<=>? AND spent_at=? AND amount<=>? AND vendor<=>? AND description<=>?");

                foreach ($rows as $r) {
                    // دسترسی به مقدار سلول با توجه به نوع ایندکس
                    $val = function($idx) use ($r) {
                        if ($idx===null) return null;
                        return $r[$idx] ?? null;
                    };

                    $category = fa_norm((string)$val($colIdx['category']));
                    $spentRaw = (string)$val($colIdx['spent_at']);
                    $amount   = norm_amount($val($colIdx['amount']));
                    $vendor   = fa_norm((string)$val($colIdx['vendor']));
                    $desc     = fa_norm((string)$val($colIdx['description']));

                    // تاریخ
                    $spent_at = parse_date_string($spentRaw, $jalali_dates);
                    if (!$spent_at || $amount===null) {
                        $errors++;
                        $log[] = "ردیف نامعتبر (تاریخ/مبلغ): date='{$spentRaw}', amount='{$val($colIdx['amount'])}'";
                        continue;
                    }

                    // حذف/پر کردن مقادیر تهی
                    $category = $category !== '' ? $category : null;
                    $vendor   = $vendor !== ''   ? $vendor   : null;
                    $desc     = $desc !== ''     ? $desc     : null;

                    // چک تکراری
                    if ($skip_dups) {
                        $stmtChk->execute([$category, $spent_at, $amount, $vendor, $desc]);
                        if ((int)$stmtChk->fetchColumn() > 0) {
                            $skipped++;
                            continue;
                        }
                    }

                    $stmtIns->execute([$category, $spent_at, $amount, $vendor, $desc]);
                    $done++;
                }
                $pdo->commit();
                $log[] = "ایمپورت تمام شد: {$done} درج، {$skipped} رد تکراری، {$errors} خطا.";
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $log[] = "خطا: ".$e->getMessage();
        }
    }
}

// ---- UI ساده با Bootstrap (CDN) ----
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>ایمپورت اکسل به expenses</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-4">
        <h3 class="mb-3">📥 ایمپورت اکسل/CSV به جدول <code>expenses</code></h3>
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">فایل (xlsx/xls/csv)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">گزینه‌ها</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="has_header" id="has_header">
                            <label class="form-check-label" for="has_header">ردیف اول هدر است</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="jalali_dates" id="jalali_dates">
                            <label class="form-check-label" for="jalali_dates">تاریخ‌ها جلالی هستند (خودکار به میلادی
                                تبدیل می‌شود)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="skip_duplicates" id="skip_duplicates"
                                checked>
                            <label class="form-check-label" for="skip_duplicates">رد کردن ردیف‌های تکراری (بر اساس
                                category+spent_at+amount+vendor+description)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary">شروع ایمپورت</button>
                    </div>
                </form>
                <small class="text-muted d-block mt-3">
                    هدرهای قابل‌قبول: دسته/Category، تاریخ/Date، مبلغ/Amount، تامین‌کننده/Vendor، شرح/بابت/Description.
                    بدون هدر: A=category, B=spent_at, C=amount, D=vendor, E=description.
                </small>
            </div>
        </div>

        <?php if (!empty($log)): ?>
        <div class="card">
            <div class="card-header">نتیجه</div>
            <div class="card-body">
                <ul class="mb-0">
                    <?php foreach($log as $m): ?>
                    <li><?= htmlspecialchars($m) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>