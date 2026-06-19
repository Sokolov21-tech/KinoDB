<?php
 
 
 

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';

 
 
 

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,    
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
         
        if (empty($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 300) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
    }
}

 
 
 

function register(string $username, string $email, string $password): array {
    $username = sanitizeString($username, 50);
    $email    = sanitizeEmail($email);

    if (!$username || strlen($username) < 3) {
        return ['ok' => false, 'error' => 'Имя пользователя должно содержать от 3 до 50 символов.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['ok' => false, 'error' => 'Имя пользователя может содержать только буквы, цифры и _'];
    }
    if (!$email || !validateEmail($email)) {
        return ['ok' => false, 'error' => 'Некорректный email адрес.'];
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['ok' => false, 'error' => 'Пароль должен содержать минимум ' . PASSWORD_MIN_LENGTH . ' символов.'];
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['ok' => false, 'error' => 'Пароль должен содержать заглавную букву и цифру.'];
    }
    if (dbExists('users', ['username' => $username])) {
        return ['ok' => false, 'error' => 'Это имя пользователя уже занято.'];
    }
    if (dbExists('users', ['email' => $email])) {
        return ['ok' => false, 'error' => 'Этот email уже зарегистрирован.'];
    }

    $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $userId = dbInsert('users', [
        'username'      => $username,
        'email'         => $email,
        'password_hash' => $hash,
    ]);

    sendVerificationEmail($userId, $email, $username);
    return ['ok' => true, 'user_id' => $userId];
}

 
 
 

function login(string $identifier, string $password): array {
    rateLimitOrDie('auth_login', RATE_LIMIT_MAX_AUTH);

    $identifier = sanitizeString($identifier);
    $field      = str_contains($identifier, '@') ? 'email' : 'username';
    $user       = dbFetch("SELECT * FROM users WHERE `$field` = :id LIMIT 1", ['id' => $identifier]);

    if (!$user) {
         
        password_verify('dummy', '$2y$12$invalidhashtopreventtimingattacks');
        return ['ok' => false, 'error' => 'Неверные данные для входа.'];
    }

     
    if ($user['is_banned']) {
        return ['ok' => false, 'error' => 'Аккаунт заблокирован. Обратитесь в поддержку.'];
    }
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $wait = ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['ok' => false, 'error' => "Аккаунт временно заблокирован. Попробуйте через $wait мин."];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = $user['login_attempts'] + 1;
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
            dbUpdate('users', ['login_attempts' => $attempts, 'locked_until' => $lockedUntil], ['id' => $user['id']]);
        } else {
            dbUpdate('users', ['login_attempts' => $attempts], ['id' => $user['id']]);
        }
        return ['ok' => false, 'error' => 'Неверные данные для входа.'];
    }

     
    dbUpdate('users', ['login_attempts' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

    if (!$user['is_verified']) {
        return ['ok' => false, 'error' => 'Подтвердите email перед входом.', 'unverified' => true];
    }

     
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        dbUpdate('users', ['password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])], ['id' => $user['id']]);
    }

     
    if ($user['twofa_enabled']) {
        $_SESSION['pending_2fa_user'] = $user['id'];
        return ['ok' => true, 'needs_2fa' => true];
    }

    sessionLoginUser($user);
    return ['ok' => true, 'needs_2fa' => false];
}

function sessionLoginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_avatar']   = $user['avatar'];
    unset($_SESSION['pending_2fa_user']);
}

function logout(): void {
    session_unset();
    session_destroy();
    setcookie(SESSION_COOKIE_NAME, '', time() - 3600, '/');
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'       => (int) $_SESSION['user_id'],
        'username' => $_SESSION['user_username'],
        'email'    => $_SESSION['user_email'],
        'role'     => $_SESSION['user_role'],
        'avatar'   => $_SESSION['user_avatar'],
    ];
}

