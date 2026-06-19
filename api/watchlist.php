<?php
 
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if (!isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Bad request'], 400);
}

if (!isLoggedIn()) {
    jsonResponse(['ok' => false, 'login' => true], 401);
}

rateLimitOrDie('api_watchlist', RATE_LIMIT_MAX_API);

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonResponse(['error' => 'CSRF'], 403);
}

$userId  = (int)$_SESSION['user_id'];
$movieId = sanitizeInt($body['movie_id'] ?? 0);
$type    = in_array($body['type'] ?? '', ['want','watching','watched']) ? $body['type'] : 'want';

if (!$movieId || !dbExists('movies', ['id' => $movieId])) {
    jsonResponse(['ok' => false, 'error' => 'Фильм не найден'], 404);
}

$existing = dbFetch("SELECT id, type FROM watchlist WHERE user_id = :u AND movie_id = :m",
    ['u' => $userId, 'm' => $movieId]);

if ($existing) {
    if ($existing['type'] === $type) {
         
        dbDelete('watchlist', ['id' => $existing['id']]);
        $added   = false;
        $message = 'Удалено из списка';
    } else {
         
        $watchedAt = $type === 'watched' ? date('Y-m-d H:i:s') : null;
        dbUpdate('watchlist', ['type' => $type, 'watched_at' => $watchedAt], ['id' => $existing['id']]);
        $added   = true;
        $message = $type === 'want' ? 'Добавлено в желаемое' : ($type === 'watched' ? 'Отмечено как просмотренное' : 'Добавлено в «смотрю»');
    }
} else {
    $watchedAt = $type === 'watched' ? date('Y-m-d H:i:s') : null;
    dbInsert('watchlist', ['user_id' => $userId, 'movie_id' => $movieId, 'type' => $type, 'watched_at' => $watchedAt]);
    $added   = true;
    $message = $type === 'want' ? 'Добавлено в желаемое' : ($type === 'watched' ? 'Отмечено как просмотренное' : 'Добавлено в «смотрю»');
}

 
$counts = dbFetch(
    "SELECT SUM(type='want') AS want, SUM(type='watching') AS watching, SUM(type='watched') AS watched
     FROM watchlist WHERE user_id = :uid",
    ['uid' => $userId]
);

jsonResponse([
    'ok'      => true,
    'added'   => $added,
    'type'    => $type,
    'message' => $message,
    'counts'  => $counts,
]);
