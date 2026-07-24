<?php
require_once 'config.php';

// Clear remember me cookie
if (isset($_COOKIE[REMEMBER_ME_COOKIE])) {
    $pdo->prepare("DELETE FROM remember_tokens WHERE token=?")->execute([$_COOKIE[REMEMBER_ME_COOKIE]]);
    setcookie(REMEMBER_ME_COOKIE, '', time()-3600, '/', '', false, true);
}

// Destroy session
session_destroy();
header('Location: login.php?msg=logged_out');
exit();
