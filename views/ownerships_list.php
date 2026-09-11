<?php ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>مالکیت واحدها</h2>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#ownershipForm">➕ افزودن مالکیت</button>
</div>

<div id="ownershipForm" class="collapse mb-3">
    <div class="card card-body">
        <form method="POST" action="index.php?page=ownership_store" class="row g-3">
            <div class="col-md-3">
                <select name="unit_id" class="form-select" required>
                    <option value="">-- انتخاب واحد --</option>
                    <?php foreach($units as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="resident_id" class="form-select" required>
                    <option value="">-- انتخاب ساکن --</option>
                    <?php foreach($residents as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="date" name="start_date" required></div>
            <div class="col-md-2"><input class="form-control" type="date" name="end_date"></div>
            <div class="col-md-1">
                <select name="is_primary" class="form-select">
                    <option value="1">اصلی</option>
                    <option value="0">فرعی</option>
                </select>
            </div>
            <div class="col-md-1"><button class="btn btn-success w-100">ذخیره</button></div>
        </form>
    </div>
</div>

<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>واحد</th>
            <th>ساکن</th>
            <th>شروع</th>
            <th>پایان</th>
            <th>وضعیت</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($ownerships as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['unit_code']) ?></td>
            <td><?= htmlspecialchars($o['resident_name']) ?></td>
            <td><?= $o['start_date'] ?></td>
            <td><?= $o['end_date'] ?: '-' ?></td>
            <td><?= $o['is_primary'] ? 'اصلی' : 'فرعی' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php $content = ob_get_clean(); include 'layout.php'; ?>