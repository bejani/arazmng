<?php /* نمایش یک تیکت */ ?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">تیکت</h4>
    <a class="btn btn-secondary" href="index.php?page=portal_tickets">بازگشت</a>
</div>

<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success">
    <?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger">
    <?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<div class="card card-elevated mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <h5 class="mb-0"><?= h($ticket['subject'] ?? '') ?></h5>
            <?php
        $status = (string)($ticket['status'] ?? '');
        $chip = 'chip chip-unpaid'; $label='باز';
        if ($status==='pending'){ $chip='chip chip-partial'; $label='در حال رسیدگی'; }
        if ($status==='closed'){  $chip='chip chip-paid';    $label='بسته'; }
      ?>
            <span class="<?= $chip ?>"><?= h($label) ?></span>
        </div>
        <div class="text-muted small mb-3">
            ایجاد: <?= h(jdate($ticket['created_at'] ?? null, 'Y/m/d H:i')) ?>
            <?php if(!empty($ticket['updated_at'])): ?> • بروزرسانی:
            <?= h(jdate($ticket['updated_at'], 'Y/m/d H:i')) ?><?php endif; ?>
        </div>
        <div class="mb-2" style="white-space:pre-wrap"><?= h($ticket['body'] ?? '') ?></div>
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
        <div class="vstack gap-2">
            <?php foreach ($messages as $m): $isMe = ($m['sender_type']==='resident'); ?>
            <div class="p-2 rounded-3 border <?= $isMe ? 'bg-light' : '' ?>">
                <div class="small text-muted mb-1"><?= $isMe ? 'شما' : 'مدیریت' ?> •
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
        <div class="text-muted">هنوز پیامی ثبت نشده است.</div>
        <?php endif; ?>
    </div>
</div>

<?php $isClosed = ($ticket['status'] ?? '') === 'closed'; ?>
<?php if (!$isClosed): ?>
<div class="card card-elevated">
    <div class="card-body">
        <h6 class="mb-3">ارسال پاسخ</h6>
        <form method="POST" action="index.php?page=portal_ticket_reply" enctype="multipart/form-data" class="row g-3">
            <?= function_exists('csrf_field') ? csrf_field() : '' ?>
            <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
            <div class="col-12">
                <textarea name="message" rows="4" class="form-control" required></textarea>
            </div>
            <div class="col-12 col-md-6">
                <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">ارسال</button>
                <form method="POST" action="index.php?page=portal_ticket_close" class="d-inline">
                    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
                    <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                    <button class="btn btn-outline-danger" formaction="index.php?page=portal_ticket_close"
                        formmethod="POST" onclick="return confirm('تیکت بسته شود؟')">بستن تیکت</button>
                </form>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info mt-3">این تیکت بسته شده است.</div>
<?php endif; ?>