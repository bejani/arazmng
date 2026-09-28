<?php require_once __DIR__ . '/../../_helpers.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-1">انتخابات هیئت امنا</h2><p class="text-muted mb-0">ثبت رأی فقط یک‌بار برای هر ساکن امکان‌پذیر است.</p></div></div>
<?php if (!empty($_SESSION['ok'])): ?><div class="alert alert-success"><?= h($_SESSION['ok']); unset($_SESSION['ok']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if (!$election): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">در حال حاضر انتخابات فعالی برای نمایش وجود ندارد.</div></div>
<?php else: ?>
    <div class="card shadow-sm mb-4"><div class="card-body"><h4><?= h($election['title']) ?></h4><?php if ($election['description']): ?><p class="mb-0" style="white-space:pre-wrap"><?= h($election['description']) ?></p><?php endif; ?><?php if ($election['starts_at'] || $election['ends_at']): ?><div class="small text-muted mt-3">بازه رأی‌گیری: <?= h(jdate($election['starts_at'], 'Y/m/d H:i')) ?> تا <?= h(jdate($election['ends_at'], 'Y/m/d H:i')) ?></div><?php endif; ?></div></div>
    <?php if ($canVote): ?>
        <form method="post" action="index.php?page=portal_election_vote">
            <?= csrf_field() ?><input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <div class="row g-3">
                <?php foreach ($candidates as $c): ?><div class="col-md-6"><label class="card h-100 shadow-sm candidate-card"><div class="card-body d-flex gap-3"><input class="form-check-input mt-2" type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" required><?php if ($c['photo_url']): ?><img src="<?= h($c['photo_url']) ?>" alt="تصویر <?= h($c['full_name']) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:50%"><?php endif; ?><div><h5><?= h($c['full_name']) ?></h5><?php if ($c['bio']): ?><p class="text-muted small mb-0" style="white-space:pre-wrap"><?= nl2br(h($c['bio'])) ?></p><?php endif; ?></div></div></label></div><?php endforeach; ?>
            </div>
            <div class="text-center mt-4"><button class="btn btn-primary btn-lg px-5" onclick="return confirm('رأی شما نهایی و غیرقابل تغییر است. ادامه می‌دهید؟');">ثبت رأی نهایی</button></div>
        </form>
    <?php elseif ($votedCandidateId !== null && $election['status'] === 'open'): ?>
        <div class="alert alert-success">رأی شما ثبت شده است. پس از پایان رأی‌گیری و تأیید ادمین، نتیجه برای همه نمایش داده می‌شود.</div>
    <?php elseif ($election['results_published']): ?>
        <?php $maxVotes = 0; foreach ($candidates as $c) $maxVotes = max($maxVotes, (int)$c['votes_count']); ?>
        <div class="alert alert-success">نتایج انتخابات توسط ادمین تأیید و منتشر شده است.</div>
        <div class="row g-3"><?php foreach ($candidates as $c): $votes=(int)$c['votes_count']; $percent=$maxVotes > 0 ? round($votes / $maxVotes * 100) : 0; ?><div class="col-md-6"><div class="card shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between"><strong><?= h($c['full_name']) ?></strong><span><?= number_format($votes) ?> رأی</span></div><div class="progress mt-3" role="progressbar"><div class="progress-bar" style="width:<?= $percent ?>%"></div></div></div></div></div><?php endforeach; ?></div>
    <?php else: ?>
        <div class="alert alert-secondary">رأی‌گیری پایان یافته است؛ نتیجه پس از تأیید ادمین نمایش داده خواهد شد.</div>
    <?php endif; ?>
<?php endif; ?>
<style>.candidate-card{cursor:pointer;transition:.15s}.candidate-card:hover{border-color:#0d6efd;transform:translateY(-2px)}.candidate-card:has(input:checked){border:2px solid #0d6efd;background:#f0f7ff}</style>
