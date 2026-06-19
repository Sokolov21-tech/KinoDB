<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();
if (isLoggedIn()) { redirect(APP_URL . '/'); }

rateLimitOrDie('auth_reset', 10);

$token   = sanitizeString($_GET['token'] ?? '', 128);
$valid   = false;
$done    = false;
$error   = '';

if ($token) {
    $row = dbFetch("SELECT id FROM password_resets WHERE token = :t AND used = 0 AND expires_at > NOW() LIMIT 1", ['t' => $token]);
    $valid = (bool)$row;
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $pw  = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if ($pw !== $pw2) {
        $error = 'Пароли не совпадают.';
    } else {
        $result = resetPassword($token, $pw);
        if ($result['ok']) {
            $done = true;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый пароль — <?= APP_NAME ?></title>
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
        <h1 class="auth-title">Новый пароль</h1>

        <?php if ($done): ?>
        <div class="flash flash--success" style="margin-bottom:var(--gap-xl);">Пароль успешно изменён!</div>
        <a href="<?= APP_URL ?>/login.php" class="btn btn--primary btn--full">Войти</a>

        <?php elseif (!$token || !$valid): ?>
        <div class="flash flash--error" style="margin-bottom:var(--gap-xl);">Недействительная или истёкшая ссылка сброса пароля.</div>
        <a href="<?= APP_URL ?>/forgot-password.php" class="btn btn--primary btn--full">Запросить новую ссылку</a>

        <?php else: ?>
        <?php if ($error): ?><div class="flash flash--error mb"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-group">
                <label class="form-label">Новый пароль</label>
                <input type="password" name="password" class="form-control"
                       minlength="8" required autocomplete="new-password" autofocus>
                <div class="form-hint">Минимум 8 символов, заглавная буква и цифра</div>
            </div>
            <div class="form-group">
                <label class="form-label">Повторите пароль</label>
                <input type="password" name="password2" class="form-control"
                       minlength="8" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn--primary btn--full btn--lg">Установить пароль</button>
        </form>
        <?php endif; ?>

        <p class="auth-footer"><a href="<?= APP_URL ?>/login.php">← Ко входу</a></p>
    </div>
</div>
</body>
</html>
