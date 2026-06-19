<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();
if (isLoggedIn()) { redirect(APP_URL . '/'); }

rateLimitOrDie('auth_register', RATE_LIMIT_MAX_AUTH);

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $username  = sanitizeString($_POST['username'] ?? '');
    $email     = sanitizeEmail($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password !== $password2) {
        $errors[] = 'Пароли не совпадают.';
    } else {
        $result = register($username, $email, $password);
        if ($result['ok']) {
            $success = true;
        } else {
            $errors[] = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — <?= APP_NAME ?></title>
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
    <div class="auth-card" style="max-width:500px;">
        <div class="auth-logo">
            <a href="<?= APP_URL ?>/" class="logo" style="justify-content:center;">
                <span class="logo-icon">▶</span>
                <span class="logo-text">Kino<em>DB</em></span>
            </a>
        </div>

        <h1 class="auth-title">Регистрация</h1>
        <p class="auth-subtitle">Создайте аккаунт кинолюбителя</p>

        <?php if ($success): ?>
        <div class="flash flash--success" style="margin-bottom:var(--gap-lg);">
            <strong>Аккаунт создан!</strong><br>
            Мы отправили письмо с подтверждением на ваш email.
        </div>
        <?php if (MAIL_DEBUG): ?>
        <p style="font-size:0.8rem;color:var(--text-muted);text-align:center;margin-bottom:var(--gap-lg);">
            Режим разработки: письмо записано в <code>logs/mail.log</code>
        </p>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/login.php" class="btn btn--primary btn--full">Перейти ко входу</a>
        <?php else: ?>

        <?php foreach ($errors as $err): ?>
        <div class="flash flash--error" style="margin-bottom:var(--gap-sm);"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" novalidate id="reg-form">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="username">Имя пользователя</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       autocomplete="username" pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50" required autofocus>
                <div class="form-hint">Только латинские буквы, цифры и _</div>
                <div class="form-error" id="err-username">Имя пользователя должно содержать от 3 до 50 символов.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       autocomplete="email" required>
                <div class="form-error" id="err-email">Введите корректный email адрес.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" class="form-control"
                           autocomplete="new-password" minlength="8" required>
                    <button type="button" class="btn-show-pw" onclick="togglePw('password', this)">Показать</button>
                </div>
                <div class="form-hint">Минимум 8 символов, заглавная буква и цифра</div>

                
                <div style="margin-top:0.4rem;height:4px;background:var(--noir-5);border-radius:2px;">
                    <div id="pw-strength-bar" style="height:100%;border-radius:2px;width:0;transition:width 0.3s,background 0.3s;"></div>
                </div>
                <div id="pw-strength-label" style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;"></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password2">Повторите пароль</label>
                <div class="pw-wrap">
                    <input type="password" id="password2" name="password2" class="form-control"
                           autocomplete="new-password" required>
                    <button type="button" class="btn-show-pw" onclick="togglePw('password2', this)">Показать</button>
                </div>
                <div class="form-error" id="err-password2">Пароли не совпадают.</div>
            </div>

            <label style="display:flex;align-items:flex-start;gap:0.6rem;margin-bottom:var(--gap-lg);font-size:0.84rem;color:var(--text-muted);cursor:pointer;">
                <input type="checkbox" name="agree" required style="margin-top:3px;accent-color:var(--amber);">
                Я согласен с условиями использования платформы
            </label>

            <button type="submit" class="btn btn--primary btn--full btn--lg">Создать аккаунт</button>
        </form>
        <?php endif; ?>

        <p class="auth-footer">
            Уже есть аккаунт? <a href="<?= APP_URL ?>/login.php">Войти</a>
        </p>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/register.js"></script>
</body>
</html>
