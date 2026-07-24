<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Notifications';
$activeMenu = 'notifications';

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$_SESSION['user_id']]);
$nrows = $notifs->fetchAll();

include 'layout.php';
?>
<div class="card">
  <div class="card-header"><h3><i class="fa fa-bell"></i> Notifications</h3></div>
  <div class="card-body">
    <?php if(empty($nrows)): ?>
    <div class="text-center text-muted" style="padding:40px">
      <i class="fa fa-bell-slash" style="font-size:2.5rem;margin-bottom:12px;display:block"></i>
      No notifications yet
    </div>
    <?php else: foreach($nrows as $n): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start">
      <i class="fa fa-bell" style="color:var(--primary);margin-top:3px"></i>
      <div>
        <div><?= sanitize($n['message']) ?></div>
        <div class="text-sm text-muted"><?= date('d M Y H:i',strtotime($n['created_at'])) ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php include 'layout_end.php'; ?>
