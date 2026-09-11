<?php
// controllers/ImportPdfController.php

class ImportPdfController {
    public function index($pdo) {
        $result = null;  // لینک فایل خروجی و لاگ
        include __DIR__."/../views/import_pdf.php";
    }

    public function upload($pdo) {
        $result = ['messages' => [], 'xlsx_path' => null];

        if (empty($_FILES['pdf_file']['tmp_name'])) {
            $result['messages'][] = "فایل PDF انتخاب نشده است.";
            include __DIR__."/../views/import_pdf.php";
            return;
        }

        // vendor
        require_once __DIR__ . '/../vendor/autoload.php';
        $tmp = $_FILES['pdf_file']['tmp_name'];
        $originalName = $_FILES['pdf_file']['name'];

        try {
            // 1) PDF → متن
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($tmp);
            $text   = $pdf->getText();

            // 2) نرمالایز متن
            $text = $this->normalize($text);
            $lines = preg_split("/\r\n|\n|\r/u", $text);

            // 3) استخراج داده از هر خط
            $rows = [];  // هر ردیف: [date, amount, currency, description, source_line]
            foreach ($lines as $ln) {
                $ln = trim(preg_replace('/\s{2,}/u', ' ', $ln));
                if ($ln === '' || mb_strlen($ln) < 3) continue;

                $parsed = $this->parseExpenseLine($ln);
                if ($parsed) {
                    $rows[] = $parsed;
                }
            }

            if (empty($rows)) {
                $result['messages'][] = "هیچ ردیفی با الگوی تاریخ/مبلغ شناخته نشد. ممکن است PDF اسکن باشد یا فرمت متفاوتی داشته باشد.";
                include __DIR__."/../views/import_pdf.php";
                return;
            }

            // 4) نوشتن اکسل
            $xlsxRelPath = $this->writeExcel($rows, $originalName);
            $result['xlsx_path'] = $xlsxRelPath;
            $result['messages'][] = "تعداد ردیف‌های استخراج‌شده: " . count($rows);
            $result['messages'][] = "فایل اکسل آماده است.";

        } catch (\Throwable $e) {
            $result['messages'][] = "خطا در پردازش PDF: " . $e->getMessage();
        }

        include __DIR__."/../views/import_pdf.php";
    }

    // ---------- Helpers ----------

    // نرمال‌سازی متن: ارقام فارسی→انگلیسی، حذف کاراکترهای کنترلی
    private function normalize(string $s): string {
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٫','٬','ي','ك','‌','‏', "\xE2\x80\x8C", "\xE2\x80\x8F"];
        $en = ['0','1','2','3','4','5','6','7','8','9','.','.', 'ی','ک',' ', ' ', ' ', ' '];
        $s = str_replace($fa, $en, $s);
        // یکنواخت‌سازی جداکننده‌ها
        $s = preg_replace('/[|،]/u', ',', $s);
        return $s;
    }

