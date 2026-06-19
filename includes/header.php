<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';

startSession();

$currentUser = currentUser();
$genres      = getAllGenres();

$pageTitle       = $pageTitle       ?? APP_NAME;
$pageDescription = $pageDescription ?? 'Платформа для любителей кино — рейтинги, рецензии, коллекции фильмов';
$bodyClass       = $bodyClass       ?? '';
$publicBasePath  = rtrim((string)(parse_url(APP_URL, PHP_URL_PATH) ?: ''), '/');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Syncopate:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>

    
    <link rel="stylesheet" href="<?= e($publicBasePath) ?>/assets/css/main.css?v=<?= filemtime(ROOT_PATH . '/assets/css/main.css') ?>">

    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">

    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <script>window.APP_URL = <?= json_encode($publicBasePath) ?>;</script>

    <?= $extraHead ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">


<div class="grain-overlay" aria-hidden="true"></div>


<header class="site-header" id="site-header">
    <div class="header-inner">
        
        <a href="<?= APP_URL ?>/" class="logo" aria-label="<?= APP_NAME ?> — Главная">
            <span class="logo-icon">▶</span>
            <span class="logo-text">Kino<em>DB</em></span>
        </a>

        
        <nav class="primary-nav" aria-label="Основная навигация">
            <a href="<?= APP_URL ?>/" class="nav-link">Главная</a>
            <div class="nav-dropdown">
                <a href="<?= APP_URL ?>/search.php" class="nav-link">Фильмы <span class="nav-arrow">▾</span></a>
                <div class="nav-dropdown-menu">
                    <?php foreach ($genres as $g): ?>
                    <a href="<?= APP_URL ?>/search.php?genre=<?= e($g['slug']) ?>" class="dropdown-item">
                        <?= e($g['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="<?= APP_URL ?>/search.php?sort=views+DESC" class="nav-link">Топ фильмов</a>
            <a href="<?= APP_URL ?>/search.php?status=upcoming" class="nav-link">Скоро</a>
        </nav>

        
        <form class="header-search" action="<?= APP_URL ?>/search.php" method="GET" role="search">
            <input type="search" name="q" placeholder="Поиск фильмов, актёров..." class="search-input"
                   aria-label="Поиск" autocomplete="off" id="header-search-input"
                   data-search-suggest data-suggestions-target="search-suggestions"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="search-btn" aria-label="Искать">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </button>
            <div class="search-suggestions" id="search-suggestions" aria-live="polite"></div>
        </form>

        
        <div class="user-menu">
            <?php if ($currentUser): ?>
            <div class="user-dropdown">
                <button class="user-trigger" aria-expanded="false" aria-haspopup="true">
                    <?php if ($currentUser['avatar']): ?>
                    <img src="<?= e($currentUser['avatar']) ?>" alt="Аватар" class="user-avatar-sm">
                    <?php else: ?>
                    <span class="user-avatar-placeholder"><?= mb_strtoupper(mb_substr($currentUser['username'], 0, 1)) ?></span>
                    <?php endif; ?>
                    <span class="user-name"><?= e($currentUser['username']) ?></span>
                    <span class="nav-arrow">▾</span>
                </button>
                <div class="user-dropdown-menu" aria-label="Меню пользователя">
                    <a href="<?= APP_URL ?>/profile.php" class="dropdown-item">Профиль</a>
                    <a href="<?= APP_URL ?>/watchlist.php" class="dropdown-item">Мой список</a>
                    <a href="<?= APP_URL ?>/lists.php" class="dropdown-item">Коллекции</a>
                    <?php if (isAdmin() || isMod()): ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?= APP_URL ?>/admin/" class="dropdown-item dropdown-item--accent">Панель администратора</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?= APP_URL ?>/logout.php" class="dropdown-item dropdown-item--danger">Выйти</a>
                </div>
            </div>
            <?php else: ?>
            <a href="<?= APP_URL ?>/login.php" class="btn btn--ghost btn--sm">Войти</a>
            <a href="<?= APP_URL ?>/register.php" class="btn btn--primary btn--sm">Регистрация</a>
            <?php endif; ?>
        </div>

        
        <button class="burger" aria-label="Меню" aria-expanded="false" id="burger-btn">
            <span></span><span></span><span></span>
        </button>
    </div>

    
    <nav class="mobile-nav" id="mobile-nav" aria-hidden="true">
        <a href="<?= APP_URL ?>/" class="mobile-nav-link">Главная</a>
        <a href="<?= APP_URL ?>/search.php" class="mobile-nav-link">Все фильмы</a>
        <a href="<?= APP_URL ?>/search.php?sort=views+DESC" class="mobile-nav-link">Топ фильмов</a>
        <?php if ($currentUser): ?>
        <a href="<?= APP_URL ?>/watchlist.php" class="mobile-nav-link">Мой список</a>
        <a href="<?= APP_URL ?>/profile.php" class="mobile-nav-link">Профиль</a>
        <a href="<?= APP_URL ?>/logout.php" class="mobile-nav-link">Выйти</a>
        <?php else: ?>
        <a href="<?= APP_URL ?>/login.php" class="mobile-nav-link">Войти</a>
        <a href="<?= APP_URL ?>/register.php" class="mobile-nav-link">Регистрация</a>
        <?php endif; ?>
    </nav>
</header>


<?php if (!empty($_SESSION['flash'])): ?>
<div class="flash-container" id="flash-container">
    <?php foreach ($_SESSION['flash'] as $flash): ?>
    <div class="flash flash--<?= e($flash['type']) ?>" role="alert">
        <?= e($flash['message']) ?>
        <button class="flash-close" aria-label="Закрыть">×</button>
    </div>
    <?php endforeach; ?>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<main id="main-content">
