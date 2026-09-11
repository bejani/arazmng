<?php
// views/charges/layout_finder.php — include layout from common places or echo content
$candidates = [
  __DIR__ . '/../layout.php',      // views/layout.php
  __DIR__ . '/../../layout.php',   // project root/layout.php
];
$layout = null;
foreach ($candidates as $p) { if (file_exists($p)) { $layout = $p; break; } }
if ($layout) { include $layout; } else { echo $content; }
