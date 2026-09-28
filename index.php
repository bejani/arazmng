<?php
// index.php — Router اصلی (PDO)
// نکته: داشبورد/کارت‌ها داخل home.php هستند
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$isDevelopment = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL);
ini_set('display_errors', $isDevelopment ? '1' : '0');
error_reporting($isDevelopment ? E_ALL : 0);

// --- DB ---
require_once __DIR__ . '/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo '<h3>DB Error:</h3><pre>db.php باید متغیر $pdo (از نوع PDO) را مقداردهی کند.</pre>';
    exit;
}

// --- Controllers ---
require_once __DIR__ . '/controllers/UnitController.php';
require_once __DIR__ . '/controllers/ResidentController.php';
require_once __DIR__ . '/controllers/ChargeController.php';
require_once __DIR__ . '/controllers/InvoiceController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/ResidentPortalController.php';
require_once __DIR__ . '/controllers/OwnershipController.php';
require_once __DIR__ . '/controllers/ExpenseController.php';
require_once __DIR__ . '/controllers/ExpenseCategoryController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/UtilityController.php';
require_once __DIR__ . '/controllers/PortalInvoicesController.php';
require_once __DIR__ . '/controllers/PettyCashController.php';



// Admin: Announcements & Tickets
require_once __DIR__ . '/controllers/AdminAnnouncementsController.php';
require_once __DIR__ . '/controllers/AdminTicketsController.php';
require_once __DIR__ . '/controllers/ElectionController.php';

// --- Auth / Session guards ---
require_once __DIR__ . '/auth.php';
check_admin_timeout();

$page = $_GET['page'] ?? 'home';

// POST + CSRF guard
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
    require_csrf();
}

// صفحاتی که فقط ادمین می‌تواند ببیند
$adminPages = [
    // Admin: Announcements
    'admin_announcements',
    'admin_announcement_new',
    'admin_announcement_store',
    'admin_announcement_edit',
    'admin_announcement_update',
    'admin_announcement_delete',
    'admin_announcement_toggle_pin',
    'admin_announcement_toggle_visibility',

    // Admin: Tickets
    'admin_tickets',
    'admin_ticket_show',
    'admin_ticket_reply',
    'admin_ticket_close',
    'admin_ticket_reopen',
    // Trustee election
    'admin_elections',
    'admin_election_store',
    'admin_candidate_store',
    'admin_candidate_delete',
    'admin_election_status',

    // Units
    'units',
    'unit_edit',
    'unit_store',
    'unit_update',
    'unit_toggle_active',
    'unit_delete',

    // Residents
    'residents',
    'resident_edit',
    'resident_store',
    'resident_update',
    'resident_toggle_active',
    'resident_delete',

    // Ownerships
    'ownership_reassign',
    'ownership_reassign_store',

    // Invoices
    'invoices',
    'invoice_show',
    'invoice_delete',

    // Payments
    'payments',
    'payment_store',
    'payment_new',
    'payment_delete',

    // Charges
    'charges',
    'charge_new',
    'charge_generate',
    'charge_collect',
    'charge_quickpay',
    'charge_new_unit',
    'charge_generate_single',

    // Import
    'import_units_owners',

    // Expenses
    'expenses',
    'expense_store',
    'expense_edit',
    'expense_update',
    'expense_delete',

    // Expense Categories (اضافه شد)
    'expense_categories',
    'expense_category_store',
    'expense_category_edit',
    'expense_category_update',
    'expense_category_delete',

    'home',
];

// اگر صفحه ادمین است و لاگین نیست
if (in_array($page, $adminPages, true) && !is_admin()) {
    $_SESSION['error'] = 'برای دسترسی به بخش مدیریت، ابتدا وارد شوید.';
    header('Location: index.php?page=admin_login');
    exit;
}

