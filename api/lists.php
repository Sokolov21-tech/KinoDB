<?php
 
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/security.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if (!isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Bad request'], 400);
}

if (!isLoggedIn()) {
    jsonResponse(['ok' => false, 'login' => true], 401);
}

rateLimitOrDie('api_lists', RATE_LIMIT_MAX_API);

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonResponse(['error' => 'CSRF'], 403);
}

$userId  = (int)$_SESSION['user_id'];
$action  = $body['action'] ?? '';
$listId  = sanitizeInt($body['list_id']  ?? 0);
$movieId = sanitizeInt($body['movie_id'] ?? 0);

if (in_array($action, ['add', 'remove', 'reorder'], true) && !$listId) {
    jsonResponse(['ok' => false, 'error' => 'Коллекция не выбрана'], 400);
}

 
if ($listId) {
    $list = dbFetch("SELECT id FROM user_lists WHERE id = :id AND user_id = :uid", ['id' => $listId, 'uid' => $userId]);
    if (!$list) jsonResponse(['ok' => false, 'error' => 'Список не найден'], 404);
}

if ($action === 'add') {
    if (!$movieId || !dbExists('movies', ['id' => $movieId])) {
        jsonResponse(['ok' => false, 'error' => 'Фильм не найден'], 404);
    }
    if (dbExists('user_list_movies', ['list_id' => $listId, 'movie_id' => $movieId])) {
        jsonResponse(['ok' => false, 'error' => 'Фильм уже в списке']);
    }
    $note = sanitizeString($body['note'] ?? '', 500);
    dbInsert('user_list_movies', ['list_id' => $listId, 'movie_id' => $movieId, 'note' => $note]);
    dbUpdate('user_lists', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $listId]);
    $count = dbFetch("SELECT COUNT(*) AS cnt FROM user_list_movies WHERE list_id = :id", ['id' => $listId]);
    jsonResponse(['ok' => true, 'message' => 'Фильм добавлен в список', 'count' => $count['cnt']]);
}

if ($action === 'remove') {
    dbQuery("DELETE FROM user_list_movies WHERE list_id = :lid AND movie_id = :mid",
        ['lid' => $listId, 'mid' => $movieId]);
    jsonResponse(['ok' => true, 'message' => 'Фильм удалён из списка']);
}

if ($action === 'reorder') {
    $order = $body['order'] ?? [];  
    foreach ($order as $pos => $mid) {
        $mid = (int)$mid;
        if ($mid) {
            dbQuery("UPDATE user_list_movies SET order_pos = :pos WHERE list_id = :lid AND movie_id = :mid",
                ['pos' => $pos, 'lid' => $listId, 'mid' => $mid]);
        }
    }
    jsonResponse(['ok' => true]);
}

 
if ($action === 'get_user_lists') {
    $lists = dbFetchAll(
        "SELECT ul.id, ul.title, EXISTS(SELECT 1 FROM user_list_movies WHERE list_id = ul.id AND movie_id = :mid) AS has_movie
         FROM user_lists ul WHERE ul.user_id = :uid ORDER BY ul.updated_at DESC",
        ['uid' => $userId, 'mid' => $movieId]
    );
    jsonResponse(['ok' => true, 'lists' => $lists]);
}

jsonResponse(['error' => 'Неизвестный action'], 400);
