<?php
// layout_resolver.php
// تابع کمکی برای وارد کردن layout.php از مسیرهای متداول؛ در صورت عدم وجود، $content چاپ می‌شود.
if (!function_exists('view_include_layout')) {
    function view_include_layout(string $content, array $extraCandidates = []): void {
        $candidates = array_merge($extraCandidates, [
            __DIR__ . '/views/layout.php',   // ریشه پروژه/views/layout.php
            __DIR__ . '/layout.php',         // ریشه پروژه/layout.php
        ]);
        $layout = null;
        foreach ($candidates as $p) {
            if (is_string($p) && file_exists($p)) { $layout = $p; break; }
        }
        if ($layout) { include $layout; } else { echo $content; }
    }
}
