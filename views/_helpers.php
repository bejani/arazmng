<?php
// views/_helpers.php  — escape/format + Jalali helpers + CSRF

declare(strict_types=1);

/** HTML-escape برای هر نوع مقداری؛ خودش به string تبدیل می‌کند. */
function h($s): string
{
  if ($s === null)        $s = '';
  elseif (is_bool($s))    $s = $s ? '1' : '0';
  elseif (is_int($s) || is_float($s)) $s = (string)$s;
  elseif (!is_string($s)) $s = (string)$s; // fallback برای سایر اسکار/آبجکت‌ها
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}


// خروجی CSV با BOM برای اکسل
function csv_output(string $filename, array $rows, array $columns): void
{
  // $columns = ['کلیدِ_آرایه' => 'عنوان ستون در خروجی', ...]
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $out = fopen('php://output', 'w');
  // BOM برای نمایش درست فارسی در Excel
  fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
  // هدرها
  fputcsv($out, array_values($columns));
  // سطرها
  foreach ($rows as $r) {
    $line = [];
    foreach ($columns as $k => $_label) {
      $line[] = isset($r[$k]) ? (is_scalar($r[$k]) ? (string)$r[$k] : json_encode($r[$k], JSON_UNESCAPED_UNICODE)) : '';
    }
    fputcsv($out, $line);
  }
  fclose($out);
  exit;
}


if (!function_exists('h')) {
  function h(?string $s): string
  {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
if (!function_exists('money')) {
  function money(float $v, int $dec = 0): string
  {
    return number_format($v, $dec, '.', ',');
  }
}

/* ---------- Digits ---------- */
if (!function_exists('fa_num')) {
  function fa_num(string $s): string
  {
    $map = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '-' => '-', '/' => '/'];
    return strtr($s, $map);
  }
}
if (!function_exists('en_num')) {
  function en_num(string $s): string
  {
    $map = [
      '۰' => '0',
      '۱' => '1',
      '۲' => '2',
      '۳' => '3',
      '۴' => '4',
      '۵' => '5',
      '۶' => '6',
      '۷' => '7',
      '۸' => '8',
      '۹' => '9',
      '٠' => '0',
      '١' => '1',
      '٢' => '2',
      '٣' => '3',
      '٤' => '4',
      '٥' => '5',
      '٦' => '6',
      '٧' => '7',
      '٨' => '8',
      '٩' => '9'
    ];
    return strtr($s, $map);
  }
}

/* ---------- Gregorian ⇄ Jalali ---------- */
if (!function_exists('gregorian_to_jalali')) {
  function gregorian_to_jalali(int $gy, int $gm, int $gd): array
  {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
      $jy += intdiv($days - 1, 365);
      $days = ($days - 1) % 365;
    }
    if ($days < 186) {
      $jm = 1 + intdiv($days, 31);
      $jd = 1 + ($days % 31);
    } else {
      $jm = 7 + intdiv($days - 186, 30);
      $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
  }
}
if (!function_exists('jalali_to_gregorian')) {
  function jalali_to_gregorian(int $jy, int $jm, int $jd): array
  {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33 + 3), 4) + $jd + (($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186));
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
      $gy += 100 * intdiv(--$days, 36524);
      $days %= 36524;
      if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
      $gy += intdiv($days - 1, 365);
      $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13; $gm++) {
      $v = $sal_a[$gm];
      if ($gd <= $v) break;
      $gd -= $v;
    }
    return [$gy, $gm, $gd];
  }
}

/* ---------- Format Jalali for UI ---------- */
if (!function_exists('jdate')) {
  function jdate(?string $gDate, string $fmt = 'Y/m/d', bool $faDigits = true): string
  {
    if (!$gDate) return '';
    $gDate = trim($gDate);
    if ($gDate === '' || $gDate === '0000-00-00') return '';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $gDate, $m)) return $gDate;
    [$jy, $jm, $jd] = gregorian_to_jalali((int)$m[1], (int)$m[2], (int)$m[3]);
    $months = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $rep = ['Y' => sprintf('%04d', $jy), 'm' => sprintf('%02d', $jm), 'n' => "$jm", 'd' => sprintf('%02d', $jd), 'j' => "$jd", 'F' => $months[$jm] ?? ''];
    $out = str_replace(['Y', 'm', 'n', 'd', 'j', 'F'], ['{Y}', '{m}', '{n}', '{d}', '{j}', '{F}'], $fmt);
    $out = strtr($out, ['{Y}' => $rep['Y'], '{m}' => $rep['m'], '{n}' => $rep['n'], '{d}' => $rep['d'], '{j}' => $rep['j'], '{F}' => $rep['F']]);
    return $faDigits ? fa_num($out) : $out;
  }
}

/* ---------- Accept Jalali input, return Y-m-d (Gregorian) ---------- */
if (!function_exists('normalize_date_input')) {
  function normalize_date_input(?string $input): ?string
  {
    if (!$input) return null;
    $s = en_num(trim($input));
    if ($s === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    if (preg_match('/^(13|14)\d{2}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $s)) {
      $parts = preg_split('/[\/\-]/', $s);
      [$gy, $gm, $gd] = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
    return $s;
  }
}

/* ---------- CSRF ---------- */
if (!function_exists('csrf_field')) {
  function csrf_field(): string
  {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="_csrf" value="' . h($_SESSION['_csrf']) . '">';
  }
}
if (!function_exists('csrf_value')) {
  function csrf_value(): string
  {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
  }
}
