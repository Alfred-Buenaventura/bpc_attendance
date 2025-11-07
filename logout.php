<?php
require_once 'config.php';

if (isLoggedIn()) {
    $db = db();
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
}

session_unset();
session_destroy();

header('Location: login.php?logout=1');
exit;
?>
