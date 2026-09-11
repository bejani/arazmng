<?php
$host = "localhost";

// $db = "zilbirir_users";
// $user = "zilbirir_admin";
// $pass = "rwm[F!7GdE#AdO{J";

$db   = "building_mgmt";
$user = "root";  
$pass = "4562";   

$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo = new PDO($dsn, $user, $pass, $options);
