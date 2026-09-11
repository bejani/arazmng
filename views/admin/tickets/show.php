<?php ob_start(); ?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">تیکت #<?= (int)$ticket['id'] ?> — <?= h($ticket['subject'] ?? '') ?></h4>
    <a class="btn btn-secondary" href="index.php?page=admin_tickets">بازگشت</a>
</div>

<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success">
    <?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
    <?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<div class="card card-elevated mb-3">
    <div class="card-body">
        <div class="row g-2 small text-muted">
            <div class="col-12 col-md">ساکن: <strong class="text-body"><?= h($ticket['full_name'] ?? '') ?></strong>
            </div>
            <div class="col-12 col-md">موبایل: <span dir="ltr"><?= h($ticket['mobile'] ?? '') ?></span></div>
            <div class="col-12 col-md">ایجاد: <?= h(jdate($ticket['created_at'] ?? null, 'Y/m/d H:i')) ?></div>
            <?php if(!empty($ticket['updated_at'])): ?>
            <div class="col-12 col-md">بروزرسانی: <?= h(jdate($ticket['updated_at'], 'Y/m/d H:i')) ?></div>
            <?php endif; ?>
        </div>
        <hr>
        <div style="white-space:pre-wrap"><?= h($ticket['body'] ?? '') ?></div>
        <?php if (!empty($ticket['file_path'])): ?>
        <div class="mt-2"><a class="btn btn-sm btn-outline-secondary" target="_blank"
                href="<?= h($ticket['file_path']) ?>">دانلود پیوست اولیه</a></div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-elevated mb-3">
    <div class="card-body">
        <h6 class="mb-3">گفت‌وگو</h6>
        <?php if (!empty($messages)): ?>
        <div class="thread">
            <?php foreach($messages as $m): $isAdmin = ($m['sender_type']==='admin'); ?>
            <div class="msg <?= $isAdmin?'msg--admin':'' ?>">
                <div class="meta"><?= $isAdmin ? 'ادمین' : 'ساکن' ?> •
                    <?= h(jdate($m['created_at'] ?? null, 'Y/m/d H:i')) ?></div>
                <div style="white-space:pre-wrap"><?= h($m['message']) ?></div>
                <?php if (!empty($m['file_path'])): ?>
                <div class="mt-1"><a class="btn btn-sm btn-outline-secondary" target="_blank"
                        href="<?= h($m['file_path']) ?>">دانلود پیوست</a></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-muted">پیامی ثبت نشده است.</div>
        <?php endif; ?>
    </div>
</div>

<?php $isClosed = ($ticket['status'] ?? '') === 'closed'; ?>
<div class="card card-elevated">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 btn-group-wrap">
            <h6 class="mb-0">ارسال پاسخ ادمین</h6>
            <div class="d-flex gap-2 btn-group-wrap">
                <?php if ($isClosed): ?>
                <form method="POST" action="index.php?page=admin_ticket_reopen" class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                    <button class="btn btn-outline-secondary">باز کردن تیکت</button>
                </form>
                <?php else: ?>
                <form method="POST" action="index.php?page=admin_ticket_close" class="d-inline"
                    onsubmit="return confirm('بسته شود؟')">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                    <button class="btn btn-outline-danger">بستن تیکت</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isClosed): ?>
        <div class="alert alert-info mb-0">این تیکت بسته است؛ برای پاسخ ابتدا آن را باز کنید.</div>
        <?php else: ?>
        <form method="POST" action="index.php?page=admin_ticket_reply" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
            <div class="col-12">
                <textarea name="message" rows="4" class="form-control" required></textarea>
            </div>
            <div class="col-12 col-md-6">
                <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf">
            </div>
            <div class="col-12">
                <button class="btn btn-primary">ارسال پاسخ</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>