<?php
// Импорт фильмов из ПоискКино (https://poiskkino.dev) — API в формате Кинопоиска.
// Поиск:  GET  ?action=search&q=название
// Импорт: POST {kp_id: <ID фильма на Кинопоиске>}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/security.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if (!isAjax()) {

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        requireAdmin();
    } else {
        http_response_code(403);
        die(eJson(['error' => 'AJAX only']));
    }
}

requireAdmin();
rateLimitOrDie('api_import', 30);


if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search') {
    $q = sanitizeString($_GET['q'] ?? '', 200);
    if (!$q) jsonResponse(['results' => []]);

    $data = kpRequest('/movie/search', ['query' => $q, 'limit' => 8, 'page' => 1]);
    if ($data === null) {
        jsonResponse(['results' => [], 'error' => kpLastError()]);
    }

    $results = [];
    foreach ($data['docs'] ?? [] as $doc) {
        $results[] = [
            'id'             => $doc['id'] ?? 0,
            'title'          => $doc['name'] ?: ($doc['alternativeName'] ?? ''),
            'original_title' => $doc['alternativeName'] ?? '',
            'year'           => $doc['year'] ?? null,
            'poster'         => $doc['poster']['previewUrl'] ?? ($doc['poster']['url'] ?? ''),
            'rating'         => $doc['rating']['kp'] ?? null,
        ];
    }
    jsonResponse(['results' => $results]);
}


$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonResponse(['error' => 'CSRF'], 403);
}

$kpId = sanitizeInt($body['kp_id'] ?? $body['tmdb_id'] ?? 0);
if (!$kpId) jsonResponse(['ok' => false, 'error' => 'Kinopoisk ID обязателен'], 400);

if (!KP_API_KEY) {
    jsonResponse(['ok' => false, 'error' => 'KP_API_KEY не настроен в config.php']);
}

// Колонка kinopoisk_id появляется после database/migration-kinopoisk.sql
if (!dbFetch("SHOW COLUMNS FROM movies LIKE 'kinopoisk_id'")) {
    jsonResponse(['ok' => false, 'error' => 'Нет колонки kinopoisk_id — выполните database/migration-kinopoisk.sql']);
}

$existing = dbFetch("SELECT id, title FROM movies WHERE kinopoisk_id = :id", ['id' => $kpId]);
if ($existing) {
    jsonResponse(['ok' => false, 'error' => "Уже импортирован: {$existing['title']} (ID {$existing['id']})"]);
}


$movie = kpRequest("/movie/$kpId");
if (!$movie || empty($movie['id'])) {
    $reason = kpLastError();
    if ($reason) {
        jsonResponse(['ok' => false, 'error' => 'ПоискКино не вернул фильм: ' . $reason]);
    }
    jsonResponse(['ok' => false, 'error' => 'Фильм не найден в ПоискКино']);
}

