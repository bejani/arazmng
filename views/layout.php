<?php /* DEBUG */ /* echo "<!-- layout router loaded -->\n"; */ ?>

<?php
// views/layout.php — انتخاب خودکار لای‌اوت ادمین یا پورتال
require_once __DIR__ . '/../auth.php';     // is_admin(), is_portal(), csrf_value()
require_once __DIR__ . '/_helpers.php';    // h(), money(), csrf_field()

$isAdmin  = is_admin();
$isPortal = is_portal();

// اگر ادمین لاگین است → لای‌اوت ادمین
// اگر ساکن لاگین است → لای‌اوت پورتال
// در غیر این صورت: لای‌اوت ادمین با منوی حداقلی (فقط دکمه ورود)
if ($isAdmin) {
  include __DIR__ . '/layout_admin.php';
} elseif ($isPortal) {
  include __DIR__ . '/layout_portal.php';
} else {
  include __DIR__ . '/layout_admin.php';
}