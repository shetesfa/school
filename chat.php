<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Messages';
$activeMenu = 'chat';
$myId   = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'];

// ── AJAX handler (must be first) ────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'send_message') {
        $convId = (int)($_POST['conv_id'] ?? 0);
        $msg    = trim($_POST['message'] ?? '');
        $attach = null; $attachType = null;

        $ck = $pdo->prepare("SELECT id FROM chat_conversations WHERE id=? AND (user1_id=? OR user2_id=?)");
        $ck->execute([$convId, $myId, $myId]);
        if (!$ck->fetch()) jsonResponse(['error'=>'Forbidden'], 403);

        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png','gif','webp','pdf']) && $_FILES['attachment']['size'] <= 5*1024*1024) {
                $dir = __DIR__ . '/uploads/chat/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn = uniqid('c_',true) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir.$fn)) {
                    $attach = 'uploads/chat/'.$fn;
                    $attachType = in_array($ext,['jpg','jpeg','png','gif','webp']) ? 'image' : 'pdf';
                }
            }
        }
        if (!$msg && !$attach) jsonResponse(['error'=>'Empty'], 400);

        $st = $pdo->prepare("INSERT INTO chat_messages (conversation_id,sender_id,message,attachment,attachment_type) VALUES (?,?,?,?,?)");
        if ($st->execute([$convId, $myId, $msg?:null, $attach, $attachType])) {
            $pdo->prepare("UPDATE chat_conversations SET last_message_at=NOW() WHERE id=?")->execute([$convId]);
            jsonResponse(['success'=>true, 'id'=>(int)$pdo->lastInsertId()]);
        }
        jsonResponse(['error'=>'DB error'], 500);
    }

    if ($action === 'get_messages') {
        $convId = (int)($_POST['conv_id'] ?? 0);
        $lastId = (int)($_POST['last_id'] ?? 0);
        $ck = $pdo->prepare("SELECT id FROM chat_conversations WHERE id=? AND (user1_id=? OR user2_id=?)");
        $ck->execute([$convId, $myId, $myId]);
        if (!$ck->fetch()) jsonResponse(['error'=>'Forbidden'], 403);
        $mq = $pdo->prepare("SELECT cm.id,cm.sender_id,cm.message,cm.attachment,cm.attachment_type,cm.is_read,cm.created_at,u.name AS sender_name FROM chat_messages cm JOIN users u ON u.id=cm.sender_id WHERE cm.conversation_id=? AND cm.id>? ORDER BY cm.created_at ASC");
        $mq->execute([$convId, $lastId]);
        $pdo->prepare("UPDATE chat_messages SET is_read=1,read_at=NOW() WHERE conversation_id=? AND sender_id!=? AND is_read=0")->execute([$convId,$myId]);
        jsonResponse(['messages'=>$mq->fetchAll()]);
    }
    jsonResponse(['error'=>'Unknown action'], 400);
}

