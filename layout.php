<?php
if (!isLoggedIn()) { header('Location: login.php'); exit(); }
$isDark   = $_SESSION['dark'] ?? 0;
$notifCount = 0;
$msgCount   = 0;
try {
    $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $ns->execute([$_SESSION['user_id']]);
    $notifCount = (int)$ns->fetchColumn();
} catch(Exception $e){}
try {
    $ms = $pdo->prepare("SELECT COUNT(*) FROM chat_messages cm JOIN chat_conversations cc ON cc.id=cm.conversation_id WHERE cm.is_read=0 AND cm.sender_id!=? AND (cc.user1_id=? OR cc.user2_id=?)");
    $ms->execute([$_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id']]);
    $msgCount = (int)$ms->fetchColumn();
} catch(Exception $e){}

$role     = $_SESSION['role'];
$userName = $_SESSION['name'];

$navLinks = [];
switch($role) {
    case 'superadmin':
        $navLinks = [
            ['admin.php',            'fa-gauge',          'Dashboard',  'dashboard'],
            ['admin.php?tab=students','fa-users',          'Students',   'students'],
            ['admin.php?tab=teachers','fa-chalkboard',     'Teachers',   'teachers'],
            ['admin.php?tab=classes', 'fa-door-open',      'Classes',    'classes'],
            ['admin.php?tab=marks',   'fa-star',           'Marks',      'marks'],
            ['admin.php?tab=attend',  'fa-calendar-check', 'Attendance', 'attend'],
            ['admin.php?tab=calendar','fa-calendar',       'Calendar',   'calendar'],
            ['admin.php?tab=reports', 'fa-file-alt',       'Reports',    'reports'],
            ['admin.php?tab=announce','fa-bullhorn',       'Announce',   'announce'],
            ['chat.php',              'fa-comments',       'Messages',   'chat'],
            ['admin.php?tab=settings','fa-gear',           'Settings',   'settings'],
        ]; break;
    case 'teacher':
        $navLinks = [
            ['teacher.php',               'fa-gauge',          'Dashboard',  'dashboard'],
            ['teacher.php?tab=marks',     'fa-star',           'Marks',      'marks'],
            ['teacher.php?tab=attendance','fa-calendar-check', 'Attendance', 'attend'],
            ['teacher.php?tab=students',  'fa-users',          'Students',   'students'],
            ['teacher.php?tab=homeroom',  'fa-house',          'Homeroom',   'homeroom'],
            ['chat.php',                  'fa-comments',       'Messages',   'chat'],
            ['teacher.php?tab=calendar',  'fa-calendar',       'Calendar',   'calendar'],
        ]; break;
    case 'parent':
        $navLinks = [
            ['parent.php',              'fa-gauge',          'Overview',   'dashboard'],
            ['parent.php?tab=marks',    'fa-star',           'Results',    'marks'],
            ['parent.php?tab=attend',   'fa-calendar-check', 'Attendance', 'attend'],
            ['parent.php?tab=calendar', 'fa-calendar',       'Calendar',   'calendar'],
            ['chat.php',                'fa-comments',       'Messages',   'chat'],
        ]; break;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $isDark?'dark':'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?= sanitize($pageTitle??'EduTrack') ?> · EduTrack</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ══════════════════════════════════════════════════
   CSS VARIABLES
══════════════════════════════════════════════════ */
:root{
  --primary:#6366f1;--primary-dark:#4f46e5;--secondary:#8b5cf6;
  --accent:#06b6d4;--success:#10b981;--warning:#f59e0b;
  --danger:#ef4444;--orange:#f97316;
  --bg:#f1f5f9;--card:#fff;--text:#1e293b;--muted:#64748b;
  --border:#e2e8f0;--input-bg:#f8fafc;
  --sidebar-bg:#1e1b4b;--header-bg:#fff;
  --radius:12px;
  --shadow:0 1px 3px rgba(0,0,0,.08),0 4px 16px rgba(0,0,0,.06);
  --shadow-lg:0 8px 32px rgba(0,0,0,.12);
  --topbar-h:56px;
  --bottom-nav-h:60px;
}
[data-theme="dark"]{
  --bg:#0f172a;--card:#1e293b;--text:#f1f5f9;--muted:#94a3b8;
  --border:#334155;--input-bg:#0f172a;--header-bg:#1e293b;
}

/* ══════════════════════════════════════════════════
   RESET
══════════════════════════════════════════════════ */
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overflow-x:hidden;}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);font-size:14px;}
a{color:inherit;text-decoration:none;}
button,input,select,textarea{font-family:inherit;}

