<?php
class HomeController {
    public function index($pdo) {
        // تعداد رکوردها برای نمایش در داشبورد؛ یک round-trip به جای چهار query جدا.
        $counts = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM units) AS units,
                (SELECT COUNT(*) FROM residents) AS residents,
                (SELECT COUNT(*) FROM invoices) AS invoices,
                (SELECT COUNT(*) FROM payments) AS payments"
        )->fetch();
        $units = (int)($counts['units'] ?? 0);
        $residents = (int)($counts['residents'] ?? 0);
        $invoices = (int)($counts['invoices'] ?? 0);
        $payments = (int)($counts['payments'] ?? 0);

        include __DIR__."/../views/home.php";
    }
}
