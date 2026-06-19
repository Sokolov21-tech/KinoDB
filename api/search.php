<?php
 
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if (!isAjax()) {
    http_response_code(403);
    die(eJson(['error' => 'AJAX only']));
}

rateLimitOrDie('api_search', RATE_LIMIT_MAX_API);

$q     = sanitizeString($_GET['q'] ?? '', 200);
$limit = min(20, max(1, sanitizeInt($_GET['limit'] ?? 6)));

if (strlen($q) < 2) {
    echo eJson(['movies' => []]);
    exit;
}

$like   = '%' . $q . '%';
$prefix = $q . '%';
$movies = dbFetchAll(
    "SELECT m.id, m.title, m.original_title, m.release_year, m.poster_url, m.kp_rating,
            (SELECT AVG(r.rating) FROM ratings r WHERE r.movie_id = m.id) AS avg_rating
     FROM movies m
     WHERE m.title LIKE :like_title OR m.original_title LIKE :like_original
     ORDER BY
        CASE
            WHEN m.title LIKE :prefix_title THEN 0
            WHEN m.original_title LIKE :prefix_original THEN 1
            ELSE 2
        END,
        m.views DESC,
        m.release_year DESC
     LIMIT :lim",
    [
        'like_title'      => $like,
        'like_original'   => $like,
        'prefix_title'    => $prefix,
        'prefix_original' => $prefix,
        'lim'             => $limit,
    ]
);

foreach ($movies as &$movie) {
    $movie['poster_url'] = tmdbImageUrl($movie['poster_url'] ?? '');
}
unset($movie);

echo eJson(['movies' => $movies]);