/* ══════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════ */
#topbar{
  position:fixed;top:0;left:0;right:0;z-index:300;
  height:var(--topbar-h);
  background:var(--header-bg);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 14px;gap:10px;
  box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.tb-hamburger{
  width:36px;height:36px;border-radius:10px;border:none;
  background:transparent;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  color:var(--muted);font-size:1rem;flex-shrink:0;
}
.tb-logo{
  display:flex;align-items:center;gap:8px;flex:1;min-width:0;
}
.tb-logo img{width:28px;height:28px;border-radius:8px;object-fit:cover;}
.tb-logo .tb-name{font-weight:800;font-size:.9rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tb-actions{display:flex;align-items:center;gap:6px;flex-shrink:0;}
.tb-btn{
  width:34px;height:34px;border-radius:10px;border:1px solid var(--border);
  background:var(--card);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  color:var(--muted);font-size:.85rem;position:relative;
  transition:background .15s;
}
.tb-btn:hover{background:var(--bg);color:var(--primary);}
.tb-dot{
  position:absolute;top:5px;right:5px;width:7px;height:7px;
  background:var(--danger);border-radius:50%;border:2px solid var(--card);
}

/* ══════════════════════════════════════════════════
   SIDEBAR (desktop)
══════════════════════════════════════════════════ */
#sidebar{
  position:fixed;top:var(--topbar-h);left:0;bottom:0;
  width:220px;z-index:200;
  background:var(--sidebar-bg);
  display:flex;flex-direction:column;
  transition:transform .28s cubic-bezier(.4,0,.2,1);
  overflow:hidden;
}
.sb-user{
  padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.1);
  display:flex;align-items:center;gap:10px;
}
.sb-av{
  width:36px;height:36px;border-radius:50%;
  background:var(--primary);display:flex;align-items:center;
  justify-content:center;font-weight:800;font-size:.9rem;color:#fff;flex-shrink:0;
}
.sb-uname{color:#fff;font-size:.82rem;font-weight:700;line-height:1.3;}
.sb-role{color:rgba(255,255,255,.5);font-size:.68rem;text-transform:capitalize;}
.sb-nav{flex:1;overflow-y:auto;padding:8px 0;}
.sb-nav::-webkit-scrollbar{width:3px;}
.sb-nav::-webkit-scrollbar-track{background:transparent;}
.sb-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:2px;}
.nav-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 14px;margin:2px 8px;border-radius:10px;
  color:rgba(255,255,255,.7);font-size:.83rem;font-weight:500;
  cursor:pointer;transition:all .18s;position:relative;
}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;}
.nav-item i{width:16px;text-align:center;font-size:.82rem;opacity:.85;flex-shrink:0;}
.nav-item .nav-badge{
  margin-left:auto;background:var(--danger);color:#fff;
  font-size:.6rem;padding:2px 6px;border-radius:10px;font-weight:800;
}
.sb-footer{padding:10px 8px;border-top:1px solid rgba(255,255,255,.1);}
.sb-logout{
  display:flex;align-items:center;gap:10px;padding:9px 14px;
  border-radius:10px;color:rgba(255,255,255,.6);font-size:.83rem;
  cursor:pointer;transition:all .18s;
}
.sb-logout:hover{background:rgba(255,255,255,.08);color:#fff;}

/* ══════════════════════════════════════════════════
   MAIN CONTENT AREA
══════════════════════════════════════════════════ */
#main{
  margin-left:220px;
  padding-top:var(--topbar-h);
  min-height:100vh;
  transition:margin-left .28s;
}
.page-content{
  padding:16px;
  padding-bottom:calc(var(--bottom-nav-h) + 8px);
}

