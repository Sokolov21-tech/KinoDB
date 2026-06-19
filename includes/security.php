<?php
 
 
 
 

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

 
 
 

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function csrfVerify(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

 
 
 

function e(mixed $value): string {
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }

    if (!is_scalar($value) && !$value instanceof Stringable) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function eAttr(mixed $value): string {
    return e($value);
}

function eJson(mixed $data): string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

 
 
 

function sanitizeString(string $input, int $maxLen = 500): string {
    $input = trim($input);
    $input = strip_tags($input);
    return mb_substr($input, 0, $maxLen);
}

function sanitizeInt(mixed $input): int {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeEmail(string $input): string|false {
    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
}

function sanitizeUrl(string $input): string|false {
    return filter_var(trim($input), FILTER_SANITIZE_URL);
}

function validateEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateUrl(string $url): bool {
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
}

 
 
 

function getClientIp(): string {
    $headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function checkRateLimit(string $endpoint, int $maxRequests = RATE_LIMIT_MAX_PAGE, int $window = RATE_LIMIT_WINDOW): bool {
    $ip = getClientIp();
    $windowStart = date('Y-m-d H:i:s', time() - $window);

     
    if (rand(1, 100) === 1) {
        dbQuery("DELETE FROM rate_limits WHERE window_start < :ws", ['ws' => $windowStart]);
    }

    $row = dbFetch(
        "SELECT id, requests_count FROM rate_limits
         WHERE ip_address = :ip AND endpoint = :ep AND window_start >= :ws
         LIMIT 1",
        ['ip' => $ip, 'ep' => $endpoint, 'ws' => $windowStart]
    );

    if ($row === null) {
        dbInsert('rate_limits', ['ip_address' => $ip, 'endpoint' => $endpoint, 'requests_count' => 1]);
        return true;
    }

    if ($row['requests_count'] >= $maxRequests) {
        return false;
    }

    dbQuery(
        "UPDATE rate_limits SET requests_count = requests_count + 1 WHERE id = :id",
        ['id' => $row['id']]
    );
    return true;
}

function rateLimitOrDie(string $endpoint, int $max, string $message = 'Слишком много запросов. Попробуйте позже.'): void {
    if (!checkRateLimit($endpoint, $max)) {
        http_response_code(429);
        if (isAjax()) {
            die(json_encode(['error' => $message]));
        }
        die($message);
    }
}

 
 
 

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo eJson($data);
    exit;
}

function redirect(string $url, int $code = 302): never {
    header("Location: $url", true, $code);
    exit;
}

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        redirect(APP_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Доступ запрещён.');
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        http_response_code(403);
        die('Недостаточно прав.');
    }
}

 
function validateUploadedImage(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Ошибка загрузки файла.'];
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['ok' => false, 'error' => 'Файл слишком большой (макс. 5 МБ).'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        return ['ok' => false, 'error' => 'Допустимы только JPEG, PNG, WebP.'];
    }
    return ['ok' => true];
}

function saveUploadedImage(array $file, string $subdir, string $prefix = ''): string|false {
    $check = validateUploadedImage($file);
    if (!$check['ok']) return false;

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
    $dir      = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return UPLOAD_URL . $subdir . '/' . $filename;
    }
    return false;
}
