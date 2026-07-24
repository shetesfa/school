<?php
require_once 'config.php';
if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: login.php');
    exit();
}
