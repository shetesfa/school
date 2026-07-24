<?php
// Shared calendar view - included in teacher and parent dashboards
$events = $pdo->query("SELECT * FROM calendar_events ORDER BY event_date ASC")->fetchAll();
$evByDate = [];
foreach($events as $ev) {
    $evByDate[$ev['event_date']][] = $ev;
    if($ev['end_date'] && $ev['end_date'] !== $ev['event_date']) {
        $d = new DateTime($ev['event_date']);
        $end = new DateTime($ev['end_date']);
        while($d <= $end) {
            $evByDate[$d->format('Y-m-d')][] = $ev;
            $d->modify('+1 day');
        }
    }
}
$viewMonth = isset($_GET['cal_month']) ? sanitize($_GET['cal_month']) : date('Y-m');
[$vy,$vm] = explode('-', $viewMonth);
$firstDay = mktime(0,0,0,$vm,1,$vy);
$daysInMonth = date('t',$firstDay);
$startDow = (int)date('N',$firstDay); // 1=Mon
?>
<div class="card">
  <div class="card-header" style="justify-content:center;gap:24px">
    <a href="?<?= http_build_query(array_merge($_GET,['cal_month'=>date('Y-m',mktime(0,0,0,$vm-1,1,$vy))])) ?>"
       class="btn btn-secondary btn-sm"><i class="fa fa-chevron-left"></i></a>
    <h3 style="margin:0"><?= date('F Y',$firstDay) ?></h3>
    <a href="?<?= http_build_query(array_merge($_GET,['cal_month'=>date('Y-m',mktime(0,0,0,$vm+1,1,$vy))])) ?>"
       class="btn btn-secondary btn-sm"><i class="fa fa-chevron-right"></i></a>
  </div>
  <div class="card-body">
    <!-- Day headers -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:8px">
      <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
      <div style="text-align:center;font-size:.75rem;font-weight:700;color:var(--muted);padding:6px"><?= $d ?></div>
      <?php endforeach; ?>
    </div>
    <!-- Days grid -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
      <?php
      // Empty cells before first day
      for($i=1;$i<$startDow;$i++):
      ?><div style="min-height:70px"></div><?php endfor;

      for($day=1;$day<=$daysInMonth;$day++):
        $dateStr = "$vy-".str_pad($vm,2,'0',STR_PAD_LEFT)."-".str_pad($day,2,'0',STR_PAD_LEFT);
        $isToday = $dateStr === date('Y-m-d');
        $dayEvs = $evByDate[$dateStr] ?? [];
      ?>
      <div style="min-height:70px;border:1px solid var(--border);border-radius:8px;padding:4px;
                  background:<?= $isToday?'var(--primary)':'var(--card)' ?>;
                  color:<?= $isToday?'#fff':'var(--text)' ?>">
        <div style="font-size:.82rem;font-weight:<?= $isToday?'800':'600' ?>;margin-bottom:3px;padding:2px 4px"><?= $day ?></div>
        <?php foreach(array_slice($dayEvs,0,3) as $ev): ?>
        <div style="font-size:.68rem;padding:2px 5px;border-radius:4px;margin-bottom:2px;
                    background:<?= $isToday?'rgba(255,255,255,.2)':$ev['color'].'22' ?>;
                    color:<?= $isToday?'#fff':$ev['color'] ?>;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
             title="<?= sanitize($ev['title']) ?>">
          <?= sanitize($ev['title']) ?>
        </div>
        <?php endforeach; ?>
        <?php if(count($dayEvs)>3): ?>
        <div style="font-size:.65rem;color:<?= $isToday?'rgba(255,255,255,.7)':'var(--muted)' ?>">+<?= count($dayEvs)-3 ?> more</div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- Upcoming Events -->
<div class="card mt-4">
  <div class="card-header"><h3><i class="fa fa-list"></i> Upcoming Events</h3></div>
  <div class="card-body">
    <?php
    $upEvents = $pdo->query("SELECT * FROM calendar_events WHERE event_date >= CURDATE() ORDER BY event_date LIMIT 10")->fetchAll();
    if(empty($upEvents)): ?><div class="text-center text-muted">No upcoming events</div><?php
    else: foreach($upEvents as $ev): ?>
    <div style="display:flex;gap:16px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="width:48px;height:48px;border-radius:10px;background:<?= $ev['color'] ?>22;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0">
        <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:<?= $ev['color'] ?>"><?= date('M',strtotime($ev['event_date'])) ?></div>
        <div style="font-size:1.1rem;font-weight:800;color:<?= $ev['color'] ?>;line-height:1"><?= date('d',strtotime($ev['event_date'])) ?></div>
      </div>
      <div>
        <div style="font-weight:700"><?= sanitize($ev['title']) ?></div>
        <div class="text-sm text-muted"><?= sanitize($ev['description']??'') ?></div>
        <?php if($ev['end_date'] && $ev['end_date'] !== $ev['event_date']): ?>
        <div class="text-sm text-muted">Until: <?= date('d M Y',strtotime($ev['end_date'])) ?></div>
        <?php endif; ?>
      </div>
      <div style="margin-left:auto">
        <span class="badge" style="background:<?= $ev['color'] ?>22;color:<?= $ev['color'] ?>"><?= ucfirst($ev['type']) ?></span>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
