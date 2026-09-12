<?php
declare(strict_types=1);

// Backward-compatible alias for legacy includes. New code should include db.php directly.
require_once __DIR__ . '/db.php';
$conn = $pdo;