/* ══════════════════════════════════════════════════
   MOBILE BOTTOM NAV
══════════════════════════════════════════════════ */
#bottom-nav{
  display:none;
  position:fixed;bottom:0;left:0;right:0;
  height:var(--bottom-nav-h);z-index:300;
  background:var(--header-bg);
  border-top:1px solid var(--border);
  box-shadow:0 -2px 10px rgba(0,0,0,.08);
}
.bn-items{
  display:flex;height:100%;align-items:stretch;
  overflow-x:auto;scrollbar-width:none;
}
.bn-items::-webkit-scrollbar{display:none;}
.bn-item{
  flex:1;min-width:52px;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:3px;cursor:pointer;position:relative;
  color:var(--muted);transition:color .18s;padding:0 4px;
}
.bn-item.active{color:var(--primary);}
.bn-item i{font-size:1.1rem;}
.bn-item span{font-size:.6rem;font-weight:600;white-space:nowrap;}
.bn-badge{
  position:absolute;top:6px;right:calc(50% - 14px);
  background:var(--danger);color:#fff;
  font-size:.55rem;font-weight:800;
  padding:1px 5px;border-radius:10px;min-width:15px;text-align:center;
}

/* ══════════════════════════════════════════════════
   OVERLAY (mobile sidebar)
══════════════════════════════════════════════════ */
#sb-overlay{
  display:none;position:fixed;inset:0;z-index:190;
  background:rgba(0,0,0,.4);backdrop-filter:blur(2px);
}

/* ══════════════════════════════════════════════════
   SHARED COMPONENTS
══════════════════════════════════════════════════ */

/* Cards */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);}
.card-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.card-header h3{font-size:.9rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;}
.card-body{padding:16px;}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:14px 16px;box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;border-left:4px solid var(--primary);}
.stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.stat-num{font-size:1.6rem;font-weight:800;color:var(--text);line-height:1;}
.stat-label{color:var(--muted);font-size:.73rem;margin-top:2px;}

/* Tables */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:var(--radius);}
table{width:100%;border-collapse:collapse;min-width:400px;}
th{background:var(--bg);color:var(--muted);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:2px solid var(--border);white-space:nowrap;}
td{padding:10px 14px;border-bottom:1px solid var(--border);font-size:.84rem;color:var(--text);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(99,102,241,.03);}

