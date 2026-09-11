<?php ob_start();
require_once __DIR__ . '/../_helpers.php';
$units = $units ?? []; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">ایجاد قبض و تقسیم بین واحدها</h3>
</div>

<div class="card card-elevated">
    <div class="card-body">
        <form method="POST" action="index.php?page=utilities_store" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-md-3">
                <label class="form-label">نوع قبض</label>
                <select name="utility_type" class="form-select" required>
                    <option value="gas">گاز</option>
                    <option value="water">آب</option>
                    <option value="electric">برق</option>
                    <option value="other">سایر</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">دوره (YYYY-MM)</label>
                <input type="text" name="period" class="form-control" placeholder="1403-06" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">مبلغ کل قبض (تومان)</label>
                <input type="number" name="bill_amount" step="1" min="0" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">روش تقسیم</label>
                <select name="method" class="form-select" id="util-method" required>
                    <option value="equal">برابر</option>
                    <option value="weights">وزنی (ضریب دلخواه)</option>
                    <option value="consumption">مصرف واقعی</option>
                </select>
            </div>

            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>واحد</th>
                                <th>طبقه</th>
                                <th class="text-center">
                                    <span class="label-equal">وزن پیش‌فرض = 1</span>
                                    <span class="label-weights d-none">ضریب</span>
                                    <span class="label-cons d-none">مصرف</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $i => $u): $uid = (int)$u['id']; ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= h($u['name'] ?? '') ?></td>
                                    <td><?= (int)($u['floor'] ?? 0) ?></td>
                                    <td class="text-center">
                                        <input type="number" step="0.001" min="0"
                                            class="form-control d-inline-block w-auto basis-w" name="w[<?= $uid ?>]"
                                            value="1">
                                        <input type="number" step="0.001" min="0"
                                            class="form-control d-inline-block w-auto basis-c d-none" name="c[<?= $uid ?>]"
                                            value="0">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">توضیحات (اختیاری)</label>
                <textarea name="note" rows="2" class="form-control" placeholder="توضیح..."></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-success">ثبت و صدور فاکتور برای واحدها</button>
                <a href="index.php?page=utilities" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const sel = document.getElementById('util-method');
        const le = document.querySelector('.label-equal');
        const lw = document.querySelector('.label-weights');
        const lc = document.querySelector('.label-cons');
        const ws = document.querySelectorAll('.basis-w');
        const cs = document.querySelectorAll('.basis-c');

        function apply() {
            const m = sel.value;
            if (m === 'equal') {
                le.classList.remove('d-none');
                lw.classList.add('d-none');
                lc.classList.add('d-none');
                ws.forEach(i => {
                    i.classList.remove('d-none');
                    i.value = 1;
                });
                cs.forEach(i => {
                    i.classList.add('d-none');
                    i.value = 0;
                });
            } else if (m === 'weights') {
                le.classList.add('d-none');
                lw.classList.remove('d-none');
                lc.classList.add('d-none');
                ws.forEach(i => {
                    i.classList.remove('d-none');
                    if (+i.value === 0) i.value = 1;
                });
                cs.forEach(i => {
                    i.classList.add('d-none');
                    i.value = 0;
                });
            } else {
                le.classList.add('d-none');
                lw.classList.add('d-none');
                lc.classList.remove('d-none');
                ws.forEach(i => {
                    i.classList.add('d-none');
                    i.value = 0;
                });
                cs.forEach(i => {
                    i.classList.remove('d-none');
                });
            }
        }
        sel.addEventListener('change', apply);
        apply();
    })();
</script>