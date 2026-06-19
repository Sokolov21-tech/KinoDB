<?php
require_once dirname(__DIR__) . '/includes/config.php';

$allowedSizes = [
    'w45' => true,
    'w92' => true,
    'w154' => true,
    'w185' => true,
    'w300' => true,
    'w342' => true,
    'w500' => true,
    'w780' => true,
    'w1280' => true,
    'h632' => true,
    'original' => true,
];

$size = (string)($_GET['size'] ?? '');
$file = rawurldecode((string)($_GET['file'] ?? $_GET['path'] ?? ''));
$file = ltrim($file, '/');

if (!isset($allowedSizes[$size]) || !tmdbProxyValidFile($file)) {
    tmdbProxyError(400, 'Bad image request');
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$contentType = tmdbProxyContentTypeFromExtension($ext);
$cacheDir = rtrim(TMDB_IMAGE_CACHE_DIR, "/\\") . DIRECTORY_SEPARATOR . $size;
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . sha1($size . '/' . $file) . '.' . $ext;
$cacheTtl = (int)TMDB_IMAGE_CACHE_TTL;

if (is_file($cacheFile) && filemtime($cacheFile) >= time() - $cacheTtl) {
    tmdbProxyServe($cacheFile, $contentType);
}

if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
    if (is_file($cacheFile)) {
        tmdbProxyServe($cacheFile, $contentType);
    }
    tmdbProxyError(500, 'Image cache is not writable');
}

$fetched = tmdbProxyFetch($size, $file, $cacheFile, $contentType);
if (!$fetched && is_file($cacheFile)) {
    tmdbProxyServe($cacheFile, $contentType);
}
if (!$fetched) {
    tmdbProxyError(502, 'Image upstream is unavailable');
}

tmdbProxyServe($cacheFile, $contentType);

function tmdbProxyValidFile(string $file): bool {
    if ($file === '' || strlen($file) > 200) {
        return false;
    }
    if (str_contains($file, "\0") || str_contains($file, '..') || str_contains($file, '\\') || str_contains($file, '/')) {
        return false;
    }
    return (bool)preg_match('/^[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$/i', $file);
}

function tmdbProxyFetch(string $size, string $file, string $cacheFile, string &$contentType): bool {
    $url = TMDB_IMAGE_URL . rawurlencode($size) . '/' . rawurlencode($file);
    $tmpFile = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $headers = [];

    $ok = function_exists('curl_init')
        ? tmdbProxyFetchWithCurl($url, $tmpFile, $headers)
        : tmdbProxyFetchWithStreams($url, $tmpFile, $headers);

    if (!$ok || !is_file($tmpFile)) {
        tmdbProxyRemoveTmp($tmpFile);
        return false;
    }

    $bytes = filesize($tmpFile);
    if (!$bytes || $bytes > TMDB_IMAGE_MAX_BYTES) {
        tmdbProxyRemoveTmp($tmpFile);
        return false;
    }

    $mime = strtolower(trim(explode(';', $headers['content-type'] ?? '')[0]));
    if (!$mime && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $tmpFile) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        tmdbProxyRemoveTmp($tmpFile);
        return false;
    }
    $contentType = $mime;

    if (is_file($cacheFile)) {
        @unlink($cacheFile);
    }
    if (!@rename($tmpFile, $cacheFile)) {
        tmdbProxyRemoveTmp($tmpFile);
        return false;
    }

    return true;
}

function tmdbProxyFetchWithCurl(string $url, string $tmpFile, array &$headers): bool {
    $fh = @fopen($tmpFile, 'wb');
    if (!$fh) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'KinoDB TMDB image proxy',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$headers): int {
            $length = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        },
    ]);

    $success = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fh);

    return $success === true && $status === 200;
}

function tmdbProxyFetchWithStreams(string $url, string $tmpFile, array &$headers): bool {
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'KinoDB TMDB image proxy',
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $data = @file_get_contents($url, false, $context);
    if ($data === false || strlen($data) > TMDB_IMAGE_MAX_BYTES) {
        return false;
    }

    $statusOk = false;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $statusOk = (int)$m[1] === 200;
        }
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }
    if (!$statusOk) {
        return false;
    }

    return file_put_contents($tmpFile, $data, LOCK_EX) !== false;
}

function tmdbProxyServe(string $file, string $contentType): never {
    $mtime = filemtime($file) ?: time();
    $etag = '"' . sha1($file . '|' . $mtime . '|' . filesize($file)) . '"';

    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=2592000, immutable');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('X-Content-Type-Options: nosniff');

    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }

    readfile($file);
    exit;
}

function tmdbProxyContentTypeFromExtension(string $ext): string {
    return match ($ext) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg',
    };
}

function tmdbProxyError(int $code, string $message): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

function tmdbProxyRemoveTmp(string $file): void {
    if (is_file($file)) {
        @unlink($file);
    }
}
