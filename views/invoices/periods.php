<?php
// views/invoices/periods.php
declare(strict_types=1);
require_once __DIR__ . '/../_helpers.php';

/**
 * متغیرهای ورودی از کنترلر:
 * $byYear = [year => [ ['period'=>YYYY-MM, 'invoice_count'=>N, 'total_amount'=>X], ... ], ...]
 * $units, $qsBase, $page, $perPage, $lastPage, $total
 */
?>
<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h4 m-0">دوره‌های شارژ</h2>
        <div class="text-muted small">نمایش دوره‌ها به‌صورت خلاصه (هر ردیف یک ماه/دوره)</div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2" method="get">
                <input type="hidden" name="page" value="invoices">
                <input type="hidden" name="action" value="periods">

                <div class="col-sm-3">
                    <label class="form-label">واحد</label>
                    <select class="form-select" name="unit_id">
                        <option value="">همه</option>
                        <?php foreach ($units as $u):
              $sel = (isset($_GET['unit_id']) && (string)$_GET['unit_id']===(string)$u['id']) ? 'selected' : ''; ?>
                        <option value="<?=h($u['id'])?>" <?=$sel?>><?=h($u['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-5">
                    <label class="form-label">جستجو در عنوان فاکتور</label>
                    <input class="form-control" type="text" name="q" value="<?=h($_GET['q'] ?? '')?>"
                        placeholder="مثلاً شارژ مرداد 1403">
                </div>

                <div class="col-sm-2">
                    <label class="form-label">تعداد در هر صفحه</label>
                    <select class="form-select" name="per_page">
                        <?php foreach ([12,24,36,60,120] as $pp): ?>
                        <option value="<?=$pp?>" <?=((int)($_GET['per_page'] ?? 24)===$pp)?'selected':''?>><?=$pp?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">اعمال فیلتر</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    $from = $total ? (($page-1)*$perPage + 1) : 0;
    $to   = min($total, $page*$perPage);
  ?>
    <div class="d-flex justify-content-between align-items-center mb-2 small">
        <div class="text-muted">نمایش <?=h(number_format($from))?> تا <?=h(number_format($to))?> از
            <?=h(number_format($total))?> دوره</div>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?page=invoices">مشاهدهٔ فاکتورها (لیست)</a>
        </div>
    </div>

    <?php if (!$byYear): ?>
    <div class="alert alert-info">دوره‌ای یافت نشد.</div>
    <?php else: ?>

    <?php foreach ($byYear as $year => $rows): ?>
    <div class="card mb-3">
        <div class="card-header fw-bold">
            سال <?=h($year)?>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm align-middle m-0">
                <thead>
                    <tr>
                        <th style="width:160px;">دوره</th>
                        <th>تعداد فاکتور</th>
                        <th>جمع مبالغ</th>
                        <th style="width:180px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                $p   = (string)($r['period'] ?? 'بدون-دوره');
                $cnt = (int)($r['invoice_count'] ?? 0);
                $sum = (float)($r['total_amount'] ?? 0);
                $link = "index.php?page=invoices&period=" . rawurlencode($p);
                // فیلترهای فعلی به لینک اضافه شوند (به‌جز action و p)
                $keep = $_GET;
                unset($keep['action'], $keep['p'], $keep['per_page']);
                $keep['page'] = 'invoices';
                $keep['period'] = $p;
                $link = "index.php?" . http_build_query($keep, '', '&', PHP_QUERY_RFC3986);
            ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?=h($p)?></span></td>
                        <td><?=h(number_format($cnt))?></td>
                        <td><?=h(number_format($sum))?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="<?=$link?>">مشاهدهٔ فاکتورها</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($lastPage > 1): ?>
    <nav aria-label="pagination" class="mt-2">
        <ul class="pagination justify-content-center flex-wrap">
            <?php
            $plink = function($label, $p, $disabled=false, $active=false) use ($qsBase) {
              $cls = 'page-item';
              if ($disabled) $cls .= ' disabled';
              if ($active)   $cls .= ' active';
              $href = $disabled ? '#' : "index.php?{$qsBase}&p={$p}";
              echo '<li class="'.$cls.'"><a class="page-link" href="'.$href.'">'.htmlspecialchars((string)$label,ENT_QUOTES,'UTF-8').'</a></li>';
            };
            $plink('قبلی', max(1,$page-1), $page<=1);
            $win = 2;
            $start = max(1, $page-$win);
            $end   = min($lastPage, $page+$win);
            if ($start > 1) {
              $plink(1, 1, false, $page===1);
              if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            for ($i=$start; $i<=$end; $i++) $plink($i, $i, false, $i===$page);
            if ($end < $lastPage) {
              if ($end < $lastPage-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              $plink($lastPage, $lastPage, false, $page===$lastPage);
            }
            $plink('بعدی', min($lastPage,$page+1), $page>=$lastPage);
          ?>
        </ul>
        <div class="text-center small text-muted">صفحه <?=h($page)?> از <?=h($lastPage)?></div>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</div>