function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function isAdmin(): bool    { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function isMod(): bool      { return in_array($_SESSION['user_role'] ?? '', ['admin','moderator'], true); }

 
 
 

function sendVerificationEmail(int $userId, string $email, string $username): void {
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_HOURS * 3600);

    dbQuery("DELETE FROM email_verifications WHERE user_id = :uid", ['uid' => $userId]);
    dbInsert('email_verifications', ['user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt]);

    $link    = APP_URL . '/verify-email.php?token=' . urlencode($token);
    $subject = APP_NAME . ' — Подтверждение email';
    $body    = "Привет, $username!\n\nПодтвердите email перейдя по ссылке:\n$link\n\nСсылка действительна " . TOKEN_EXPIRY_HOURS . " часов.";

    sendMail($email, $subject, $body);
}

function verifyEmailToken(string $token): array {
    $row = dbFetch(
        "SELECT ev.*, u.username FROM email_verifications ev
         JOIN users u ON u.id = ev.user_id
         WHERE ev.token = :t AND ev.expires_at > NOW() LIMIT 1",
        ['t' => $token]
    );
    if (!$row) {
        return ['ok' => false, 'error' => 'Недействительная или истёкшая ссылка подтверждения.'];
    }
    dbUpdate('users', ['is_verified' => 1], ['id' => $row['user_id']]);
    dbQuery("DELETE FROM email_verifications WHERE id = :id", ['id' => $row['id']]);
    return ['ok' => true, 'username' => $row['username']];
}

 
 
 

function requestPasswordReset(string $email): void {
    $user = dbFetch("SELECT id, username FROM users WHERE email = :e AND is_verified = 1 LIMIT 1", ['e' => $email]);
    if (!$user) return;  

    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    dbQuery("DELETE FROM password_resets WHERE user_id = :uid", ['uid' => $user['id']]);
    dbInsert('password_resets', ['user_id' => $user['id'], 'token' => $token, 'expires_at' => $expiresAt]);

    $link = APP_URL . '/reset-password.php?token=' . urlencode($token);
    sendMail($email, APP_NAME . ' — Сброс пароля',
        "Привет, {$user['username']}!\n\nСсылка для сброса пароля:\n$link\n\nСсылка действительна 1 час.");
}

function resetPassword(string $token, string $newPassword): array {
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return ['ok' => false, 'error' => 'Пароль слишком короткий.'];
    }
    $row = dbFetch(
        "SELECT * FROM password_resets WHERE token = :t AND used = 0 AND expires_at > NOW() LIMIT 1",
        ['t' => $token]
    );
    if (!$row) {
        return ['ok' => false, 'error' => 'Недействительная или истёкшая ссылка сброса.'];
    }
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    dbUpdate('users', ['password_hash' => $hash, 'login_attempts' => 0, 'locked_until' => null], ['id' => $row['user_id']]);
    dbUpdate('password_resets', ['used' => 1], ['id' => $row['id']]);
    return ['ok' => true];
}

 
 
 

class TOTP {
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string {
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= self::BASE32[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getCode(string $secret, ?int $time = null): string {
        $time    = $time ?? time();
        $counter = pack('N*', 0) . pack('N*', (int) floor($time / self::PERIOD));
        $key     = self::base32Decode($secret);
        $hash    = hash_hmac('sha1', $counter, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ( ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code): bool {
        $t = time();
         
        for ($drift = -1; $drift <= 1; $drift++) {
            if (hash_equals(self::getCode($secret, $t + $drift * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getQRUrl(string $secret, string $username): string {
        $issuer  = rawurlencode(APP_NAME);
        $label   = rawurlencode("$issuer:$username");
        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&digits=6&period=30";
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otpauth);
    }

    private static function base32Decode(string $s): string {
        $s      = strtoupper($s);
        $output = '';
        $bits   = 0;
        $val    = 0;
        for ($i = 0; $i < strlen($s); $i++) {
            $pos = strpos(self::BASE32, $s[$i]);
            if ($pos === false) continue;
            $val  = ($val << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $output .= chr(($val >> ($bits - 8)) & 0xFF);
                $bits   -= 8;
            }
        }
        return $output;
    }
}

 
 
 

function enable2FA(int $userId): array {
    $secret = TOTP::generateSecret();
    dbUpdate('users', ['twofa_secret' => $secret], ['id' => $userId]);
    $user   = dbFetch("SELECT username FROM users WHERE id = :id", ['id' => $userId]);
    return [
        'secret'  => $secret,
        'qr_url'  => TOTP::getQRUrl($secret, $user['username']),
        'codes'   => generateBackupCodes($userId),
    ];
}

function confirm2FA(int $userId, string $code): bool {
    $user = dbFetch("SELECT twofa_secret FROM users WHERE id = :id", ['id' => $userId]);
    if (!$user || !$user['twofa_secret']) return false;
    if (!TOTP::verify($user['twofa_secret'], $code)) return false;
    dbUpdate('users', ['twofa_enabled' => 1], ['id' => $userId]);
    return true;
}

function verify2FA(int $userId, string $code): bool {
    $user = dbFetch("SELECT twofa_secret FROM users WHERE id = :id AND twofa_enabled = 1", ['id' => $userId]);
    if (!$user) return false;

     
    if (TOTP::verify($user['twofa_secret'], $code)) return true;

     
    $backups = dbFetchAll("SELECT id, code_hash FROM twofa_backup_codes WHERE user_id = :uid AND used = 0", ['uid' => $userId]);
    foreach ($backups as $b) {
        if (password_verify($code, $b['code_hash'])) {
            dbUpdate('twofa_backup_codes', ['used' => 1], ['id' => $b['id']]);
            return true;
        }
    }
    return false;
}

function disable2FA(int $userId): void {
    dbUpdate('users', ['twofa_enabled' => 0, 'twofa_secret' => null], ['id' => $userId]);
    dbQuery("DELETE FROM twofa_backup_codes WHERE user_id = :uid", ['uid' => $userId]);
}

function generateBackupCodes(int $userId): array {
    dbQuery("DELETE FROM twofa_backup_codes WHERE user_id = :uid", ['uid' => $userId]);
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $code  = strtoupper(bin2hex(random_bytes(4)));
        $codes[] = $code;
        dbInsert('twofa_backup_codes', ['user_id' => $userId, 'code_hash' => password_hash($code, PASSWORD_BCRYPT)]);
    }
    return $codes;
}
