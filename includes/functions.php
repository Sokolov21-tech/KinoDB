<?php
 
 
 

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

 
 
 

function appPublicPath(): string {
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    $path = rtrim((string)(parse_url(APP_URL, PHP_URL_PATH) ?: ''), '/');
    return $path;
}

function tmdbImageUrl(?string $url): string {
    $url = trim((string)$url);
    if ($url === '' || !TMDB_IMAGE_PROXY_ENABLED) {
        return $url;
    }

    $parts = parse_url($url);
    if (($parts['host'] ?? '') !== 'image.tmdb.org') {
        return $url;
    }

    $path = $parts['path'] ?? '';
    if (!preg_match('#^/t/p/([^/]+)/([^/]+)$#', $path, $matches)) {
        return $url;
    }

    $size = $matches[1];
    $file = $matches[2];
    $proxyUrl = TMDB_IMAGE_PROXY_URL ?: appPublicPath() . '/api/tmdb-image.php';
    $separator = str_contains($proxyUrl, '?') ? '&' : '?';
    return $proxyUrl . $separator . 'size=' . rawurlencode($size) . '&file=' . rawurlencode($file);
}

function tmdbImagePathUrl(string $size, ?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    return tmdbImageUrl(TMDB_IMAGE_URL . $size . '/' . ltrim($path, '/'));
}

 
 
 