// Любая SQL-ошибка (гонка двойного клика, дубликат и т.п.) должна вернуть JSON,
// а не HTML-фатал, который фронтенд показывает как «Ошибка сети».
try {

$countryId   = null;
$countryName = $movie['countries'][0]['name'] ?? '';
if ($countryName !== '') {
    $c = dbFetch("SELECT id FROM countries WHERE name = :name", ['name' => $countryName]);
    if ($c) {
        $countryId = $c['id'];
    } else {
        $code = kpCountryCode($countryName);
        if ($code !== '') {
            $byCode = dbFetch("SELECT id FROM countries WHERE code = :code", ['code' => $code]);
            $countryId = $byCode
                ? $byCode['id']
                : dbInsert('countries', ['name' => $countryName, 'code' => $code]);
        }
    }
}

$releaseDate = kpDate($movie['premiere']['world'] ?? ($movie['premiere']['russia'] ?? ''));
$trailerUrl  = null;
foreach ($movie['videos']['trailers'] ?? [] as $trailer) {
    if (!empty($trailer['url'])) { $trailerUrl = $trailer['url']; break; }
}

$movieId = dbInsert('movies', [
    'title'          => $movie['name'] ?: ($movie['alternativeName'] ?? ''),
    'original_title' => $movie['alternativeName'] ?: null,
    'description'    => $movie['description'] ?: ($movie['shortDescription'] ?? null),
    'release_year'   => $movie['year'] ?: null,
    'release_date'   => $releaseDate,
    'duration'       => $movie['movieLength'] ?: null,
    'poster_url'     => $movie['poster']['url']   ?? null,
    'backdrop_url'   => $movie['backdrop']['url'] ?? null,
    'trailer_url'    => $trailerUrl,
    'country_id'     => $countryId,
    'imdb_id'        => $movie['externalId']['imdb'] ?? null,
    'tmdb_id'        => $movie['externalId']['tmdb'] ?? null,
    'kinopoisk_id'   => $kpId,
    'kp_rating'      => isset($movie['rating']['kp']) && $movie['rating']['kp'] > 0
                            ? round((float)$movie['rating']['kp'], 1) : null,
    'age_rating'     => isset($movie['ageRating']) && $movie['ageRating'] !== null
                            ? $movie['ageRating'] . '+'
                            : (!empty($movie['ratingMpaa']) ? strtoupper($movie['ratingMpaa']) : null),
    'language'       => null,
    'status'         => kpMapStatus($movie['status'] ?? ''),
    'budget'         => ($movie['budget']['value'] ?? 0) ?: null,
    'revenue'        => ($movie['fees']['world']['value'] ?? 0) ?: null,
]);


foreach ($movie['genres'] ?? [] as $g) {
    $name = trim((string)($g['name'] ?? ''));
    if ($name === '') continue;
    $slug  = kpSlug($name);
    $genre = dbFetch("SELECT id FROM genres WHERE name = :name OR slug = :slug", ['name' => $name, 'slug' => $slug]);
    $gid = $genre ? $genre['id'] : dbInsert('genres', ['name' => $name, 'slug' => $slug]);
    if (!dbExists('movie_genres', ['movie_id' => $movieId, 'genre_id' => $gid])) {
        dbInsert('movie_genres', ['movie_id' => $movieId, 'genre_id' => $gid]);
    }
}


$castImported = 0;
foreach ($movie['persons'] ?? [] as $person) {
    $personId = (int)($person['id'] ?? 0);
    $name     = trim((string)($person['name'] ?: ($person['enName'] ?? '')));
    if (!$personId || $name === '') continue;

    if (($person['enProfession'] ?? '') === 'director') {
        $dir = dbFetch("SELECT id FROM directors WHERE kinopoisk_id = :kid", ['kid' => $personId]);
        $dirId = $dir ? $dir['id'] : dbInsert('directors', [
            'name'          => $name,
            'original_name' => $person['enName'] ?: null,
            'photo_url'     => $person['photo'] ?: null,
            'kinopoisk_id'  => $personId,
        ]);
        if (!dbExists('movie_directors', ['movie_id' => $movieId, 'director_id' => $dirId])) {
            dbInsert('movie_directors', ['movie_id' => $movieId, 'director_id' => $dirId]);
        }
        continue;
    }

    if (($person['enProfession'] ?? '') === 'actor' && $castImported < 10) {
        $actor = dbFetch("SELECT id FROM actors WHERE kinopoisk_id = :kid", ['kid' => $personId]);
        $actorId = $actor ? $actor['id'] : dbInsert('actors', [
            'name'          => $name,
            'original_name' => $person['enName'] ?: null,
            'photo_url'     => $person['photo'] ?: null,
            'kinopoisk_id'  => $personId,
        ]);
        if (!dbExists('movie_actors', ['movie_id' => $movieId, 'actor_id' => $actorId])) {
            dbInsert('movie_actors', [
                'movie_id'       => $movieId,
                'actor_id'       => $actorId,
                'character_name' => sanitizeString($person['description'] ?? '', 200),
                'actor_order'    => $castImported,
            ]);
            $castImported++;
        }
    }
}

jsonResponse(['ok' => true, 'id' => $movieId, 'title' => $movie['name'] ?: ($movie['alternativeName'] ?? '')]);

} catch (Throwable $e) {
    error_log('[Import] ' . $e->getMessage());
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        jsonResponse(['ok' => false, 'error' => 'Фильм уже есть в базе (дубликат)']);
    }
    jsonResponse(['ok' => false, 'error' => 'Ошибка импорта: ' . $e->getMessage()], 500);
}