// ── Load conversations (positional params – no named param reuse bug) ──
try {
    $sq = $pdo->prepare("
        SELECT cc.*,
          IF(cc.user1_id=?,u2.id,u1.id)     AS other_id,
          IF(cc.user1_id=?,u2.name,u1.name) AS other_name,
          IF(cc.user1_id=?,u2.role,u1.role) AS other_role,
          (SELECT message    FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
          (SELECT created_at FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_msg_time,
          (SELECT COUNT(*)   FROM chat_messages WHERE conversation_id=cc.id AND sender_id!=? AND is_read=0)   AS unread
        FROM chat_conversations cc
        JOIN users u1 ON u1.id=cc.user1_id
        JOIN users u2 ON u2.id=cc.user2_id
        WHERE cc.user1_id=? OR cc.user2_id=?
        ORDER BY last_msg_time DESC
    ");
    $sq->execute([$myId,$myId,$myId,$myId,$myId,$myId]);
    $conversations = $sq->fetchAll();
} catch(Exception $e){ $conversations=[]; }

// ── Active conversation / new chat ───────────────────────────────
$activeConvId = (int)($_GET['conv'] ?? ($conversations[0]['id'] ?? 0));

$withUserId = (int)($_GET['with'] ?? 0);
if ($withUserId && $withUserId !== $myId) {
    try {
        $u1=min($myId,$withUserId); $u2=max($myId,$withUserId);
        $ex=$pdo->prepare("SELECT id FROM chat_conversations WHERE user1_id=? AND user2_id=?");
        $ex->execute([$u1,$u2]);
        $existing=$ex->fetch();
        if ($existing) { $activeConvId=(int)$existing['id']; }
        else {
            $pdo->prepare("INSERT INTO chat_conversations (user1_id,user2_id,last_message_at) VALUES (?,?,NOW())")->execute([$u1,$u2]);
            $activeConvId=(int)$pdo->lastInsertId();
        }
        header("Location: chat.php?conv=$activeConvId"); exit();
    } catch(Exception $e){}
}

if ($activeConvId) {
    try { $pdo->prepare("UPDATE chat_messages SET is_read=1,read_at=NOW() WHERE conversation_id=? AND sender_id!=? AND is_read=0")->execute([$activeConvId,$myId]); } catch(Exception $e){}
}

// ── Load messages ────────────────────────────────────────────────
$messages=[]; $otherUser=null;
if ($activeConvId) {
    try {
        $mq=$pdo->prepare("SELECT cm.*,u.name AS sender_name,u.role AS sender_role FROM chat_messages cm JOIN users u ON u.id=cm.sender_id WHERE cm.conversation_id=? ORDER BY cm.created_at ASC");
        $mq->execute([$activeConvId]);
        $messages=$mq->fetchAll();
        $cv=$pdo->prepare("SELECT * FROM chat_conversations WHERE id=?");
        $cv->execute([$activeConvId]);
        $cvRow=$cv->fetch();
        if($cvRow){
            $oid=((int)$cvRow['user1_id']===$myId)?(int)$cvRow['user2_id']:(int)$cvRow['user1_id'];
            $ou=$pdo->prepare("SELECT id,name,role,email FROM users WHERE id=?");
            $ou->execute([$oid]); $otherUser=$ou->fetch();
        }
    } catch(Exception $e){ $messages=[]; }
}

// ── Available contacts ───────────────────────────────────────────
$availableUsers=[];
try {
    if ($myRole==='superadmin') {
        $q=$pdo->prepare("SELECT id,name,role FROM users WHERE id!=? AND status='active' ORDER BY role,name");
        $q->execute([$myId]); $availableUsers=$q->fetchAll();
    } elseif ($myRole==='teacher') {
        $q1=$pdo->prepare("SELECT id,name,role FROM users WHERE role='superadmin' AND status='active' ORDER BY name");
        $q1->execute();
        $q2=$pdo->prepare("SELECT id,name,role FROM users WHERE role='parent' AND status='active' ORDER BY name");
        $q2->execute();
        $availableUsers=array_merge($q1->fetchAll(),$q2->fetchAll());
    } elseif ($myRole==='parent') {
        $q1=$pdo->prepare("SELECT id,name,role FROM users WHERE role='superadmin' AND status='active' ORDER BY name");
        $q1->execute();
        $q2=$pdo->prepare("SELECT id,name,role FROM users WHERE role='teacher' AND status='active' ORDER BY name");
        $q2->execute();
        $availableUsers=array_merge($q1->fetchAll(),$q2->fetchAll());
    }
} catch(Exception $e){ $availableUsers=[]; }

$lastMsgId = !empty($messages) ? (int)end($messages)['id'] : 0;
$roleColors = ['superadmin'=>'#f59e0b','teacher'=>'#8b5cf6','parent'=>'#10b981'];

include 'layout.php';
?>
<style>
/* ═══════════════════════════════════════════
   CHAT — MOBILE FIRST
═══════════════════════════════════════════ */

/* Override page-content padding for chat */
.page-content { padding: 0 !important; }

/* Root wrap — full height */
.chat-root {
    display: flex;
    height: calc(100vh - 64px); /* 64px = topbar */
    overflow: hidden;
    background: var(--bg);
}

/* ── Sidebar list ── */
.chat-sb {
    width: 100%;          /* mobile: full width */
    max-width: 100%;
    display: flex;
    flex-direction: column;
    background: var(--card);
    border-right: 1px solid var(--border);
    flex-shrink: 0;
    transition: transform .25s ease;
}
.chat-sb-header {
    padding: 12px 14px 10px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.chat-sb-title {
    font-size: 1.05rem; font-weight: 800;
    color: var(--text); margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between;
}
.search-box {
    width: 100%; padding: 8px 14px;
    border: 1.5px solid var(--border); border-radius: 20px;
    font-size: .84rem; background: var(--bg); color: var(--text);
    outline: none; transition: border-color .2s;
}
.search-box:focus { border-color: var(--primary); }
.new-chat-btn {
    display: flex; align-items: center; gap: 7px;
    padding: 9px 16px; margin: 8px 14px;
    background: var(--primary); color: #fff;
    border: none; border-radius: 22px;
    font-size: .85rem; font-weight: 700;
    cursor: pointer; width: calc(100% - 28px);
    justify-content: center; transition: opacity .15s;
}
.new-chat-btn:hover { opacity: .88; }

.conv-list { flex: 1; overflow-y: auto; }
.conv-item {
    display: flex; align-items: center; gap: 11px;
    padding: 12px 14px; cursor: pointer;
    border-bottom: 1px solid var(--border);
    transition: background .15s; position: relative;
}
.conv-item:hover  { background: var(--bg); }
.conv-item.active { background: #ede9fe; }
[data-theme="dark"] .conv-item.active { background: rgba(99,102,241,.18); }

.cav {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 1rem; flex-shrink: 0;
}
.conv-body { flex: 1; min-width: 0; }
.conv-name {
    font-weight: 700; font-size: .88rem; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.conv-preview {
    font-size: .75rem; color: var(--muted); margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.conv-right { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
.conv-time { font-size: .68rem; color: var(--muted); white-space: nowrap; }
.unread-badge {
    background: var(--primary); color: #fff;
    font-size: .65rem; font-weight: 800;
    padding: 2px 7px; border-radius: 20px; min-width: 20px; text-align: center;
}

/* ── Chat main pane ── */
.chat-main {
    display: none;        /* mobile: hidden until conv selected */
    flex-direction: column;
    flex: 1;
    min-width: 0;
    height: 100%;
    background: var(--bg);
}
.chat-main.open { display: flex; }

/* Chat top bar */
.chat-topbar {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; flex-shrink: 0;
    background: var(--card); border-bottom: 1px solid var(--border);
}
.back-btn {
    background: none; border: none; cursor: pointer;
    color: var(--primary); font-size: 1.1rem; padding: 4px 6px;
    display: flex; align-items: center;
}
.chat-peer-name { font-weight: 700; font-size: .95rem; color: var(--text); }
.chat-peer-role { font-size: .7rem; color: var(--muted); margin-top: 1px; }
.role-badge {
    display: inline-block; padding: 1px 7px; border-radius: 6px;
    font-size: .65rem; font-weight: 700;
}
.rb-superadmin{background:#fef9c3;color:#92400e;}
.rb-teacher{background:#ede9fe;color:#7c3aed;}
.rb-parent{background:#dcfce7;color:#15803d;}

/* Messages scroll area */
.chat-msgs {
    flex: 1; overflow-y: auto;
    padding: 12px 14px;
    display: flex; flex-direction: column; gap: 8px;
}

/* Date separator */
.date-sep { text-align: center; margin: 6px 0; }
.date-sep span {
    background: var(--border); color: var(--muted);
    font-size: .68rem; padding: 3px 12px; border-radius: 20px;
}

/* Message row */
.msg-row { display: flex; align-items: flex-end; gap: 6px; }
.msg-row.mine { flex-direction: row-reverse; }

.msg-av {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: .68rem;
    flex-shrink: 0; align-self: flex-end;
}

.msg-col { display: flex; flex-direction: column; max-width: 72%; }
.msg-row.mine .msg-col { align-items: flex-end; }

.sender-name {
    font-size: .68rem; color: var(--muted);
    margin-bottom: 2px; padding-left: 2px;
}

/* THE FIX: bubble is a block, time is INSIDE at bottom-right */
.bubble {
    padding: 8px 12px 6px;
    border-radius: 16px;
    font-size: .87rem;
    line-height: 1.5;
    word-break: break-word;
    word-wrap: break-word;
    white-space: pre-wrap;
    display: inline-block;
    max-width: 100%;
}
.bubble.theirs {
    background: var(--card);
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,.07);
    color: var(--text);
}
.bubble.mine {
    background: var(--primary);
    color: #fff;
    border-bottom-right-radius: 4px;
}

/* Time sits on same line at end of last text line — Telegram style */
.bubble-inner {
    display: inline;
}
.bubble-time {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: .63rem;
    opacity: .7;
    margin-left: 8px;
    white-space: nowrap;
    vertical-align: bottom;
    float: right;         /* floats time to right within bubble */
    margin-top: 2px;
    padding-left: 6px;
}
/* Clear float so bubble height wraps content */
.bubble::after { content:''; display:table; clear:both; }

/* Attachments */
.att-img {
    max-width: 200px; max-height: 200px;
    border-radius: 10px; display: block;
    margin-bottom: 4px; cursor: pointer;
    object-fit: cover;
}
.att-file {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: .78rem; padding: 6px 10px;
    background: rgba(0,0,0,.1); border-radius: 8px;
    text-decoration: none; color: inherit; margin-bottom: 4px;
}

/* Empty state */
.no-msgs {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: var(--muted); gap: 8px; padding: 24px;
    text-align: center;
}

/* Input bar */
.chat-input-bar {
    flex-shrink: 0;
    background: var(--card);
    border-top: 1px solid var(--border);
    padding: 8px 12px;
}
.attach-preview-bar {
    display: none; align-items: center; gap: 8px;
    font-size: .75rem; color: var(--muted);
    padding: 4px 2px 6px;
}
.attach-preview-bar.show { display: flex; }
.input-row {
    display: flex; align-items: flex-end; gap: 8px;
}
.attach-label {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg); border: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); cursor: pointer; flex-shrink: 0;
    font-size: .9rem; transition: all .2s;
}
.attach-label:hover { border-color: var(--primary); color: var(--primary); }
.chat-textarea {
    flex: 1; padding: 9px 14px;
    border: 1.5px solid var(--border); border-radius: 20px;
    font-size: .87rem; resize: none; max-height: 100px;
    overflow-y: auto; background: var(--input-bg);
    color: var(--text); font-family: inherit; line-height: 1.4;
    outline: none; transition: border-color .2s;
}
.chat-textarea:focus { border-color: var(--primary); }
.send-btn {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--primary); border: none; color: #fff;
    cursor: pointer; display: flex; align-items: center;
    justify-content: center; font-size: 1rem; flex-shrink: 0;
    transition: background .2s;
}
.send-btn:hover { background: var(--primary-dark); }

/* ── People selector dropdown ── */
.dd-wrap { position: relative; }
.user-dd {
    display: none; position: absolute;
    top: calc(100% + 4px); left: 0; right: 0;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.14);
    z-index: 500; max-height: 260px; overflow-y: auto;
}
.user-dd.open { display: block; }
.dd-search-wrap { padding: 8px 10px; border-bottom: 1px solid var(--border); }
.user-dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; cursor: pointer; font-size: .84rem;
    transition: background .15s;
}
.user-dd-item:hover { background: var(--bg); }
.di-name { font-weight: 700; color: var(--text); font-size: .85rem; }
.di-role { font-size: .7rem; color: var(--muted); }

/* ── No active conv placeholder ── */
.no-conv-placeholder {
    display: none;
    flex: 1; flex-direction: column;
    align-items: center; justify-content: center;
    color: var(--muted); gap: 10px;
}

/* ═══ DESKTOP overrides (≥ 700px) ══════════════════ */
@media(min-width: 700px) {
    .chat-root { border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.1); margin: 16px; height: calc(100vh - 96px); }
    .chat-sb { width: 300px; max-width: 300px; }
    .chat-main { display: flex !important; } /* always visible on desktop */
    .back-btn { display: none; }
    .no-conv-placeholder { display: flex; }
    .page-content { padding: 0 !important; }
}
@media(max-width: 699px) {
    .chat-root { height: calc(100vh - 64px); border-radius: 0; margin: 0; }
    /* On mobile: sb takes full width, chat-main covers full width */
    .chat-sb { position: absolute; top: 64px; left: 0; right: 0; bottom: 0; z-index: 10; width: 100%; }
    .chat-sb.hidden { display: none; }
    .chat-main { position: absolute; top: 64px; left: 0; right: 0; bottom: 0; z-index: 20; width: 100%; }
    .chat-main.open { display: flex; }
    .page-content { padding: 0 !important; position: relative; }
    #main { position: relative; }
}
</style>

<div class="chat-root">

  <!-- ════ SIDEBAR ════ -->
  <div class="chat-sb" id="chat-sb">
    <div class="chat-sb-header">
      <div class="chat-sb-title">
        <span>Messages</span>
        <span class="badge badge-primary" style="font-size:.7rem"><?= count($conversations) ?></span>
      </div>
      <input type="text" class="search-box" placeholder="🔍 Search conversations…" oninput="filterConvs(this)">
    </div>

    <!-- New chat button + dropdown -->
    <div class="dd-wrap" style="padding:8px 14px 0">
      <button class="new-chat-btn" onclick="toggleDD(event)">
        <i class="fa fa-plus"></i> New Conversation
      </button>
      <div class="user-dd" id="user-dd">
        <div class="dd-search-wrap">
          <input type="text" class="search-box" id="dd-search" placeholder="Search people…" oninput="filterUsers(this)">
        </div>
        <?php foreach($availableUsers as $u):
          $col = $roleColors[$u['role']] ?? '#6366f1';
        ?>
        <div class="user-dd-item" onclick="startChat(<?= (int)$u['id'] ?>)"
             data-search="<?= strtolower(sanitize($u['name'])) ?>">
          <div class="cav" style="width:34px;height:34px;font-size:.78rem;background:<?= $col ?>">
            <?= strtoupper(substr($u['name'],0,1)) ?>
          </div>
          <div>
            <div class="di-name"><?= sanitize($u['name']) ?></div>
            <div class="di-role"><?= ucfirst($u['role']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($availableUsers)): ?>
        <div style="padding:16px;text-align:center;color:var(--muted);font-size:.8rem">No contacts</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Conversation list -->
    <div class="conv-list" id="conv-list">
      <?php if(empty($conversations)): ?>
      <div style="padding:32px 16px;text-align:center;color:var(--muted);font-size:.82rem">
        <i class="fa fa-comments" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.4"></i>
        No conversations yet.<br>Tap <strong>New Conversation</strong> to start.
      </div>
      <?php endif; ?>

      <?php foreach($conversations as $conv):
        $col      = $roleColors[$conv['other_role']] ?? '#6366f1';
        $isActive = ((int)$conv['id'] === $activeConvId);
        $unread   = (int)($conv['unread'] ?? 0);
        $lmsg     = $conv['last_msg'] ? sanitize(mb_substr($conv['last_msg'],0,38)) : 'Say hello!';
        $ltime    = $conv['last_msg_time'] ? date('H:i',strtotime($conv['last_msg_time'])) : '';
      ?>
      <div class="conv-item <?= $isActive?'active':'' ?>"
           onclick="openConv(<?= (int)$conv['id'] ?>)"
           data-name="<?= strtolower(sanitize($conv['other_name'])) ?>">
        <div class="cav" style="background:<?= $col ?>">
          <?= strtoupper(substr($conv['other_name'],0,1)) ?>
        </div>
        <div class="conv-body">
          <div class="conv-name"><?= sanitize($conv['other_name']) ?></div>
          <div class="conv-preview"><?= $lmsg ?></div>
        </div>
        <div class="conv-right">
          <div class="conv-time"><?= $ltime ?></div>
          <?php if($unread>0): ?><div class="unread-badge"><?= $unread ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div><!-- /.chat-sb -->

  <!-- ════ MAIN CHAT ════ -->
  <?php if($activeConvId && $otherUser): ?>
  <div class="chat-main open" id="chat-main">
    <?php $col2 = $roleColors[$otherUser['role']] ?? '#6366f1'; ?>

    <!-- Topbar -->
    <div class="chat-topbar">
      <button class="back-btn" onclick="goBack()" title="Back"><i class="fa fa-arrow-left"></i></button>
      <div class="cav" style="width:38px;height:38px;font-size:.88rem;background:<?= $col2 ?>;flex-shrink:0">
        <?= strtoupper(substr($otherUser['name'],0,1)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div class="chat-peer-name"><?= sanitize($otherUser['name']) ?></div>
        <div class="chat-peer-role">
          <span class="role-badge rb-<?= $otherUser['role'] ?>"><?= ucfirst($otherUser['role']) ?></span>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-msgs" id="chat-msgs">
      <?php if(empty($messages)): ?>
      <div class="no-msgs">
        <i class="fa fa-comments" style="font-size:2.5rem;opacity:.25"></i>
        <div style="font-weight:700">No messages yet</div>
        <div style="font-size:.82rem">Say hello to <?= sanitize($otherUser['name']) ?>!</div>
      </div>
      <?php else:
        $prevDate='';
        foreach($messages as $m):
          $mDate  = date('Y-m-d',strtotime($m['created_at']));
          $isMine = ((int)$m['sender_id']===$myId);
          $mTime  = date('H:i',strtotime($m['created_at']));
          if($mDate!==$prevDate):
            $prevDate=$mDate;
            $dlabel = ($mDate===date('Y-m-d'))?'Today'
                     :(($mDate===date('Y-m-d',strtotime('-1 day')))?'Yesterday'
                     :date('d M Y',strtotime($mDate)));
      ?>
      <div class="date-sep"><span><?= $dlabel ?></span></div>
      <?php endif; ?>

      <div class="msg-row <?= $isMine?'mine':'' ?>" id="msg-<?= (int)$m['id'] ?>">
        <?php if(!$isMine): ?>
        <div class="msg-av" style="background:<?= $col2 ?>">
          <?= strtoupper(substr($m['sender_name'],0,1)) ?>
        </div>
        <?php endif; ?>

        <div class="msg-col">
          <?php if(!$isMine): ?>
          <div class="sender-name"><?= sanitize($m['sender_name']) ?></div>
          <?php endif; ?>

          <div class="bubble <?= $isMine?'mine':'theirs' ?>">
            <?php if($m['attachment']): ?>
              <?php if($m['attachment_type']==='image'): ?>
              <img src="<?= sanitize($m['attachment']) ?>" class="att-img"
                   onclick="window.open(this.src,'_blank')" alt="img"><br>
              <?php else: ?>
              <a href="<?= sanitize($m['attachment']) ?>" target="_blank" class="att-file">
                <i class="fa fa-file-pdf"></i> <?= sanitize(basename($m['attachment'])) ?>
              </a><br>
              <?php endif; ?>
            <?php endif; ?>
            <span class="bubble-inner"><?= nl2br(sanitize($m['message'] ?? '')) ?></span>
            <span class="bubble-time">
              <?= $mTime ?>
              <?php if($isMine): ?>
              <i class="fa fa-check<?= $m['is_read']?'-double':'' ?>" style="font-size:.55rem"></i>
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div><!-- /.chat-msgs -->

    <!-- Attachment preview -->
    <div class="attach-preview-bar" id="att-preview">
      <i class="fa fa-paperclip" style="color:var(--primary)"></i>
      <span id="att-name" style="flex:1;font-size:.78rem"></span>
      <button onclick="clearAtt()" style="background:none;border:none;color:var(--danger);cursor:pointer"><i class="fa fa-xmark"></i></button>
    </div>

    <!-- Input -->
    <div class="chat-input-bar">
      <div class="input-row">
        <label class="attach-label" title="Attach image or PDF">
          <i class="fa fa-paperclip"></i>
          <input type="file" id="file-inp" style="display:none"
                 accept="image/*,.pdf" onchange="onFile(this)">
        </label>
        <textarea class="chat-textarea" id="msg-inp" rows="1"
                  placeholder="Type a message…"
                  onkeydown="handleKey(event)"
                  oninput="autoResize(this)"></textarea>
        <button class="send-btn" onclick="sendMsg()"><i class="fa fa-paper-plane"></i></button>
      </div>
    </div>
  </div><!-- /.chat-main -->

  <?php else: ?>
  <!-- Desktop no-conv placeholder -->
  <div class="no-conv-placeholder" id="chat-main">
    <i class="fa fa-comments" style="font-size:3.5rem;opacity:.2"></i>
    <div style="font-weight:700;font-size:1rem">Select a conversation</div>
    <div style="font-size:.83rem;color:var(--muted)">or tap <strong>New Conversation</strong></div>
  </div>
  <?php endif; ?>

</div><!-- /.chat-root -->

<script>
const CONV_ID   = <?= $activeConvId ?>;
const MY_ID     = <?= $myId ?>;
let   lastMsgId = <?= $lastMsgId ?>;
const isMobile  = () => window.innerWidth < 700;

// ── Mobile nav ───────────────────────────────
function openConv(id){ location.href = 'chat.php?conv=' + id; }
function goBack(){
    document.getElementById('chat-main')?.classList.remove('open');
    document.getElementById('chat-sb')?.classList.remove('hidden');
}
// On mobile, hide sidebar when a conv is open
document.addEventListener('DOMContentLoaded', () => {
    if(isMobile() && CONV_ID){
        document.getElementById('chat-sb')?.classList.add('hidden');
        document.getElementById('chat-main')?.classList.add('open');
    }
    scrollDown();
});

// ── Scroll ───────────────────────────────────
function scrollDown(){
    const el = document.getElementById('chat-msgs');
    if(el) el.scrollTop = el.scrollHeight;
}

// ── Resize textarea ──────────────────────────
function autoResize(el){
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

// ── Enter = send ─────────────────────────────
function handleKey(e){
    if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMsg(); }
}

// ── Send message ──────────────────────────────
function sendMsg(){
    if(!CONV_ID) return;
    const inp  = document.getElementById('msg-inp');
    const file = document.getElementById('file-inp');
    const txt  = inp.value.trim();
    if(!txt && !file.files.length) return;

    const now  = new Date();
    const time = now.toTimeString().slice(0,5);
    addBubble({ id:'tmp'+now.getTime(), sender_id:MY_ID, message:txt,
                attachment:null, created_at:now.toISOString(), is_read:0 }, true);
    inp.value=''; inp.style.height='auto';

    const fd = new FormData();
    fd.append('action','send_message');
    fd.append('conv_id', CONV_ID);
    fd.append('message', txt);
    if(file.files[0]) fd.append('attachment', file.files[0]);
    clearAtt();

    fetch('chat.php',{ method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json())
        .then(d=>{ if(d.success) lastMsgId = Math.max(lastMsgId, d.id); })
        .catch(()=>{});
}

// ── Poll ──────────────────────────────────────
function poll(){
    if(!CONV_ID) return;
    const fd = new FormData();
    fd.append('action','get_messages');
    fd.append('conv_id', CONV_ID);
    fd.append('last_id', lastMsgId);
    fetch('chat.php',{ method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json())
        .then(d=>{
            if(d.messages && d.messages.length){
                d.messages.forEach(m=>{
                    if(document.getElementById('msg-'+m.id)) return;
                    if(parseInt(m.sender_id)===MY_ID) return;
                    addBubble(m, false);
                    lastMsgId = Math.max(lastMsgId, parseInt(m.id));
                });
                scrollDown();
            }
        })
        .catch(()=>{})
        .finally(()=>{ setTimeout(poll, 3000); });
}
if(CONV_ID) setTimeout(poll, 3000);

// ── Render a bubble ───────────────────────────
function addBubble(m, isMine){
    // Remove empty state
    document.querySelector('.no-msgs')?.remove();

    const msgs = document.getElementById('chat-msgs');
    if(!msgs) return;

    const t   = m.created_at ? new Date(m.created_at).toTimeString().slice(0,5) : '--:--';
    const txt = (m.message||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    const tickHtml = isMine ? `<i class="fa fa-clock" style="font-size:.55rem"></i>` : '';

    let attHtml = '';
    if(m.attachment){
        if(m.attachment_type==='image')
            attHtml = `<img src="${m.attachment}" class="att-img" onclick="window.open(this.src,'_blank')" alt="img"><br>`;
        else
            attHtml = `<a href="${m.attachment}" target="_blank" class="att-file"><i class="fa fa-file-pdf"></i> File</a><br>`;
    }

    const row = document.createElement('div');
    row.className = 'msg-row' + (isMine?' mine':'');
    row.id = 'msg-' + m.id;

    if(isMine){
        row.innerHTML = `
        <div class="msg-col">
          <div class="bubble mine">
            ${attHtml}
            <span class="bubble-inner">${txt}</span>
            <span class="bubble-time">${t} ${tickHtml}</span>
          </div>
        </div>`;
    } else {
        const init = (m.sender_name||'?')[0].toUpperCase();
        row.innerHTML = `
        <div class="msg-av" style="background:var(--primary)">${init}</div>
        <div class="msg-col">
          <div class="sender-name">${m.sender_name||''}</div>
          <div class="bubble theirs">
            ${attHtml}
            <span class="bubble-inner">${txt}</span>
            <span class="bubble-time">${t}</span>
          </div>
        </div>`;
    }
    msgs.appendChild(row);
    scrollDown();
}

// ── File attach ───────────────────────────────
function onFile(inp){
    if(inp.files[0]){
        document.getElementById('att-name').textContent = inp.files[0].name;
        document.getElementById('att-preview').classList.add('show');
    }
}
function clearAtt(){
    document.getElementById('file-inp').value='';
    document.getElementById('att-name').textContent='';
    document.getElementById('att-preview').classList.remove('show');
}

// ── Dropdown ──────────────────────────────────
function toggleDD(e){
    e.stopPropagation();
    document.getElementById('user-dd').classList.toggle('open');
    setTimeout(()=>document.getElementById('dd-search')?.focus(), 60);
}
document.addEventListener('click',()=> document.getElementById('user-dd')?.classList.remove('open'));
function startChat(uid){ location.href = 'chat.php?with=' + uid; }
function filterUsers(inp){
    const q=inp.value.toLowerCase();
    document.querySelectorAll('.user-dd-item').forEach(el=>{
        el.style.display=(el.dataset.search||'').includes(q)?'':'none';
    });
}
function filterConvs(inp){
    const q=inp.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(el=>{
        el.style.display=(el.dataset.name||'').includes(q)?'':'none';
    });
}
</script>

<?php include 'layout_end.php'; ?>