// --- Routing ---
switch ($page) {

    // Admin auth
    case 'admin_login':
        (new AdminController($pdo))->login();
        break;
    case 'admin_do_login':
        require_post();
        (new AdminController($pdo))->doLogin();
        break;
    case 'admin_logout':
        (new AdminController($pdo))->logout();
        break;

    // Admin: Announcements
    case 'admin_announcements':
        (new AdminAnnouncementsController($pdo))->index();
        break;
    case 'admin_announcement_new':
        (new AdminAnnouncementsController($pdo))->new();
        break;
    case 'admin_announcement_store':
        require_post();
        (new AdminAnnouncementsController($pdo))->store();
        break;
    case 'admin_announcement_edit':
        (new AdminAnnouncementsController($pdo))->edit();
        break;
    case 'admin_announcement_update':
        require_post();
        (new AdminAnnouncementsController($pdo))->update();
        break;
    case 'admin_announcement_delete':
        require_post();
        (new AdminAnnouncementsController($pdo))->delete();
        break;
    case 'admin_announcement_toggle_pin':
        require_post();
        (new AdminAnnouncementsController($pdo))->togglePin();
        break;
    case 'admin_announcement_toggle_visibility':
        require_post();
        (new AdminAnnouncementsController($pdo))->toggleVisibility();
        break;

    // Admin: Tickets
    case 'admin_tickets':
        (new AdminTicketsController($pdo))->index();
        break;
    case 'admin_ticket_show':
        (new AdminTicketsController($pdo))->show();
        break;
    case 'admin_ticket_reply':
        require_post();
        (new AdminTicketsController($pdo))->reply();
        break;
    case 'admin_ticket_close':
        require_post();
        (new AdminTicketsController($pdo))->close();
        break;
    case 'admin_ticket_reopen':
        require_post();
        (new AdminTicketsController($pdo))->reopen();
        break;

    // Trustee election
    case 'admin_elections':
        (new ElectionController($pdo))->adminIndex();
        break;
    case 'admin_election_store':
        require_post();
        (new ElectionController($pdo))->adminElectionStore();
        break;
    case 'admin_candidate_store':
        require_post();
        (new ElectionController($pdo))->adminCandidateStore();
        break;
    case 'admin_candidate_delete':
        require_post();
        (new ElectionController($pdo))->adminCandidateDelete();
        break;
    case 'admin_election_status':
        require_post();
        (new ElectionController($pdo))->adminStatus();
        break;
    // Expense Categories
    case 'expense_categories':
        (new ExpenseCategoryController($pdo))->index();
        break;
    case 'expense_category_store':
        require_post();
        (new ExpenseCategoryController($pdo))->store();
        break;
    case 'expense_category_edit':
        (new ExpenseCategoryController($pdo))->edit();
        break;
    case 'expense_category_update':
        require_post();
        (new ExpenseCategoryController($pdo))->update();
        break;
    case 'expense_category_delete':
        require_post();
        (new ExpenseCategoryController($pdo))->delete();
        break;

    // Expenses
    case 'expenses':
        (new ExpenseController($pdo))->index();
        break;
    case 'expense_store':
        require_post();
        (new ExpenseController($pdo))->store();
        break;
    case 'expense_edit':
        (new ExpenseController($pdo))->edit();
        break;
    case 'expense_update':
        require_post();
        (new ExpenseController($pdo))->update();
        break;
    case 'expense_delete':
        require_post();
        (new ExpenseController($pdo))->delete();
        break;

    // Portal: expenses
    case 'portal_expenses':
        (new ResidentPortalController($pdo))->expenses();
        break;

    // Invoices
    case 'invoices':
        $controller = new InvoiceController($pdo);
        $action = $_GET['action'] ?? 'index';
        if (!method_exists($controller, $action)) {
            http_response_code(404);
            echo "Method '$action' not found";
            exit;
        }
        $controller->$action();
        break;
    case 'invoice_show':
        (new InvoiceController($pdo))->show();
        break;
    case 'invoice_delete':
        (new InvoiceController($pdo))->delete();
        break;
    case 'invoices_periods':
        (new InvoiceController($pdo))->periods();
        break;
    case 'portal_invoices':
        (new PortalInvoicesController($pdo))->index();
        break;


    // Payments
    case 'payments':
        (new PaymentController($pdo))->index();
        break;
    case 'payment_store':
        require_post();
        (new PaymentController($pdo))->store();
        break;
    case 'payment_new':
        (new PaymentController($pdo))->new();
        break;
    case 'payment_delete':
        (new PaymentController($pdo))->delete();
        break;
    case 'payment_edit':
        (new PaymentController($pdo))->edit();
        break;

    case 'payment_update':
        (new PaymentController($pdo))->update();
        break;



    // Charges
    case 'charge_new_unit':
        (new ChargeController($pdo))->newSingle();
        break;
    case 'charge_generate_single':
        require_post();
        (new ChargeController($pdo))->generateSingle();
        break;
    case 'charges':
        (new ChargeController($pdo))->index();
        break;
    case 'charge_new':
        (new ChargeController($pdo))->new();
        break;
    case 'charge_generate':
        require_post();
        (new ChargeController($pdo))->generate();
        break;
    case 'charge_collect':
        (new ChargeController($pdo))->collect();
        break;
    case 'charge_quickpay':
        require_post();
        (new ChargeController($pdo))->quickPay();
        break;

    // Units
    case 'units':
        (new UnitController($pdo))->index();
        break;
    case 'unit_edit':
        (new UnitController($pdo))->edit();
        break;
    case 'unit_store':
        require_post();
        (new UnitController($pdo))->store();
        break;
    case 'unit_update':
        require_post();
        (new UnitController($pdo))->update();
        break;
    case 'unit_toggle_active':
        require_post();
        (new UnitController($pdo))->toggleActive();
        break;
    case 'unit_delete':
        (new UnitController($pdo))->delete();
        break;

    // Ownerships
    case 'ownership_reassign':
        (new OwnershipController($pdo))->reassignForm();
        break;
    case 'ownership_reassign_store':
        require_post();
        (new OwnershipController($pdo))->reassignStore();
        break;

    // Residents
    case 'residents':
        (new ResidentController($pdo))->index();
        break;
    case 'resident_edit':
        (new ResidentController($pdo))->edit();
        break;
    case 'residents_create':
        (new ResidentController($pdo))->create();
        break;
    case 'resident_store':
        require_post();
        (new ResidentController($pdo))->store();
        break;
    case 'resident_update':
        require_post();
        (new ResidentController($pdo))->update();
        break;
    case 'resident_toggle_active':
        require_post();
        (new ResidentController($pdo))->toggleActive();
        break;
    case 'resident_delete':
        (new ResidentController($pdo))->delete();
        break;

    //---------------- Portal--------------------
    case 'portal_login':
        (new ResidentPortalController($pdo))->login();
        break;
    case 'portal_do_login':
        require_post();
        (new ResidentPortalController($pdo))->doLogin();
        break;
    case 'portal_logout':
        (new ResidentPortalController($pdo))->logout();
        break;
    case 'portal_dashboard':
        (new ResidentPortalController($pdo))->dashboard();
        break;
    case 'portal_profile':
        (new ResidentPortalController($pdo))->profile();
        break;
    case 'portal_change_pin':
        (new ResidentPortalController($pdo))->changePinForm();
        break;
    case 'portal_do_change_pin':
        require_post();
        (new ResidentPortalController($pdo))->changePinStore();
        break;

    case 'portal_announcements':
        (new ResidentPortalController($pdo))->announcements();
        break;
    case 'portal_election':
        (new ElectionController($pdo))->portalIndex();
        break;
    case 'portal_election_vote':
        require_post();
        (new ElectionController($pdo))->portalVote();
        break;
    case 'portal_tickets':
        (new ResidentPortalController($pdo))->tickets();
        break;
    case 'portal_ticket_new':
        (new ResidentPortalController($pdo))->ticketNew();
        break;
    case 'portal_ticket_store':
        require_post();
        (new ResidentPortalController($pdo))->ticketStore();
        break;
    case 'portal_ticket_show':
        (new ResidentPortalController($pdo))->ticketShow();
        break;
    case 'portal_ticket_reply':
        require_post();
        (new ResidentPortalController($pdo))->ticketReply();
        break;
    case 'portal_ticket_close':
        require_post();
        (new ResidentPortalController($pdo))->ticketClose();
        break;

    //----------------Utility Bills--------------------

    case 'utilities':
        (new UtilityController($pdo))->index();
        break;

    case 'utility_run':
        (new UtilityController($pdo))->run();
        break;

    case 'utility_pay':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') (new UtilityController($pdo))->pay();
        else {
            header('Location: index.php?page=utility');
            exit;
        }
        break;


    // تنخواه
    case 'pettycash':
        (new PettyCashController($pdo))->index();
        break;
    case 'pettycash_create':
        (new PettyCashController($pdo))->create();
        break;

    case 'pettycash_run':
        (new PettyCashController($pdo))->run();
        break;

    case 'pettycash_store':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new PettyCashController($pdo))->store();
        } else {
            header('Location: index.php?page=pettycash');
            exit;
        }
        break;
    case 'pettycash_pay':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new PettyCashController($pdo))->pay();
        } else {
            header('Location: index.php?page=pettycash');
            exit;
        }
        break;

    // Home
    case 'home':
    default:
        if (file_exists(__DIR__ . '/home.php')) {
            include __DIR__ . '/home.php';
            break;
        }
        // fallback بسیار ساده
        echo '<div style="padding:20px;font-family:sans-serif">home.php یافت نشد. <a href="index.php?page=units">واحدها</a> • <a href="index.php?page=residents">ساکنان</a></div>';
        break;
}
