<?php
// controllers/ExpenseCategoryController.php
declare(strict_types=1);
require_once __DIR__ . '/../models/ExpenseCategory.php';

final class ExpenseCategoryController
{
    public function __construct(private PDO $pdo) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function index(): void {
        $cats = ExpenseCategory::all($this->pdo);
        include __DIR__ . '/../views/expense_categories/index.php';
    }

    public function store(): void {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $_SESSION['error'] = 'نام دسته الزامی است.'; header("Location: index.php?page=expense_categories"); exit; }
        ExpenseCategory::create($this->pdo, $name);
        $_SESSION['ok'] = 'دسته افزوده شد.';
        header("Location: index.php?page=expense_categories"); exit;
    }

    public function edit(): void {
        $id = (int)($_GET['id'] ?? 0);
        $cat = ExpenseCategory::find($this->pdo, $id);
        if (!$cat) { die('دسته یافت نشد.'); }
        include __DIR__ . '/../views/expense_categories/edit.php';
    }

    public function update(): void {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $is_active = (int)($_POST['is_active'] ?? 0);
        if ($id<=0 || $name==='') { $_SESSION['error'] = 'نام دسته الزامی است.'; header("Location: index.php?page=expense_categories"); exit; }
        ExpenseCategory::update($this->pdo, $id, $name, $is_active);
        $_SESSION['ok'] = 'دسته بروزرسانی شد.';
        header("Location: index.php?page=expense_categories"); exit;
    }

    public function delete(): void {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) {
            try { ExpenseCategory::delete($this->pdo, $id); $_SESSION['ok']='حذف شد.'; }
            catch(Throwable $e) { $_SESSION['error']='حذف نشد (احتمالاً استفاده شده است).'; }
        }
        header("Location: index.php?page=expense_categories"); exit;
    }
}