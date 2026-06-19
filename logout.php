<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     
    require_once 'includes/security.php';
    csrfVerify();
}

logout();
header('Location: ' . APP_URL . '/');
exit;
