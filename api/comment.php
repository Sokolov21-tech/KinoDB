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

rateLimitOrDie('api_comment', 20);

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonResponse(['error' => 'CSRF'], 403);
}

$action  = $body['action'] ?? '';
$userId  = (int)$_SESSION['user_id'];
$currentUser = currentUser();

 
 
 
if ($action === 'post') {
    $movieId   = sanitizeInt($body['movie_id'] ?? 0);
    $content   = sanitizeString($body['content'] ?? '', 2000);
    $isSpoiler = !empty($body['is_spoiler']) ? 1 : 0;

    if (!$movieId || !$content) {
        jsonResponse(['ok' => false, 'error' => 'Неверные данные'], 400);
    }
    if (!dbExists('movies', ['id' => $movieId])) {
        jsonResponse(['ok' => false, 'error' => 'Фильм не найден'], 404);
    }

    $id   = dbInsert('comments', [
        'user_id'    => $userId,
        'movie_id'   => $movieId,
        'content'    => $content,
        'is_spoiler' => $isSpoiler,
    ]);
    $comment = dbFetch(
        "SELECT c.*, u.username, u.avatar,
                0 AS likes,
                0 AS liked
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.id = :id",
        ['id' => $id]
    );
    $comment['replies'] = [];

    ob_start();
    include dirname(__DIR__) . '/includes/comment.php';
    $html = ob_get_clean();
    jsonResponse(['ok' => true, 'html' => $html, 'id' => $id]);
}

 
 
 
if ($action === 'reply') {
    $movieId  = sanitizeInt($body['movie_id'] ?? 0);
    $parentId = sanitizeInt($body['parent_id'] ?? 0);
    $content  = sanitizeString($body['content'] ?? '', 2000);

    if (!$movieId || !$content || !$parentId) {
        jsonResponse(['ok' => false, 'error' => 'Неверные данные'], 400);
    }
    if (!dbExists('movies', ['id' => $movieId])) {
        jsonResponse(['ok' => false, 'error' => 'Фильм не найден'], 404);
    }
    if (!dbExists('comments', ['id' => $parentId, 'movie_id' => $movieId, 'is_deleted' => 0])) {
        jsonResponse(['ok' => false, 'error' => 'Комментарий не найден'], 404);
    }

    $id   = dbInsert('comments', [
        'user_id'   => $userId,
        'movie_id'  => $movieId,
        'parent_id' => $parentId,
        'content'   => $content,
    ]);
    $comment = dbFetch(
        "SELECT c.*, u.username, u.avatar,
                0 AS likes,
                0 AS liked
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.id = :id",
        ['id' => $id]
    );
    $comment['replies'] = [];

    ob_start();
    include dirname(__DIR__) . '/includes/comment.php';
    $html = ob_get_clean();
    jsonResponse(['ok' => true, 'html' => $html, 'id' => $id]);
}

 
 
 
if ($action === 'like') {
    $commentId = sanitizeInt($body['comment_id'] ?? 0);
    if (!$commentId) jsonResponse(['ok' => false], 400);
    if (!dbExists('comments', ['id' => $commentId, 'is_deleted' => 0])) {
        jsonResponse(['ok' => false, 'error' => 'Комментарий не найден'], 404);
    }

    $existing = dbFetch("SELECT 1 FROM comment_likes WHERE user_id = :u AND comment_id = :c",
        ['u' => $userId, 'c' => $commentId]);

    if ($existing) {
        dbDelete('comment_likes', ['user_id' => $userId, 'comment_id' => $commentId]);
        dbQuery("UPDATE comments SET likes_count = GREATEST(0, likes_count - 1) WHERE id = :id", ['id' => $commentId]);
        $liked = false;
    } else {
        dbInsert('comment_likes', ['user_id' => $userId, 'comment_id' => $commentId]);
        dbQuery("UPDATE comments SET likes_count = likes_count + 1 WHERE id = :id", ['id' => $commentId]);
        $liked = true;
    }

    $row = dbFetch("SELECT likes_count FROM comments WHERE id = :id", ['id' => $commentId]);
    jsonResponse(['ok' => true, 'liked' => $liked, 'likes' => (int)($row['likes_count'] ?? 0)]);
}

 
 
 
if ($action === 'delete') {
    $commentId = sanitizeInt($body['comment_id'] ?? 0);
    $comment   = dbFetch("SELECT user_id FROM comments WHERE id = :id", ['id' => $commentId]);
    if (!$comment) jsonResponse(['ok' => false, 'error' => 'Не найдено'], 404);

    if ((int)$comment['user_id'] !== $userId && !isMod()) {
        jsonResponse(['ok' => false, 'error' => 'Нет прав'], 403);
    }

    dbUpdate('comments', ['is_deleted' => 1, 'content' => '[Удалено]'], ['id' => $commentId]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Неизвестный action'], 400);
