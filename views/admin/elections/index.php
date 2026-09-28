<?php require_once __DIR__ . '/../../../views/_helpers.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1">انتخابات هیئت امنا</h2>
        <p class="text-muted mb-0">مدیریت انتخابات، کاندیداها و انتشار نتیجه نهایی</p>
    </div>
</div>
<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success"><?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header fw-bold">ایجاد انتخابات جدید</div>
            <div class="card-body">
                <form method="post" action="index.php?page=admin_election_store">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">عنوان</label><input name="title" class="form-control" placeholder="انتخابات هیئت امنای سال ..." required></div>
                    <div class="mb-3"><label class="form-label">توضیحات</label><textarea name="description" class="form-control" rows="3" placeholder="توضیحات و قوانین رأی‌گیری"></textarea></div>
                    <div class="mb-3"><label class="form-label">شروع رأی‌گیری</label><input type="datetime-local" name="starts_at" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">پایان رأی‌گیری</label><input type="datetime-local" name="ends_at" class="form-control"></div>
                    <button class="btn btn-primary w-100">ایجاد انتخابات</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-header fw-bold">انتخابات ثبت‌شده</div>
            <div class="list-group list-group-flush">
                <?php foreach ($elections as $e): ?>
                    <a class="list-group-item list-group-item-action <?= ((int)$e['id'] === (int)($selected['id'] ?? 0)) ? 'active' : '' ?>" href="index.php?page=admin_elections&eid=<?= (int)$e['id'] ?>">
                        <div class="d-flex justify-content-between gap-2"><span><?= h($e['title']) ?></span><span class="badge <?= $e['status']==='open'?'text-bg-success':($e['status']==='closed'?'text-bg-secondary':'text-bg-warning') ?>"><?= $e['status']==='open'?'باز':($e['status']==='closed'?'بسته':'پیش‌نویس') ?></span></div>
                        <?php if (!empty($e['results_published'])): ?><small>نتیجه منتشر شده</small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!$elections): ?><div class="list-group-item text-muted">هنوز انتخاباتی ثبت نشده است.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if ($selected): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div><h4><?= h($selected['title']) ?></h4><p class="text-muted mb-1" style="white-space:pre-wrap"><?= h($selected['description']) ?></p></div>
                        <div class="text-end"><span class="badge text-bg-info">کل رأی: <?= number_format($totalVotes) ?></span><?php if ($selected['results_published']): ?><div class="badge text-bg-success mt-2">نتیجه منتشر شده</div><?php endif; ?></div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($selected['status'] !== 'open'): ?><form method="post" action="index.php?page=admin_election_status"><?= csrf_field() ?><input type="hidden" name="election_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="open"><button class="btn btn-success">شروع رأی‌گیری</button></form><?php endif; ?>
                        <?php if ($selected['status'] === 'open'): ?><form method="post" action="index.php?page=admin_election_status" onsubmit="return confirm('رأی‌گیری بسته شود؟');"><?= csrf_field() ?><input type="hidden" name="election_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="close"><button class="btn btn-warning">بستن رأی‌گیری</button></form><?php endif; ?>
                        <?php if ($selected['status'] === 'closed' && !$selected['results_published']): ?><form method="post" action="index.php?page=admin_election_status" onsubmit="return confirm('نتایج برای همه کاربران قابل مشاهده شود؟');"><?= csrf_field() ?><input type="hidden" name="election_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="publish"><button class="btn btn-primary">تأیید و انتشار نتایج</button></form><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold">افزودن کاندیدا</div>
                <div class="card-body">
                    <form method="post" action="index.php?page=admin_candidate_store" class="row g-3">
                        <?= csrf_field() ?><input type="hidden" name="election_id" value="<?= (int)$selected['id'] ?>">
                        <div class="col-md-6"><label class="form-label">نام و نام خانوادگی</label><input name="full_name" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">موبایل</label><input name="mobile" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">کد ملی</label><input name="national_id" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label">معرفی و سوابق</label><textarea name="bio" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-4"><label class="form-label">لینک تصویر (اختیاری)</label><input type="url" name="photo_url" class="form-control"></div>
                        <div class="col-12"><button class="btn btn-outline-primary">ثبت کاندیدا</button></div>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header fw-bold">کاندیداها و نتایج</div>
                <div class="table-responsive"><table class="table table-striped mb-0 align-middle"><thead><tr><th>کاندیدا</th><th>مشخصات</th><th class="text-center">رأی</th><th>عملیات</th></tr></thead><tbody>
                <?php foreach ($candidates as $c): ?><tr><td><?php if ($c['photo_url']): ?><img src="<?= h($c['photo_url']) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:50%" class="me-2"><?php endif; ?><strong><?= h($c['full_name']) ?></strong></td><td class="small">موبایل: <?= h($c['mobile'] ?: '-') ?><br>کد ملی: <?= h($c['national_id'] ?: '-') ?><br><?= nl2br(h($c['bio'] ?: '')) ?></td><td class="text-center fw-bold"><?= number_format((int)$c['votes_count']) ?></td><td><form method="post" action="index.php?page=admin_candidate_delete" onsubmit="return confirm('این کاندیدا حذف شود؟');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">حذف</button></form></td></tr><?php endforeach; ?>
                <?php if (!$candidates): ?><tr><td colspan="4" class="text-center text-muted">کاندیدایی ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div>
            </div>
        <?php else: ?><div class="alert alert-info">برای شروع، یک انتخابات جدید ایجاد کنید.</div><?php endif; ?>
    </div>
</div>
