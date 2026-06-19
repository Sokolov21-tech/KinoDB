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

rateLimitOrDie('api_rate', 30);

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$token   = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonResponse(['error' => 'CSRF'], 403);
}

$movieId = sanitizeInt($body['movie_id'] ?? 0);
$rating  = sanitizeInt($body['rating']   ?? 0);

if (!$movieId || $rating < 1 || $rating > 10) {
    jsonResponse(['error' => 'Некорректные данные'], 400);
}

if (!dbExists('movies', ['id' => $movieId])) {
    jsonResponse(['error' => 'Фильм не найден'], 404);
}

$userId = (int)$_SESSION['user_id'];

 
$existing = dbFetch("SELECT id FROM ratings WHERE user_id = :u AND movie_id = :m", ['u' => $userId, 'm' => $movieId]);
if ($existing) {
    dbUpdate('ratings', ['rating' => $rating], ['id' => $existing['id']]);
} else {
    dbInsert('ratings', ['user_id' => $userId, 'movie_id' => $movieId, 'rating' => $rating]);
}

$avg = getMovieAvgRating($movieId);
$cnt = getMovieRatingCount($movieId);

jsonResponse([
    'ok'           => true,
    'rating'       => $rating,
    'avg_rating'   => formatRating($avg),
    'rating_count' => $cnt,
]);
