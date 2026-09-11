<?php
$host = "localhost";
$db   = "building_mgmt";
$user = "root"; // پیش‌فرض لاراگون
$pass = "4562";     // معمولاً خالیه
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo = new PDO($dsn, $user, $pass, $options);