function kpRequest(string $endpoint, array $params = []): ?array {
    $GLOBALS['kp_last_error'] = '';

    $url = rtrim(KP_API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    [$status, $raw, $transportError] = kpHttpGet($url);

    if ($raw === null || $raw === '') {
        $GLOBALS['kp_last_error'] = $transportError ?: 'empty response';
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $GLOBALS['kp_last_error'] = 'invalid JSON response';
        return null;
    }

    if ($status < 200 || $status >= 300) {
        $message = $data['message'] ?? ('HTTP ' . $status);
        if (is_array($message)) $message = implode('; ', $message);
        $GLOBALS['kp_last_error'] = $message . ' (HTTP ' . $status . ')';
        return null;
    }

    return $data;
}

function kpHttpGet(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'KinoDB importer',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . KP_API_KEY,
                'Accept: application/json',
            ],
        ]);

        $raw    = curl_exec($ch);
        $error  = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, $raw === false ? null : $raw, $error];
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 20,
            'ignore_errors' => true,
            'user_agent'    => 'KinoDB importer',
            'header'        => "X-API-KEY: " . KP_API_KEY . "\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int)$m[1];
        }
    }

    return [$status, $raw === false ? null : $raw, $raw === false ? 'file_get_contents failed' : ''];
}

function kpLastError(): string {
    return (string)($GLOBALS['kp_last_error'] ?? '');
}

// '1999-03-24T00:00:00.000Z' -> '1999-03-24'
function kpDate(string $iso): ?string {
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $iso, $m)) {
        return $m[1];
    }
    return null;
}

function kpMapStatus(string $status): string {
    return match ($status) {
        'announced'                                    => 'upcoming',
        'filming', 'pre-production', 'post-production' => 'in_production',
        default                                        => 'released',
    };
}

// Слаг из русского названия жанра: «научная фантастика» -> «nauchnaya-fantastika»
function kpSlug(string $name): string {
    static $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c',
        'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $slug = strtr(mb_strtolower($name, 'UTF-8'), $map);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'genre';
}

// ISO-код для русских названий стран от Кинопоиска (countries.code CHAR(2) NOT NULL).
function kpCountryCode(string $name): string {
    static $map = [
        'США' => 'US', 'Россия' => 'RU', 'СССР' => 'SU', 'Великобритания' => 'GB',
        'Франция' => 'FR', 'Германия' => 'DE', 'Италия' => 'IT', 'Испания' => 'ES',
        'Япония' => 'JP', 'Южная Корея' => 'KR', 'Корея Южная' => 'KR', 'Китай' => 'CN',
        'Индия' => 'IN', 'Канада' => 'CA', 'Австралия' => 'AU', 'Новая Зеландия' => 'NZ',
        'Мексика' => 'MX', 'Бразилия' => 'BR', 'Аргентина' => 'AR', 'Швеция' => 'SE',
        'Норвегия' => 'NO', 'Дания' => 'DK', 'Финляндия' => 'FI', 'Польша' => 'PL',
        'Чехия' => 'CZ', 'Австрия' => 'AT', 'Швейцария' => 'CH', 'Бельгия' => 'BE',
        'Нидерланды' => 'NL', 'Ирландия' => 'IE', 'Португалия' => 'PT', 'Греция' => 'GR',
        'Турция' => 'TR', 'Израиль' => 'IL', 'Иран' => 'IR', 'Казахстан' => 'KZ',
        'Украина' => 'UA', 'Беларусь' => 'BY', 'Грузия' => 'GE', 'Армения' => 'AM',
        'Венгрия' => 'HU', 'Румыния' => 'RO', 'Болгария' => 'BG', 'Сербия' => 'RS',
        'Хорватия' => 'HR', 'Исландия' => 'IS', 'Таиланд' => 'TH', 'Вьетнам' => 'VN',
        'Индонезия' => 'ID', 'Филиппины' => 'PH', 'Малайзия' => 'MY', 'Сингапур' => 'SG',
        'Гонконг' => 'HK', 'Тайвань' => 'TW', 'ЮАР' => 'ZA', 'Египет' => 'EG',
        'Колумбия' => 'CO', 'Чили' => 'CL', 'Перу' => 'PE', 'Куба' => 'CU',
    ];
    return $map[$name] ?? '';
}
