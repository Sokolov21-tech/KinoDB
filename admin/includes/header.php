<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

startSession();
requireAdmin();

$adminUser   = currentUser();
$publicBasePath = appPublicPath();
$adminTitle  = $adminTitle ?? 'Панель управления';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle) ?> — Admin — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Syncopate:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($publicBasePath) ?>/assets/css/main.css">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <script>window.APP_URL = <?= json_encode($publicBasePath) ?>;</script>
</head>
<body>
<div class="grain-overlay" aria-hidden="true"></div>

<div class="admin-layout">
    
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <a href="<?= APP_URL ?>/" class="logo">
                <span class="logo-icon">▶</span>
                <span class="logo-text">Kino<em>DB</em></span>
            </a>
            <div style="margin-top:0.5rem;font-family:var(--font-label);font-size:0.6rem;letter-spacing:0.1em;color:var(--amber);">ADMIN PANEL</div>
        </div>

        <?php
        $navItems = [
            ['file' => 'index.php',    'href' => APP_URL . '/admin/',              'label' => 'Дашборд',      'icon' => '◈'],
            ['file' => 'movies.php',   'href' => APP_URL . '/admin/movies.php',    'label' => 'Фильмы',       'icon' => '🎬'],
            ['file' => 'actors.php',   'href' => APP_URL . '/admin/actors.php',    'label' => 'Актёры',       'icon' => '👤'],
            ['file' => 'directors.php','href' => APP_URL . '/admin/directors.php', 'label' => 'Режиссёры',    'icon' => '🎥'],
            ['file' => 'genres.php',   'href' => APP_URL . '/admin/genres.php',    'label' => 'Жанры & Страны','icon' => '🏷'],
            ['file' => 'users.php',    'href' => APP_URL . '/admin/users.php',     'label' => 'Пользователи', 'icon' => '👥'],
            ['file' => 'comments.php', 'href' => APP_URL . '/admin/comments.php',  'label' => 'Комментарии',  'icon' => '💬'],
            ['file' => 'import.php',   'href' => APP_URL . '/admin/import.php',    'label' => 'Импорт КП',    'icon' => '↓'],
        ];
        $currentFile = basename($_SERVER['SCRIPT_FILENAME'] ?? 'index.php');
        foreach ($navItems as $item):
            $active = $currentFile === $item['file'];
        ?>
        <a href="<?= e($item['href']) ?>" class="admin-nav-item <?= $active ? 'active' : '' ?>">
            <span><?= $item['icon'] ?></span>
            <?= e($item['label']) ?>
        </a>
        <?php endforeach; ?>

        <div style="padding:var(--gap-xl) var(--gap-lg) var(--gap-sm);border-top:1px solid var(--border);margin-top:auto;">
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:var(--gap-sm);"><?= e($adminUser['username']) ?></div>
            <a href="<?= APP_URL ?>/" class="admin-nav-item">← На сайт</a>
            <a href="<?= APP_URL ?>/logout.php" class="admin-nav-item" style="color:var(--red-bright);">Выйти</a>
        </div>
    </aside>

    
    <div class="admin-main">