/* Badges */
.badge{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:20px;font-size:.7rem;font-weight:700;}
.badge-success{background:#dcfce7;color:#16a34a;}
.badge-danger{background:#fee2e2;color:#dc2626;}
.badge-warning{background:#fef9c3;color:#ca8a04;}
.badge-info{background:#e0f2fe;color:#0369a1;}
.badge-primary{background:#ede9fe;color:#7c3aed;}
.badge-gray{background:var(--bg);color:var(--muted);}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;white-space:nowrap;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg);color:var(--muted);border:1px solid var(--border);}
.btn-secondary:hover{background:var(--border);}
.btn-danger{background:var(--danger);color:#fff;}
.btn-success{background:var(--success);color:#fff;}
.btn-warning{background:var(--warning);color:#fff;}
.btn-sm{padding:5px 10px;font-size:.75rem;}

/* Forms */
.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:.78rem;font-weight:600;color:var(--text);margin-bottom:5px;}
.form-control{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;color:var(--text);background:var(--input-bg);transition:border-color .2s;outline:none;}
.form-control:focus{border-color:var(--primary);}
select.form-control{cursor:pointer;}

/* Tabs */
.tabs{display:flex;gap:3px;padding:4px;background:var(--bg);border-radius:10px;margin-bottom:16px;overflow-x:auto;scrollbar-width:none;flex-wrap:nowrap;}
.tabs::-webkit-scrollbar{display:none;}
.tab-btn{padding:7px 13px;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--muted);transition:all .18s;white-space:nowrap;flex-shrink:0;}
.tab-btn.active{background:var(--card);color:var(--primary);box-shadow:var(--shadow);}
.tab-pane{display:none;}
.tab-pane.active{display:block;animation:fadeIn .25s;}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}

/* Alerts */
.alert{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:.84rem;display:flex;align-items:center;gap:8px;}
.alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;}
.alert-danger{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;}
.alert-warning{background:#fef9c3;color:#92400e;border:1px solid #fde68a;}
.alert-info{background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;}

/* Progress */
.progress{background:var(--bg);border-radius:100px;height:7px;overflow:hidden;}
.progress-bar{height:100%;border-radius:100px;transition:width .4s;}

/* Modals */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-end;justify-content:center;padding:0;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border-radius:20px 20px 0 0;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 -8px 40px rgba(0,0,0,.15);animation:slideUp .25s ease;}
@keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
.modal-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--card);z-index:1;}
.modal-header h3{font-size:.95rem;font-weight:700;}
.modal-close{background:none;border:none;font-size:1.1rem;cursor:pointer;color:var(--muted);width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;}
.modal-close:hover{background:var(--bg);}
.modal-body{padding:16px 20px;}
.modal-footer{padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;}

/* Avatar */
.student-photo{width:34px;height:34px;border-radius:50%;object-fit:cover;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0;}