    /**
     * تلاش برای استخراج تاریخ، مبلغ، ارز، توضیح از یک خط
     * الگوهای تاریخ: 13xx/14xx یا 20xx (YYYY/MM/DD یا DD/MM/YYYY)
     * الگوهای مبلغ: 1,234,567.89 + (تومان|ریال) اختیاری
     */
    private function parseExpenseLine(string $line): ?array {
        $dateRegex1 = '(?:(?:13|14|20)\d{2}[\/\-.](?:0?[1-9]|1[0-2])[\/\-.](?:0?[1-9]|[12]\d|3[01]))'; // YYYY/MM/DD
        $dateRegex2 = '(?:(?:0?[1-9]|[12]\d|3[01])[\/\-.](?:0?[1-9]|1[0-2])[\/\-.](?:13|14|20)\d{2})'; // DD/MM/YYYY
        $amountRegex = '([0-9][0-9,\.]*)\s*(تومان|ریال)?';

        // حالت 1: تاریخ ... شرح ... مبلغ [ارز]
        $pattern1 = '/^(?P<date>'.$dateRegex1.'|'.$dateRegex2.')\s+(?P<desc>.+?)\s+(?P<amount>'.$amountRegex.')$/u';
        if (preg_match($pattern1, $line, $m)) {
            return [
                'date'       => $this->normalizeDate($m['date']),
                'amount'     => $this->normalizeAmount($m[3]),
                'currency'   => $m[4] ?? '',
                'description'=> trim($m['desc']),
                'source'     => $line,
            ];
        }

        // حالت 2: شرح ... تاریخ ... مبلغ
        $pattern2 = '/^(?P<desc>.+?)\s+(?P<date>'.$dateRegex1.'|'.$dateRegex2.')\s+(?P<amount>'.$amountRegex.')$/u';
        if (preg_match($pattern2, $line, $m)) {
            return [
                'date'       => $this->normalizeDate($m['date']),
                'amount'     => $this->normalizeAmount($m[3]),
                'currency'   => $m[4] ?? '',
                'description'=> trim($m['desc']),
                'source'     => $line,
            ];
        }

        // حالت 3: شرح، مبلغ [ارز]، تاریخ (با ویرگول/کاما)
        $pattern3 = '/^(?P<desc>.+?)[,\s]+(?P<amount>'.$amountRegex.')[,\s]+(?P<date>'.$dateRegex1.'|'.$dateRegex2.')$/u';
        if (preg_match($pattern3, $line, $m)) {
            return [
                'date'       => $this->normalizeDate($m['date']),
                'amount'     => $this->normalizeAmount($m[2]),
                'currency'   => $m[3] ?? '',
                'description'=> trim($m['desc']),
                'source'     => $line,
            ];
        }

        // اگر هیچ الگویی نخورد، null برگردان
        return null;
    }

    private function normalizeDate(string $d): string {
        $d = str_replace(['.', '-'], '/', $d);
        // اگر تاریخ DD/MM/YYYY بود، به YYYY/MM/DD تبدیل کن
        if (preg_match('/^(?<d>\d{1,2})\/(?<m>\d{1,2})\/(?<y>(?:13|14|20)\d{2})$/', $d, $m)) {
            return sprintf('%04d/%02d/%02d', (int)$m['y'], (int)$m['m'], (int)$m['d']);
        }
        // اگر تاریخ همین الان YYYY/MM/DD است:
        if (preg_match('/^(?<y>(?:13|14|20)\d{2})\/(?<m>\d{1,2})\/(?<d>\d{1,2})$/', $d, $m)) {
            return sprintf('%04d/%02d/%02d', (int)$m['y'], (int)$m['m'], (int)$m['d']);
        }
        return $d; // در غیر اینصورت همان را برگردان
    }

    private function normalizeAmount(string $a): float {
        $a = str_replace([','], '', $a);
        return (float)$a;
    }

    private function writeExcel(array $rows, string $originalName): string {
        $dt = date('Ymd_His');
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $relDir = 'exports';
        $absDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . $relDir;
        if (!is_dir($absDir)) mkdir($absDir, 0777, true);
        $relPath = $relDir . "/expenses_{$safeName}_{$dt}.xlsx";
        $absPath = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . $relPath;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Expenses');

        // Header
        $sheet->fromArray(['Date(YYYY/MM/DD)','Amount','Currency','Description','Source Line'], null, 'A1');

        // Rows
        $r = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$r}", $row['date']);
            $sheet->setCellValue("B{$r}", $row['amount']);
            $sheet->setCellValue("C{$r}", $row['currency']);
            $sheet->setCellValue("D{$r}", $row['description']);
            $sheet->setCellValue("E{$r}", $row['source']);
            $r++;
        }

        // Format amount
        $sheet->getStyle("B2:B{$r}")->getNumberFormat()->setFormatCode('#,##0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($absPath);

        return $relPath; // مسیر نسبی برای لینک‌دهی در مرورگر
    }
}