<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();

 
if (empty($_SESSION['pending_2fa_user'])) {
    redirect(APP_URL . '/login.php');
}

rateLimitOrDie('2fa_verify', RATE_LIMIT_MAX_AUTH);

$userId = (int)$_SESSION['pending_2fa_user'];
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (verify2FA($userId, $code)) {
        $user = dbFetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        sessionLoginUser($user);
        redirect(APP_URL . '/');
    } else {
        $error = 'Неверный код. Попробуйте снова.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Двухфакторная аутентификация — <?= APP_NAME ?></title>
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

        <div style="font-size:2.5rem;margin-bottom:var(--gap);">🔐</div>
        <h1 class="auth-title">Двухфакторная<br>аутентификация</h1>
        <p class="auth-subtitle">Введите 6-значный код из приложения-аутентификатора или резервный код</p>

        <?php if ($error): ?>
        <div class="flash flash--error" style="margin-bottom:var(--gap-lg);"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <input type="text" id="twofa-code" name="code"
                       class="form-control <?= $error ? 'error' : '' ?>"
                       placeholder="000000"
                       maxlength="8" pattern="\d{6,8}" autocomplete="one-time-code"
                       style="text-align:center;font-family:var(--font-label);font-size:1.8rem;letter-spacing:0.3em;"
                       autofocus required>
            </div>
            <button type="submit" class="btn btn--primary btn--full btn--lg">Подтвердить</button>
        </form>

        <p class="auth-footer">
            <a href="<?= APP_URL ?>/login.php">← Назад ко входу</a>
        </p>
    </div>
</div>

<script>

document.getElementById('twofa-code').addEventListener('input', function() {
    const digits = this.value.replace(/\D/g, '');
    this.value = digits;
    if (digits.length === 6) this.closest('form').submit();
});
</script>
</body>
</html>
