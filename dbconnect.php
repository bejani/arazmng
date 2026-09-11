<?php
$host = "localhost";
$dbname = "zilbirir_users";
$username = "zilbirir_admin";
$password = "rwm[F!7GdE#AdO{J";

$conn = new mysqli($host, $username, $password, $dbname);
 
 if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');