/* Grade colors */
.grade-Ap,.grade-A{color:#10b981;font-weight:700}
.grade-Bp,.grade-B{color:#3b82f6;font-weight:700}
.grade-C{color:#f59e0b;font-weight:700}
.grade-D{color:#f97316;font-weight:700}
.grade-F{color:#ef4444;font-weight:700}

/* Layout helpers */
.grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;}
.grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
.flex{display:flex;}.gap-2{gap:8px;}.gap-3{gap:12px;}.items-center{align-items:center;}
.justify-between{justify-content:space-between;}.flex-1{flex:1;}.flex-wrap{flex-wrap:wrap;}
.text-muted{color:var(--muted);}.text-sm{font-size:.76rem;}.font-bold{font-weight:700;}
.mt-2{margin-top:8px;}.mt-3{margin-top:12px;}.mt-4{margin-top:16px;}.mb-4{margin-bottom:16px;}
.text-center{text-align:center;}.p-4{padding:16px;}.rounded{border-radius:var(--radius);}
.w-full{width:100%;}.hidden{display:none!important;}

/* ══════════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
══════════════════════════════════════════════════ */
@media(max-width:768px){
  #sidebar{transform:translateX(-100%);}
  #sidebar.open{transform:translateX(0);}
  #sb-overlay.open{display:block;}
  #main{margin-left:0;}
  #bottom-nav{display:block;}
  .page-content{padding:12px 12px calc(var(--bottom-nav-h) + 12px);}
  .stats-row{grid-template-columns:1fr 1fr;}
  .modal-overlay{align-items:flex-end;}
  .grid-2,.grid-3{grid-template-columns:1fr;}
}
@media(max-width:480px){
  .stats-row{grid-template-columns:1fr 1fr;}
  .tabs{gap:2px;}
  .tab-btn{padding:6px 10px;font-size:.72rem;}
  th,td{padding:8px 10px;font-size:.78rem;}
  .btn{padding:7px 11px;font-size:.78rem;}
  .card-header{padding:12px 14px;}
  .card-body{padding:12px;}
}
@media(min-width:769px){
  #bottom-nav{display:none;}
}

/* Print */
@media print{
  #sidebar,#topbar,#bottom-nav,.no-print{display:none!important;}
  #main{margin:0;padding:0;}
  .page-content{padding:0;}
  body{background:#fff;}
}
</style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<div id="topbar">
  <button class="tb-hamburger" onclick="toggleSidebar()" aria-label="Menu">
    <i class="fa fa-bars"></i>
  </button>
  <div class="tb-logo">
    <img src="images/logo.png" onerror="this.style.display='none'" alt="Logo">
    <span class="tb-name">EduTrack</span>
  </div>
  <div class="tb-actions">
    <button class="tb-btn" onclick="toggleDark()" title="Dark mode"><i class="fa fa-moon" id="dark-ic"></i></button>
    <a href="notifications.php" class="tb-btn">
      <i class="fa fa-bell"></i>
      <?php if($notifCount>0): ?><span class="tb-dot"></span><?php endif; ?>
    </a>
  </div>
</div>

<!-- ══ SIDEBAR OVERLAY ══ -->
<div id="sb-overlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<nav id="sidebar">
  <div class="sb-user">
    <div class="sb-av"><?= strtoupper(substr($userName,0,1)) ?></div>
    <div>
      <div class="sb-uname"><?= sanitize(mb_substr($userName,0,18)) ?></div>
      <div class="sb-role"><?= $role==='superadmin'?'Admin':ucfirst($role) ?></div>
    </div>
  </div>
  <div class="sb-nav">
    <?php foreach($navLinks as [$url,$icon,$label,$key]): ?>
    <a href="<?= $url ?>" class="nav-item <?= ($activeMenu??'')===$key?'active':'' ?>">
      <i class="fa <?= $icon ?>"></i>
      <span><?= $label ?></span>
      <?php if($key==='chat' && $msgCount>0): ?>
      <span class="nav-badge"><?= $msgCount ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="sb-footer">
    <a href="logout.php" class="sb-logout">
      <i class="fa fa-right-from-bracket"></i><span>Sign Out</span>
    </a>
  </div>
</nav>

<!-- ══ MAIN ══ -->
<div id="main">
<div class="page-content">

<!-- ══ BOTTOM NAV (mobile) ══ -->
<div id="bottom-nav">
  <div class="bn-items">
    <?php
    // Show max 5 key items for bottom nav
    $bnLinks = array_slice($navLinks, 0, 5);
    foreach($bnLinks as [$url,$icon,$label,$key]):
      $isAct = ($activeMenu??'')===$key;
    ?>
    <a href="<?= $url ?>" class="bn-item <?= $isAct?'active':'' ?>">
      <i class="fa <?= $icon ?>"></i>
      <span><?= $label ?></span>
      <?php if($key==='chat' && $msgCount>0): ?>
      <span class="bn-badge"><?= $msgCount ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <!-- More button -->
    <?php if(count($navLinks)>5): ?>
    <div class="bn-item" onclick="toggleSidebar()">
      <i class="fa fa-ellipsis"></i>
      <span>More</span>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleSidebar(){
  const sb=document.getElementById('sidebar');
  const ov=document.getElementById('sb-overlay');
  const isOpen=sb.classList.contains('open');
  if(isOpen){sb.classList.remove('open');ov.classList.remove('open');}
  else{sb.classList.add('open');ov.classList.add('open');}
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sb-overlay').classList.remove('open');
}
function toggleDark(){
  const html=document.documentElement;
  const dark=html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme',dark?'light':'dark');
  document.getElementById('dark-ic').className=dark?'fa fa-moon':'fa fa-sun';
  fetch('api.php?action=toggle_dark&mode='+(dark?0:1));
}
if(document.documentElement.getAttribute('data-theme')==='dark')
  document.getElementById('dark-ic').className='fa fa-sun';

// Modal helpers (global)
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.addEventListener('click',e=>{
  if(e.target.classList.contains('modal-overlay'))
    e.target.classList.remove('open');
});

// Tab helper (used by admin/teacher)
function switchTab(key){
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+key)?.classList.add('active');
  if(event?.target){
    event.target.closest?.('.tab-btn')?.classList.add('active');
  }
  const url=new URL(location.href);
  url.searchParams.set('tab',key);
  history.replaceState(null,'',url.toString());
}
</script>
