<?php
require_once 'config.php';
if (isLoggedIn()) { redirectByRole(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email=? OR name=?) AND status='active' LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['dark']    = $user['dark_mode'] ?? 0;

            if ($user['role'] === 'teacher') {
                $t = $pdo->prepare("SELECT id FROM teachers WHERE user_id=?");
                $t->execute([$user['id']]);
                $tr = $t->fetch();
                $_SESSION['teacher_id'] = $tr['id'] ?? null;
            }
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);

            if (!empty($_POST['remember'])) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("INSERT INTO remember_tokens (user_id,token,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))")
                    ->execute([$user['id'], $token]);
                setcookie(REMEMBER_ME_COOKIE, $token, time() + REMEMBER_ME_EXPIRE, '/', '', false, true);
            }
            redirectByRole();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Login · EduTrack</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;overflow-x:hidden;}
body{
  font-family:'Segoe UI',system-ui,sans-serif;
  background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
  display:flex;align-items:center;justify-content:center;
  min-height:100vh;padding:12px 0;
  overflow-y:auto;
}
.wrap{
  width:100%;max-width:400px;
  padding:0 16px;
}
.card{
  background:#fff;border-radius:16px;
  padding:24px 28px 20px;
  box-shadow:0 20px 60px rgba(0,0,0,0.2);
  animation:up .35s ease;
}
@keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* Header */
.top{display:flex;align-items:center;gap:12px;margin-bottom:18px;}
.logo{
  width:44px;height:44px;border-radius:12px;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;overflow:hidden;
}
.logo img{width:44px;height:44px;object-fit:cover;border-radius:12px;}
.logo i{color:#fff;font-size:1.3rem;}
.top-text h1{font-size:1rem;font-weight:800;color:#1e293b;line-height:1.2;}
.top-text p{font-size:.73rem;color:#64748b;margin-top:1px;}

/* Error */
.err{
  background:#fef2f2;border:1px solid #fecaca;color:#dc2626;
  padding:8px 12px;border-radius:8px;margin-bottom:12px;
  font-size:.8rem;display:flex;align-items:center;gap:7px;
}

/* Form */
.fg{margin-bottom:11px;}
.fg label{display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;}
.iw{position:relative;}
.iw .ic{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.8rem;}
.iw input{
  width:100%;padding:9px 36px 9px 32px;
  border:1.5px solid #e5e7eb;border-radius:9px;
  font-size:.85rem;color:#1e293b;background:#f9fafb;
  transition:border-color .2s;
}
.iw input:focus{outline:none;border-color:#6366f1;background:#fff;}
.iw .eye{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  cursor:pointer;color:#9ca3af;font-size:.8rem;
  background:none;border:none;padding:2px;
}
.row{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;font-size:.75rem;}
.row label{display:flex;align-items:center;gap:5px;color:#6b7280;cursor:pointer;}
.row a{color:#6366f1;text-decoration:none;}
.btn-login{
  width:100%;padding:10px;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  color:#fff;border:none;border-radius:9px;
  font-size:.9rem;font-weight:700;cursor:pointer;
  transition:opacity .15s;letter-spacing:.2px;
}
.btn-login:hover{opacity:.92;}

/* Demo */
.div{text-align:center;margin:14px 0 10px;color:#9ca3af;font-size:.72rem;position:relative;}
.div::before,.div::after{content:'';position:absolute;top:50%;width:36%;height:1px;background:#e5e7eb;}
.div::before{left:0}.div::after{right:0}

.demo-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;}
.demo-btn{
  padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:9px;
  background:#f9fafb;cursor:pointer;text-align:left;
  transition:border-color .2s,background .2s;
}
.demo-btn:hover{border-color:#6366f1;background:#f0f0ff;}
.demo-btn .role{
  font-size:.72rem;font-weight:700;color:#6366f1;
  display:flex;align-items:center;gap:5px;margin-bottom:1px;
}
.demo-btn .creds{font-size:.66rem;color:#9ca3af;line-height:1.4;}

.footer{text-align:center;margin-top:12px;color:rgba(255,255,255,.65);font-size:.7rem;}

@media(max-height:600px){
  .card{padding:16px 20px 14px;}
  .fg{margin-bottom:8px;}
  .top{margin-bottom:12px;}
  .div{margin:10px 0 8px;}
}
@media(max-width:420px){
  .wrap{padding:0 12px;}
  .card{padding:20px 18px 16px;border-radius:14px;}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="top">
      <div class="logo">
        <img src="images/logo.png" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" alt="">
        <i class="fa fa-graduation-cap" style="display:none"></i>
      </div>
      <div class="top-text">
        <h1>EduTrack School System</h1>
        <p>International Edition v2.0</p>
      </div>
    </div>

    <?php if($error): ?>
    <div class="err"><i class="fa fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="fg">
        <label>Email / Username</label>
        <div class="iw">
          <i class="ic fa fa-envelope"></i>
          <input type="text" name="username" placeholder="Enter your email"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="fg">
        <label>Password</label>
        <div class="iw">
          <i class="ic fa fa-lock"></i>
          <input type="password" name="password" id="pw" placeholder="Enter your password" required>
          <button type="button" class="eye" onclick="togglePw()"><i class="fa fa-eye" id="eye-ic"></i></button>
        </div>
      </div>
      <div class="row">
        <label><input type="checkbox" name="remember"> Remember me</label>
        <a href="#">Forgot password?</a>
      </div>
      <button type="submit" class="btn-login"><i class="fa fa-right-to-bracket"></i> &nbsp;Sign In</button>
    </form>

    <div class="div">Quick Login</div>
    <div class="demo-grid">
      <div class="demo-btn" onclick="fill('admin@school.com','superadmin123')">
        <div class="role"><i class="fa fa-shield-halved"></i>Admin</div>
        <div class="creds">admin@school.com<br>superadmin123</div>
      </div>
      <div class="demo-btn" onclick="fill('james@school.com','teacher123')">
        <div class="role"><i class="fa fa-chalkboard-teacher"></i>Teacher</div>
        <div class="creds">james@school.com<br>teacher123</div>
      </div>
      <div class="demo-btn" onclick="fill('sarah@school.com','teacher123')">
        <div class="role"><i class="fa fa-house"></i>Homeroom</div>
        <div class="creds">sarah@school.com<br>teacher123</div>
      </div>
      <div class="demo-btn" onclick="fill('parent.smith@mail.com','parent123')">
        <div class="role"><i class="fa fa-users"></i>Parent</div>
        <div class="creds">parent.smith@mail.com<br>parent123</div>
      </div>
    </div>
  </div>
  <div class="footer">© 2025/2026 EduTrack International School</div>
</div>
<script>
function togglePw(){
  const i=document.getElementById('pw'),e=document.getElementById('eye-ic');
  i.type=i.type==='password'?'text':'password';
  e.className=i.type==='password'?'fa fa-eye':'fa fa-eye-slash';
}
function fill(u,p){
  document.querySelector('[name=username]').value=u;
  document.querySelector('[name=password]').value=p;
}
</script>
</body>
</html>
