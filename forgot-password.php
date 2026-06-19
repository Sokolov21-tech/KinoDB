<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();
if (isLoggedIn()) { redirect(APP_URL . '/'); }

rateLimitOrDie('auth_forgot', 5);

$sent  = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $email = sanitizeEmail($_POST['email'] ?? '');
    if ($email && validateEmail($email)) {
        requestPasswordReset($email);
    }
    $sent = true;  
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Syncopate:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <a href="<?= APP_URL ?>/" class="logo" style="justify-content:center;">
                <span class="logo-icon">▶</span>
                <span class="logo-text">Kino<em>DB</em></span>
            </a>
        </div>
        <h1 class="auth-title">Восстановление<br>пароля</h1>
        <p class="auth-subtitle">Введите email вашего аккаунта</p>

        <?php if ($sent): ?>
        <div class="flash flash--success" style="margin-bottom:var(--gap-xl);">
            Если аккаунт с таким email существует, мы отправили инструкции по сбросу пароля.
            <?php if (MAIL_DEBUG): ?>
            <br><em style="font-size:0.78rem;">Проверьте файл <code>logs/mail.log</code></em>
            <?php endif; ?>
        </div>
        <a href="<?= APP_URL ?>/login.php" class="btn btn--primary btn--full">Вернуться ко входу</a>
        <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       autocomplete="email" required autofocus>
            </div>
            <button type="submit" class="btn btn--primary btn--full btn--lg">Отправить ссылку</button>
        </form>
        <?php endif; ?>

        <p class="auth-footer"><a href="<?= APP_URL ?>/login.php">← Назад ко входу</a></p>
    </div>
</div>
</body>
</html>
