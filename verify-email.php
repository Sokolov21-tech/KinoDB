<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();

$token  = sanitizeString($_GET['token'] ?? '', 128);
$result = $token ? verifyEmailToken($token) : ['ok' => false, 'error' => 'Токен не указан.'];

if ($result['ok']) {
    $_SESSION['flash'][] = ['type' => 'success', 'message' => 'Email подтверждён! Вы можете войти.'];
    redirect(APP_URL . '/login.php');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Syncopate:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="auth-page">
    <div class="auth-card" style="text-align:center;">
        <div class="auth-logo">
            <a href="<?= APP_URL ?>/" class="logo" style="justify-content:center;">
                <span class="logo-icon">▶</span>
                <span class="logo-text">Kino<em>DB</em></span>
            </a>
        </div>
        <div style="font-size:3rem;margin-bottom:var(--gap);">✗</div>
        <h1 class="auth-title" style="color:var(--red-bright);">Ошибка</h1>
        <p style="color:var(--text-muted);margin:var(--gap) 0 var(--gap-xl);"><?= e($result['error']) ?></p>
        <a href="<?= APP_URL ?>/register.php" class="btn btn--primary">Зарегистрироваться снова</a>
        <p class="auth-footer"><a href="<?= APP_URL ?>/">На главную</a></p>
    </div>
</div>
</body>
</html>