function getMovie(int $id): ?array {
    $m = dbFetch("SELECT m.*, c.name AS country_name, c.code AS country_code
                  FROM movies m
                  LEFT JOIN countries c ON c.id = m.country_id
                  WHERE m.id = :id LIMIT 1", ['id' => $id]);
    if (!$m) return null;

    $m['genres']    = dbFetchAll("SELECT g.* FROM genres g JOIN movie_genres mg ON mg.genre_id = g.id WHERE mg.movie_id = :id", ['id' => $id]);
    $m['directors'] = dbFetchAll("SELECT d.* FROM directors d JOIN movie_directors md ON md.director_id = d.id WHERE md.movie_id = :id", ['id' => $id]);
    $m['actors']    = dbFetchAll("SELECT a.*, ma.character_name, ma.actor_order FROM actors a JOIN movie_actors ma ON ma.actor_id = a.id WHERE ma.movie_id = :id ORDER BY ma.actor_order", ['id' => $id]);
    $m['avg_rating'] = getMovieAvgRating($id);
    $m['rating_count'] = getMovieRatingCount($id);

     
    dbQuery("UPDATE movies SET views = views + 1 WHERE id = :id", ['id' => $id]);

    return $m;
}

function getMovieAvgRating(int $movieId): float {
    $row = dbFetch("SELECT AVG(rating) AS avg FROM ratings WHERE movie_id = :id", ['id' => $movieId]);
    return round((float)($row['avg'] ?? 0), 1);
}

function getMovieRatingCount(int $movieId): int {
    $row = dbFetch("SELECT COUNT(*) AS cnt FROM ratings WHERE movie_id = :id", ['id' => $movieId]);
    return (int)($row['cnt'] ?? 0);
}

function getMovieRatingDistribution(int $movieId): array {
    $dist = array_fill(1, 10, 0);
    $rows = dbFetchAll("SELECT rating, COUNT(*) AS cnt FROM ratings WHERE movie_id = :id GROUP BY rating", ['id' => $movieId]);
    foreach ($rows as $r) $dist[(int)$r['rating']] = (int)$r['cnt'];
    return $dist;
}

function getUserRating(int $userId, int $movieId): ?int {
    $row = dbFetch("SELECT rating FROM ratings WHERE user_id = :u AND movie_id = :m", ['u' => $userId, 'm' => $movieId]);
    return $row ? (int)$row['rating'] : null;
}

function searchMovies(array $filters, int $page = 1, int $perPage = 24): array {
    $where  = ['1=1'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[]        = "MATCH(m.title, m.original_title, m.description) AGAINST(:q IN BOOLEAN MODE)";
        $params['q']    = '+' . implode('* +', preg_split('/\s+/', trim($filters['q']))) . '*';
    }
    if (!empty($filters['genre'])) {
        $where[]          = "EXISTS (SELECT 1 FROM movie_genres mg JOIN genres g ON g.id = mg.genre_id WHERE mg.movie_id = m.id AND g.slug = :genre)";
        $params['genre']  = $filters['genre'];
    }
    if (!empty($filters['year_from'])) {
        $where[]            = "m.release_year >= :year_from";
        $params['year_from'] = (int)$filters['year_from'];
    }
    if (!empty($filters['year_to'])) {
        $where[]          = "m.release_year <= :year_to";
        $params['year_to'] = (int)$filters['year_to'];
    }
    if (!empty($filters['country'])) {
        $where[]          = "c.code = :country";
        $params['country'] = $filters['country'];
    }
    if (!empty($filters['rating_min'])) {
        $where[]              = "(SELECT AVG(r2.rating) FROM ratings r2 WHERE r2.movie_id = m.id) >= :rating_min";
        $params['rating_min'] = (float)$filters['rating_min'];
    }
    if (!empty($filters['status'])) {
        $where[]          = "m.status = :status";
        $params['status'] = $filters['status'];
    }

    $allowedSorts = ['release_year DESC','release_year ASC','views DESC','title ASC','kp_rating DESC'];
    $sort         = in_array($filters['sort'] ?? '', $allowedSorts) ? $filters['sort'] : 'release_year DESC';

    $whereStr = implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    $countRow = dbFetch(
        "SELECT COUNT(*) AS total FROM movies m LEFT JOIN countries c ON c.id = m.country_id WHERE $whereStr",
        $params
    );
    $total = (int)($countRow['total'] ?? 0);

    $movies = dbFetchAll(
        "SELECT m.id, m.title, m.release_year, m.poster_url, m.duration, m.kp_rating, m.status,
                (SELECT AVG(r.rating) FROM ratings r WHERE r.movie_id = m.id) AS avg_rating,
                (SELECT COUNT(r.id) FROM ratings r WHERE r.movie_id = m.id) AS rating_count,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS genre_names
         FROM movies m
         LEFT JOIN countries c ON c.id = m.country_id
         LEFT JOIN movie_genres mg ON mg.movie_id = m.id
         LEFT JOIN genres g ON g.id = mg.genre_id
         WHERE $whereStr
         GROUP BY m.id
         ORDER BY $sort
         LIMIT :limit OFFSET :offset",
        array_merge($params, ['limit' => $perPage, 'offset' => $offset])
    );

    return ['movies' => $movies, 'total' => $total, 'pages' => (int) ceil($total / $perPage)];
}

function getFeaturedMovies(int $limit = 5): array {
    return dbFetchAll(
        "SELECT m.*, (SELECT AVG(r.rating) FROM ratings r WHERE r.movie_id = m.id) AS avg_rating
         FROM movies m WHERE m.is_featured = 1 ORDER BY RAND() LIMIT :lim",
        ['lim' => $limit]
    );
}

function getLatestMovies(int $limit = 12): array {
    return dbFetchAll(
        "SELECT m.id, m.title, m.release_year, m.poster_url, m.kp_rating,
                (SELECT AVG(r.rating) FROM ratings r WHERE r.movie_id = m.id) AS avg_rating
         FROM movies m WHERE m.status = 'released' ORDER BY m.created_at DESC LIMIT :lim",
        ['lim' => $limit]
    );
}

function getTopMovies(int $limit = 10): array {
    return dbFetchAll(
        "SELECT m.id, m.title, m.release_year, m.poster_url,
                AVG(r.rating) AS avg_rating, COUNT(r.id) AS vote_count
         FROM movies m JOIN ratings r ON r.movie_id = m.id
         GROUP BY m.id HAVING vote_count >= 3
         ORDER BY avg_rating DESC, vote_count DESC LIMIT :lim",
        ['lim' => $limit]
    );
}

 
 
 

function getMovieComments(int $movieId, int $userId = 0): array {
    $rows = dbFetchAll(
        "SELECT c.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS likes,
                " . ($userId ? "(SELECT 1 FROM comment_likes cl2 WHERE cl2.comment_id = c.id AND cl2.user_id = :uid) AS liked" : "0 AS liked") . "
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.movie_id = :mid AND c.parent_id IS NULL AND c.is_deleted = 0
         ORDER BY c.created_at DESC",
        $userId ? ['mid' => $movieId, 'uid' => $userId] : ['mid' => $movieId]
    );

    foreach ($rows as &$c) {
        $c['replies'] = getCommentReplies((int)$c['id'], $userId);
    }
    return $rows;
}

function getCommentReplies(int $parentId, int $userId = 0): array {
    $rows = dbFetchAll(
        "SELECT c.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS likes,
                " . ($userId ? "(SELECT 1 FROM comment_likes cl2 WHERE cl2.comment_id = c.id AND cl2.user_id = :uid) AS liked" : "0 AS liked") . "
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.parent_id = :pid AND c.is_deleted = 0
         ORDER BY c.created_at ASC",
        $userId ? ['pid' => $parentId, 'uid' => $userId] : ['pid' => $parentId]
    );

    foreach ($rows as &$c) {
        $c['replies'] = getCommentReplies((int)$c['id'], $userId);
    }
    return $rows;
}

 
 
 

function getUserWatchlistStatus(int $userId, int $movieId): ?string {
    $row = dbFetch("SELECT type FROM watchlist WHERE user_id = :u AND movie_id = :m", ['u' => $userId, 'm' => $movieId]);
    return $row['type'] ?? null;
}

function getUserWatchlist(int $userId, string $type = 'want', int $page = 1, int $perPage = 24): array {
    $offset = ($page - 1) * $perPage;
    return dbFetchAll(
        "SELECT m.id, m.title, m.release_year, m.poster_url, m.kp_rating, wl.added_at,
                (SELECT AVG(r.rating) FROM ratings r WHERE r.movie_id = m.id) AS avg_rating
         FROM watchlist wl JOIN movies m ON m.id = wl.movie_id
         WHERE wl.user_id = :uid AND wl.type = :type
         ORDER BY wl.added_at DESC LIMIT :lim OFFSET :off",
        ['uid' => $userId, 'type' => $type, 'lim' => $perPage, 'off' => $offset]
    );
}

 
 
 

function getUserLists(int $userId): array {
    return dbFetchAll(
        "SELECT ul.*, COUNT(ulm.movie_id) AS movie_count,
                MIN(m.poster_url) AS cover_url
         FROM user_lists ul
         LEFT JOIN user_list_movies ulm ON ulm.list_id = ul.id
         LEFT JOIN movies m ON m.id = ulm.movie_id
         WHERE ul.user_id = :uid
         GROUP BY ul.id ORDER BY ul.updated_at DESC",
        ['uid' => $userId]
    );
}

 
 
 

function formatDuration(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}ч {$m}мин" : "{$m}мин";
}

function formatRating(float $rating): string {
    return number_format($rating, 1, '.', '');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    return match(true) {
        $diff < 60       => 'только что',
        $diff < 3600     => intdiv($diff, 60) . ' мин. назад',
        $diff < 86400    => intdiv($diff, 3600) . ' ч. назад',
        $diff < 2592000  => intdiv($diff, 86400) . ' дн. назад',
        $diff < 31536000 => intdiv($diff, 2592000) . ' мес. назад',
        default          => intdiv($diff, 31536000) . ' лет назад',
    };
}

function ratingColor(float $r): string {
    if ($r >= 7) return '#4CAF50';
    if ($r >= 5) return '#FFC107';
    return '#F44336';
}

function paginate(int $total, int $perPage, int $currentPage, string $urlPattern): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $url    = str_replace('{page}', $i, $urlPattern);
        $html  .= "<a href=\"$url\" class=\"page-btn$active\">$i</a>";
    }
    return $html . '</nav>';
}

function getAllGenres(): array {
    return dbFetchAll("SELECT * FROM genres ORDER BY name");
}

function getAllCountries(): array {
    return dbFetchAll("SELECT * FROM countries ORDER BY name");
}
