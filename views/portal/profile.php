<?php /* views/portal/profile.php */ ?>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card card-elevated">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div
                        style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#0ea5e9,#7c3aed);color:#fff;display:grid;place-items:center;font-weight:800;font-size:20px;">
                        <?php
              $n = trim((string)($resident['full_name'] ?? ''));
              $ini = '';
              if ($n !== '') {
                $parts = preg_split('/\s+/u',$n);
                $ini = mb_substr($parts[0] ?? '',0,1,'UTF-8') . mb_substr($parts[1] ?? '',0,1,'UTF-8');
              }
              echo htmlspecialchars($ini ?: '👤');
            ?>
                    </div>
                    <div>
                        <div class="fw-bold fs-5"><?= h($resident['full_name'] ?? '') ?></div>
                        <div class="text-muted small">
                            <?= h(($resident['type'] ?? '')==='owner' ? 'مالک' : (($resident['type'] ?? '')==='tenant' ? 'مستأجر' : 'ساکن')) ?>
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-12">
                        <div class="d-flex justify-content-between border rounded-3 p-2">
                            <span class="text-muted">موبایل</span>
                            <span class="fw-600"><?= h($resident['mobile'] ?? '—') ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between border rounded-3 p-2">
                            <span class="text-muted">ایمیل</span>
                            <span class="fw-600"><?= h($resident['email'] ?? '—') ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between border rounded-3 p-2">
                            <span class="text-muted">کد ملی</span>
                            <span class="fw-600"><?= h($resident['national_id'] ?? '—') ?></span>
                        </div>
                    </div>

                    <?php if (isset($resident['portal_last_login'])): ?>
                    <div class="col-12">
                        <div class="d-flex justify-content-between border rounded-3 p-2">
                            <span class="text-muted">آخرین ورود</span>
                            <span class="fw-600"><?= h($resident['portal_last_login'] ?? '—') ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($resident['portal_enabled'])): ?>
                    <div class="col-12">
                        <div class="d-flex justify-content-between border rounded-3 p-2">
                            <span class="text-muted">وضعیت پورتال</span>
                            <span
                                class="fw-600"><?= ((int)$resident['portal_enabled'] === 1) ? 'فعال' : 'غیرفعال' ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($resident['is_active'])): ?>
                    <div class="col-12">
                        <div class="d-flex justify-content-between border rounded-3 p-2">
                            <span class="text-muted">وضعیت ساکن</span>
                            <span class="fw-600"><?= ((int)$resident['is_active'] === 1) ? 'فعال' : 'غیرفعال' ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="index.php?page=portal_change_pin">تغییر رمز</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card card-elevated">
            <div class="card-body">
                <h5 class="card-title mb-3">واحدهای من</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle responsive-cards table-modern">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>واحد</th>
                                <th>طبقه</th>
                                <th>وضعیت مالکیت</th>
                                <th>شروع</th>
                                <th>پایان</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($units)): ?>
                            <?php foreach ($units as $i => $u):
                  $own = $ownerships[$u['id']] ?? null;
                  $start = isset($own['start_date']) ? jdate($own['start_date'], 'Y/m/d') : '—';
                  $end   = isset($own['end_date'])   ? jdate($own['end_date'],   'Y/m/d') : '—';
                  $active = empty($own['end_date']);
                ?>
                            <tr>
                                <td data-label="#"><?= $i+1 ?></td>
                                <td data-label="واحد"><?= h($u['name'] ?? '') ?></td>
                                <td data-label="طبقه"><?= isset($u['floor']) ? h((string)$u['floor']) : '—' ?></td>
                                <td data-label="وضعیت">
                                    <span class="chip <?= $active ? 'chip-paid' : 'chip-partial' ?>">
                                        <?= $active ? 'فعال' : 'غیرفعال' ?>
                                    </span>
                                </td>
                                <td data-label="شروع"><?= h($start) ?></td>
                                <td data-label="پایان"><?= h($end) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">هنوز واحدی برای شما ثبت نشده است.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small">* تاریخ‌ها به‌صورت شمسی نمایش داده می‌شوند.</div>
            </div>
        </div>
    </div>
</div>