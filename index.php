<?php
$pageTitle       = 'Главная';
$pageDescription = 'Лучшие фильмы, рейтинги и обзоры на KinoDB — платформе для настоящих киноманов';

require_once 'includes/header.php';

$featured = getFeaturedMovies(5);
$latest   = getLatestMovies(12);
$top      = getTopMovies(10);
?>


<section class="hero" aria-label="Избранные фильмы">
    <?php foreach ($featured as $i => $m): ?>
    <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" data-id="<?= $m['id'] ?>">
        <?php if ($m['backdrop_url']): ?>
        <div class="hero-bg" style="background-image:url('<?= e(tmdbImageUrl($m['backdrop_url'])) ?>')"></div>
        <?php else: ?>
        <div class="hero-bg" style="background:var(--noir-3)"></div>
        <?php endif; ?>
        <div class="hero-vignette"></div>

        <div class="hero-content">
            <span class="hero-label">⬤ РЕКОМЕНДУЕМ</span>
            <h1 class="hero-title"><?= e($m['title']) ?></h1>
            <div class="hero-meta">
                <?php if ($m['avg_rating'] > 0): ?>
                <div class="hero-rating">
                    <span>★</span>
                    <span><?= formatRating($m['avg_rating']) ?></span>
                </div>
                <?php elseif ($m['kp_rating']): ?>
                <div class="hero-rating">
                    <span>★</span>
                    <span><?= $m['kp_rating'] ?></span>
                </div>
                <?php endif; ?>
                <?php if ($m['release_year']): ?>
                <span class="hero-year"><?= $m['release_year'] ?></span>
                <?php endif; ?>
                <?php if ($m['duration']): ?>
                <span class="hero-duration"><?= formatDuration($m['duration']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($m['description']): ?>
            <p class="hero-description"><?= e($m['description']) ?></p>
            <?php endif; ?>
            <div class="hero-actions">
                <a href="<?= APP_URL ?>/movie.php?id=<?= $m['id'] ?>" class="btn btn--primary btn--lg">
                    Смотреть детали
                </a>
                <?php if ($currentUser): ?>
                <button class="btn btn--ghost btn--lg" data-action="watchlist"
                        data-movie-id="<?= $m['id'] ?>" data-type="want">
                    + Хочу посмотреть
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="hero-dots" aria-label="Переключение слайдов">
        <?php foreach ($featured as $i => $m): ?>
        <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>" aria-label="Слайд <?= $i+1 ?>"></button>
        <?php endforeach; ?>
    </div>
</section>


<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <div class="section-rule"></div>
                <h2 class="section-title">Новые <em>поступления</em></h2>
            </div>
            <a href="<?= APP_URL ?>/search.php?sort=release_year+DESC" class="section-link">
                Все фильмы →
            </a>
        </div>

        <div class="grid-movies">
            <?php foreach ($latest as $m): ?>
            <?php include 'includes/movie-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<section class="section" style="background:var(--noir-2); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container">
        <div style="display:grid; grid-template-columns:1fr 360px; gap:var(--gap-2xl);">

            
            <div>
                <div class="section-rule"></div>
                <h2 class="section-title mb-xl">Жанры</h2>
                <div style="display:flex;flex-wrap:wrap;gap:var(--gap-sm);">
                    <?php foreach ($genres as $g): ?>
                    <a href="<?= APP_URL ?>/search.php?genre=<?= e($g['slug']) ?>" class="genre-tag" style="padding:0.5rem 1rem;border-radius:var(--r);background:var(--noir-3);">
                        <?= e($g['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            
            <div>
                <div class="section-rule"></div>
                <h2 class="section-title mb-xl">Топ <em>10</em></h2>
                <div class="top-list">
                    <?php foreach ($top as $i => $m): ?>
                    <a href="<?= APP_URL ?>/movie.php?id=<?= $m['id'] ?>" class="top-item">
                        <span class="top-num"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></span>
                        <?php if ($m['poster_url']): ?>
                        <img src="<?= e(tmdbImageUrl($m['poster_url'])) ?>" alt="<?= e($m['title']) ?>" class="top-poster" loading="lazy">
                        <?php else: ?>
                        <div class="top-poster skeleton"></div>
                        <?php endif; ?>
                        <div class="top-info">
                            <div class="top-title"><?= e($m['title']) ?></div>
                            <div class="top-meta"><?= $m['release_year'] ?> · <?= $m['vote_count'] ?> оценок</div>
                        </div>
                        <div class="top-rating">★ <?= formatRating($m['avg_rating']) ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</section>


<?php if (!$currentUser): ?>
<section class="section" style="text-align:center;">
    <div class="container" style="max-width:600px;">
        <div class="section-rule" style="margin:0 auto var(--gap);"></div>
        <h2 class="section-title">Стань частью <em>сообщества</em></h2>
        <p style="color:var(--text-muted);margin:var(--gap) 0 var(--gap-xl);">
            Ставь оценки, оставляй рецензии, создавай свои подборки фильмов и следи за обновлениями.
        </p>
        <div style="display:flex;gap:var(--gap);justify-content:center;flex-wrap:wrap;">
            <a href="<?= APP_URL ?>/register.php" class="btn btn--primary btn--lg">Создать аккаунт</a>
            <a href="<?= APP_URL ?>/login.php" class="btn btn--ghost btn--lg">Войти</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
 
function renderMovieCardInline(array $m): string {
    $avgRating = isset($m['avg_rating']) ? formatRating((float)$m['avg_rating']) : '';
    $rating    = $avgRating ?: ($m['kp_rating'] ? $m['kp_rating'] : '');
    $poster    = $m['poster_url'] ? e(tmdbImageUrl($m['poster_url'])) : '';
    $upcoming  = ($m['status'] ?? '') === 'upcoming';
    return '';
}

require_once 'includes/footer.php';
?>
