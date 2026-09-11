<?php
// controllers/_portal_invoice_query.php
declare(strict_types=1);

function _hascol(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
    $st->execute([':t' => $table, ':c' => $col]);
    return (bool)$st->fetchColumn();
}
function _pickcol(PDO $pdo, string $table, array $cands, ?string $fallback): ?string
{
    foreach ($cands as $c) if (_hascol($pdo, $table, $c)) return $c;
    return $fallback;
}

/**
 * برمی‌گرداند فاکتورهای ساکن + مبلغ/پرداخت/مانده/وضعیت
 * فیلترها: unit_id, kind, utility_type, period, status ('paid'|'partial'|'unpaid'), limit
 */
function portal_invoices_for_resident(PDO $pdo, int $residentId, array $filter = []): array
{
    $colSubject = _pickcol($pdo, 'invoices', ['subject', 'title', 'description', 'name'], 'subject');
    $colAmount  = _pickcol($pdo, 'invoices', ['amount', 'total_amount', 'total', 'price'], 'amount');
    $colIssue   = _pickcol($pdo, 'invoices', ['issue_date', 'created_at', 'created', 'date'], null);
    $colDue     = _pickcol($pdo, 'invoices', ['due_date', 'deadline', 'due'], null);
    $colPeriod  = _hascol($pdo, 'invoices', 'period')       ? 'period'       : null;
    $colKind    = _hascol($pdo, 'invoices', 'kind')         ? 'kind'         : null;
    $colUType   = _hascol($pdo, 'invoices', 'utility_type') ? 'utility_type' : null;

    // فقط فاکتورهای واحدهای منتسب به این ساکن
    $conds = ["EXISTS (
        SELECT 1 FROM ownerships oo
        WHERE oo.unit_id = i.unit_id
          AND oo.resident_id = :rid
          AND (oo.end_date IS NULL OR oo.end_date >= CURDATE())
    )"];
    $params = [':rid' => $residentId];

    if (!empty($filter['unit_id'])) {
        $conds[] = "i.unit_id = :uid";
        $params[':uid'] = (int)$filter['unit_id'];
    }
    if ($colKind && ($filter['kind'] ?? '') !== '') {
        $conds[] = "i.$colKind = :kind";
        $params[':kind'] = (string)$filter['kind']; // 'utility' برای قبض‌ها
    }
    if ($colUType && ($filter['utility_type'] ?? '') !== '') {
        $conds[] = "i.$colUType = :ut";
        $params[':ut'] = (string)$filter['utility_type']; // water/gas/...
    }
    if ($colPeriod && ($filter['period'] ?? '') !== '') {
        $conds[] = "i.$colPeriod = :per";
        $params[':per'] = (string)$filter['period'];
    }
    $where = 'WHERE ' . implode(' AND ', $conds);
    $limit = isset($filter['limit']) ? max(1, min((int)$filter['limit'], 2000)) : 1000;

    // نکته‌ها:
    //  - CAST روی مبلغ: اگر amount رشته‌ای باشد، به عددِ واقعی تبدیل می‌شود (ویرگول حذف می‌شود).
    //  - paid_amount با زیرپرس‌وجوی همبسته → گروه‌بندی نیاز ندارد و خطای join از بین می‌رود.
    $sql = "
        SELECT
            i.id, i.unit_id,
            u.name AS unit_name, u.floor AS unit_floor,
            i.$colSubject AS subject,
            CAST(REPLACE(i.$colAmount, ',', '') AS DECIMAL(18,3)) AS invoice_amount," .
        ($colPeriod ? " i.$colPeriod AS period,"     : " NULL AS period,") .
        ($colIssue  ? " i.$colIssue  AS issue_date," : " NULL AS issue_date,") .
        ($colDue    ? " i.$colDue    AS due_date,"   : " NULL AS due_date,") . "
            COALESCE((
              SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id
            ), 0) AS paid_amount
        FROM invoices i
        JOIN units u ON u.id = i.unit_id
        $where
        ORDER BY " . ($colPeriod ? "i.$colPeriod DESC, " : "") . ($colIssue ? "i.$colIssue DESC, " : "") . "i.id DESC
        LIMIT $limit
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // محاسبه مانده/وضعیت در PHP (دیدنی و البته با عدد صحیح)
    foreach ($rows as &$r) {
        $amount  = (float)$r['invoice_amount'];
        $paid    = (float)$r['paid_amount'];
        $balance = max(0, round($amount - $paid, 2));
        $r['balance'] = $balance;
        $r['_status'] = ($paid >= $amount - 1e-3) ? 'paid' : (($paid > 0) ? 'partial' : 'unpaid');
    }
    unset($r);

    // فیلتر وضعیت (اختیاری)
    $statusFilter = (string)($filter['status'] ?? '');
    if ($statusFilter !== '') {
        $rows = array_values(array_filter($rows, fn($r) => (string)$r['_status'] === $statusFilter));
    }

    return $rows;
}
