<?php
// ---- База данных --------------------------------------------------
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_NAME',     'kino_db');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ---- Настройки сайта -----------------------------------------------
function detectAppUrl(): string {
    $envUrl = getenv('APP_URL');
    if ($envUrl !== false && trim($envUrl) !== '') {
        return rtrim($envUrl, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $scriptDir = trim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($scriptDir === '.') {
        $scriptDir = '';
    }

    $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $rootPath = str_replace('\\', '/', dirname(__DIR__));
    $relativeDir = '';

    if ($scriptFile !== '') {
        $fileDir = str_replace('\\', '/', dirname($scriptFile));
        if (stripos($fileDir, $rootPath) === 0) {
            $relativeDir = trim(substr($fileDir, strlen($rootPath)), '/');
        }
    }

    if ($relativeDir !== '') {
        $scriptDirLower = strtolower($scriptDir);
        $relativeDirLower = strtolower($relativeDir);

        if ($scriptDirLower === $relativeDirLower) {
            $scriptDir = '';
        } elseif (str_ends_with($scriptDirLower, '/' . $relativeDirLower)) {
            $scriptDir = substr($scriptDir, 0, -strlen('/' . $relativeDir));
        }

        $scriptDir = trim($scriptDir, '/');
    }

    return $scheme . '://' . $host . ($scriptDir !== '' ? '/' . $scriptDir : '');
}

define('APP_NAME',    'KinoDB');
define('APP_URL',     detectAppUrl());
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'development'); // 'development' | 'production'

// ---- Сессия ---------------------------------------------------
define('SESSION_LIFETIME',   86400);     // в секундах
define('SESSION_COOKIE_NAME', 'kino_sess');

// ---- Безопасность --------------------------------------------------
define('CSRF_TOKEN_LENGTH',    32);
define('PASSWORD_MIN_LENGTH',   8);
define('LOGIN_MAX_ATTEMPTS',    5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('TOKEN_EXPIRY_HOURS',   24);      // верификация почты / смена пароля

// ---- Загрузка файлов -----------------------------------------------
define('UPLOAD_MAX_SIZE',   5 * 1024 * 1024);   // 5 MB
define('UPLOAD_DIR',        __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',        APP_URL . '/assets/uploads/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/webp']);

// ---- ПоискКино API (импорт фильмов) ---------------------------------
// Документация: https://poiskkino.dev/documentation
define('KP_API_KEY',      getenv('KP_API_KEY') ?: '6S67YZW-2YK4FP6-G883R5W-BT0PQGX');
define('KP_API_BASE_URL', getenv('KP_API_BASE_URL') ?: 'https://api.poiskkino.dev/v1.4');

// ---- TMDB images (legacy) -------------------------------------------
// Новые импорты используют прямые URL постеров с CDN Кинопоиска.
// Эти константы нужны только для картинок старых фильмов, импортированных из TMDB:
// они продолжают ходить через локальный кеширующий прокси api/tmdb-image.php.
define('TMDB_IMAGE_URL',   'https://image.tmdb.org/t/p/');
$tmdbImageProxyEnv = getenv('TMDB_IMAGE_PROXY_ENABLED');
define('TMDB_IMAGE_PROXY_ENABLED', $tmdbImageProxyEnv === false ? true : filter_var($tmdbImageProxyEnv, FILTER_VALIDATE_BOOLEAN));
define('TMDB_IMAGE_PROXY_URL', ''); // пусто = локальный api/tmdb-image.php

// ---- OMDB API ----------------------------------------
define('OMDB_API_KEY',     'YOUR_OMDB_API_KEY');
define('OMDB_BASE_URL',    'https://www.omdbapi.com/');

// ---- Email / SMTP ----------------------------------------------
// Порт 465 = неявный SSL (mailer подключается через ssl:// без STARTTLS).
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',        465);
define('MAIL_USERNAME',   'sokol4829@gmail.com');
define('MAIL_PASSWORD',   'royoirvhutuajsld'); // app password (без пробелов)
// Gmail подменяет From на адрес аккаунта — указываем его же, чтобы письма не попадали в спам.
define('MAIL_FROM_EMAIL', 'sokol4829@gmail.com');
define('MAIL_FROM_NAME',  'KinoDB');
define('MAIL_USE_TLS',    true);
// false = реальная отправка через SMTP; true = письма пишутся в logs/mail.log
define('MAIL_DEBUG',      filter_var(getenv('MAIL_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// ---- Минимальная защита от DDOS ---------------------------
define('RATE_LIMIT_WINDOW',   60);      // seconds
define('RATE_LIMIT_MAX_API',  60);      // API requests per window
define('RATE_LIMIT_MAX_AUTH', 10);      // auth attempts per window
define('RATE_LIMIT_MAX_PAGE', 120);     // page requests per window

// ---- Пути -----------------------------------------------------
define('ROOT_PATH',   dirname(__DIR__));
define('LOG_PATH',    ROOT_PATH . '/logs/');
define('CACHE_PATH',  ROOT_PATH . '/cache/');
define('TMDB_IMAGE_CACHE_DIR', CACHE_PATH . 'tmdb-images/');
define('TMDB_IMAGE_CACHE_TTL', 30 * 24 * 60 * 60); // 30 days
define('TMDB_IMAGE_MAX_BYTES', 15 * 1024 * 1024);  // 15 MB

// ---- Запуск ---------------------------------------------------
date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Логи
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0750, true);
}
