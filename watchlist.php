<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$currentUser = currentUser();
$userId      = $currentUser['id'];
$tab         = in_array($_GET['tab'] ?? '', ['want','watching','watched']) ? $_GET['tab'] : 'want';
$page        = max(1, sanitizeInt($_GET['page'] ?? 1));

$movies = getUserWatchlist($userId, $tab, $page);

$counts = dbFetch(
    "SELECT
         SUM(type = 'want')     AS want_count,
         SUM(type = 'watching') AS watching_count,
         SUM(type = 'watched')  AS watched_count
     FROM watchlist WHERE user_id = :uid",
    ['uid' => $userId]
);

$pageTitle = 'Мой список';
require_once 'includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-rule"></div>
        <h1 class="section-title mb-xl">Мой <em>список</em></h1>

        <div class="tabs">
            <a class="tab-btn <?= $tab === 'want'     ? 'active' : '' ?>" href="?tab=want">
                Хочу посмотреть <span class="badge badge--muted"><?= (int)$counts['want_count'] ?></span>
            </a>
            <a class="tab-btn <?= $tab === 'watching' ? 'active' : '' ?>" href="?tab=watching">
                Смотрю <span class="badge badge--muted"><?= (int)$counts['watching_count'] ?></span>
            </a>
            <a class="tab-btn <?= $tab === 'watched'  ? 'active' : '' ?>" href="?tab=watched">
                Просмотрено <span class="badge badge--muted"><?= (int)$counts['watched_count'] ?></span>
            </a>
        </div>

        <?php if (empty($movies)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <?= $tab === 'want' ? '🎬' : ($tab === 'watching' ? '▶' : '✓') ?>
            </div>
            <div class="empty-state-title">
                <?php if ($tab === 'want'): ?>Список желаний пуст
                <?php elseif ($tab === 'watching'): ?>Ничего в процессе просмотра
                <?php else: ?>Нет просмотренных фильмов<?php endif; ?>
            </div>
            <p style="margin:var(--gap) 0 var(--gap-xl);">
                <?= $tab === 'want' ? 'Добавляйте фильмы из карточек.' : 'Отметьте фильмы в своих списках.' ?>
            </p>
            <a href="<?= APP_URL ?>/search.php" class="btn btn--primary">Найти фильмы</a>
        </div>
        <?php else: ?>
        <div class="grid-movies">
            <?php foreach ($movies as $m): ?>
            <?php include 'includes/movie-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
