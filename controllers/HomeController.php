<?php
class HomeController {
    public function index($pdo) {
        // تعداد رکوردها برای نمایش در داشبورد
        $units = $pdo->query("SELECT COUNT(*) AS c FROM units")->fetch()['c'];
        $residents = $pdo->query("SELECT COUNT(*) AS c FROM residents")->fetch()['c'];
        $invoices = $pdo->query("SELECT COUNT(*) AS c FROM invoices")->fetch()['c'];
        $payments = $pdo->query("SELECT COUNT(*) AS c FROM payments")->fetch()['c'];

        include __DIR__."/../views/home.php";
    }
}