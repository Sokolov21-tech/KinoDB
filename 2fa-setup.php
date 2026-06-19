<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

startSession();
requireLogin();

$user  = currentUser();
$step  = 'show';  
$data  = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if (isset($_POST['start'])) {
        $data = enable2FA($user['id']);
        $step = 'confirm';
        $_SESSION['2fa_setup_secret'] = $data['secret'];
        $_SESSION['2fa_backup_codes'] = $data['codes'];
    }

    if (isset($_POST['verify'])) {
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        $code   = preg_replace('/\D/', '', $_POST['code'] ?? '');
        if ($secret && confirm2FA($user['id'], $code)) {
            $step = 'done';
            unset($_SESSION['2fa_setup_secret']);
            $_SESSION['user_role'] = $_SESSION['user_role'];  
        } else {
            $error = 'Неверный код. Попробуйте снова.';
            $step  = 'confirm';
            $data['secret']  = $_SESSION['2fa_setup_secret'] ?? '';
            $data['codes']   = $_SESSION['2fa_backup_codes'] ?? [];
            $dbUser = dbFetch("SELECT username FROM users WHERE id = :id", ['id' => $user['id']]);
            $data['qr_url']  = TOTP::getQRUrl($data['secret'], $dbUser['username']);
        }
    }
}

$pageTitle = 'Настройка 2FA';
require_once 'includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width:600px;">
        <div class="section-rule"></div>
        <h1 class="section-title">Двухфакторная <em>аутентификация</em></h1>

        <?php if ($step === 'show'): ?>
        <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);margin-top:var(--gap-xl);">
            <p style="color:var(--text-muted);margin-bottom:var(--gap-lg);">
                Двухфакторная аутентификация добавляет дополнительный уровень защиты.
                Вам потребуется приложение-аутентификатор (Google Authenticator, Authy и т.д.).
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <button name="start" class="btn btn--primary">Включить 2FA</button>
            </form>
        </div>

        <?php elseif ($step === 'confirm'): ?>
        <?php if ($error): ?>
        <div class="flash flash--error mt"><?= e($error) ?></div>
        <?php endif; ?>
        <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);margin-top:var(--gap-xl);">
            <p class="label mb">Шаг 1: Отсканируйте QR-код</p>
            <div style="text-align:center;margin-bottom:var(--gap-xl);">
                <img src="<?= e($data['qr_url']) ?>" alt="QR код для 2FA"
                     style="border-radius:var(--r);background:#fff;padding:8px;display:inline-block;">
            </div>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:var(--gap);">
                Или введите секретный ключ вручную:
            </p>
            <code style="display:block;background:var(--noir-4);padding:var(--gap);border-radius:var(--r);letter-spacing:0.15em;font-size:0.95rem;color:var(--amber);margin-bottom:var(--gap-xl);">
                <?= e($data['secret']) ?>
            </code>

            <p class="label mb">Шаг 2: Подтвердите код</p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="verify" value="1">
                <div class="form-group">
                    <input type="text" name="code"
                           class="form-control"
                           placeholder="000000"
                           maxlength="6" pattern="\d{6}"
                           autocomplete="one-time-code"
                           style="text-align:center;font-family:var(--font-label);font-size:1.5rem;letter-spacing:0.3em;"
                           autofocus required>
                </div>
                <button type="submit" class="btn btn--primary btn--full">Подтвердить и включить</button>
            </form>
        </div>

        <?php elseif ($step === 'done'): ?>
        <div style="background:var(--noir-2);border:1px solid var(--green);border-radius:var(--r-lg);padding:var(--gap-xl);margin-top:var(--gap-xl);text-align:center;">
            <div style="font-size:3rem;margin-bottom:var(--gap);">✓</div>
            <h2 style="color:var(--green);margin-bottom:var(--gap);">2FA включена!</h2>
            <p style="color:var(--text-muted);margin-bottom:var(--gap-xl);">Сохраните резервные коды в безопасном месте.</p>

            <div style="background:var(--noir-4);border:1px solid var(--border);border-radius:var(--r);padding:var(--gap-lg);margin-bottom:var(--gap-xl);">
                <p class="label mb">Резервные коды (каждый используется один раз)</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap-sm);font-family:var(--font-label);font-size:0.85rem;letter-spacing:0.08em;">
                    <?php foreach ($_SESSION['2fa_backup_codes'] ?? [] as $code): ?>
                    <div style="background:var(--noir-3);padding:0.4rem 0.8rem;border-radius:var(--r-sm);"><?= e($code) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="<?= APP_URL ?>/profile.php" class="btn btn--primary">В профиль</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
