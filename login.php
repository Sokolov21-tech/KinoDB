<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();
if (isLoggedIn()) { redirect(APP_URL . '/'); }

$error    = '';
$redirect = sanitizeString($_GET['redirect'] ?? APP_URL . '/', 500);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimitOrDie('auth_login', RATE_LIMIT_MAX_AUTH);
    csrfVerify();
    $identifier = sanitizeString($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember']);

    $result = login($identifier, $password);

    if ($result['ok']) {
        if (!empty($result['needs_2fa'])) {
            redirect(APP_URL . '/2fa-verify.php');
        }
        if ($remember) {
             
            session_set_cookie_params(['lifetime' => 30 * 86400]);
        }
        redirect($redirect ?: APP_URL . '/');
    } else {
        $error = $result['error'];
    }
}

$pageTitle = 'Вход';
$bodyClass = 'auth-page-body';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Syncopate:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
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

        <h1 class="auth-title">Добро пожаловать</h1>
        <p class="auth-subtitle">Войдите в свой аккаунт</p>

        <?php if ($error): ?>
        <div class="flash flash--error" style="margin-bottom:var(--gap-lg);">
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $f): ?>
        <div class="flash flash--<?= e($f['type']) ?>" style="margin-bottom:var(--gap-lg);">
            <?= e($f['message']) ?>
        </div>
        <?php endforeach; unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <form method="POST" novalidate>
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="identifier">Email или имя пользователя</label>
                <input type="text" id="identifier" name="identifier"
                       class="form-control <?= $error ? 'error' : '' ?>"
                       value="<?= e($_POST['identifier'] ?? '') ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password"
                           class="form-control <?= $error ? 'error' : '' ?>"
                           autocomplete="current-password" required>
                    <button type="button" class="btn-show-pw" id="toggle-pw">Показать</button>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--gap-lg);">
                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:var(--text-muted);cursor:pointer;">
                    <input type="checkbox" name="remember" style="accent-color:var(--amber);">
                    Запомнить меня
                </label>
                <a href="<?= APP_URL ?>/forgot-password.php" style="font-size:0.82rem;color:var(--amber);">Забыли пароль?</a>
            </div>

            <button type="submit" class="btn btn--primary btn--full btn--lg">Войти</button>
        </form>

        <p class="auth-footer">
            Нет аккаунта? <a href="<?= APP_URL ?>/register.php">Зарегистрироваться</a>
        </p>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
