<?php





$cardId    = (int)($m['id'] ?? 0);
$cardTitle = $m['title'] ?? '';
$cardYear  = $m['release_year'] ?? '';
$cardPoster= tmdbImageUrl($m['poster_url'] ?? '');
$avgRating = isset($m['avg_rating']) ? (float)$m['avg_rating'] : 0;
$kpRating  = (float)($m['kp_rating'] ?? 0);
$displayRating = $avgRating > 0 ? formatRating($avgRating) : ($kpRating > 0 ? number_format($kpRating,1) : '');
$isUpcoming = ($m['status'] ?? '') === 'upcoming';
$wlStatus   = (!empty($currentUser) && $cardId) ? getUserWatchlistStatus($currentUser['id'], $cardId) : null;
?>
<article class="movie-card">
    <a href="<?= APP_URL ?>/movie.php?id=<?= $cardId ?>" class="movie-card__poster-wrap">
        <?php if ($cardPoster): ?>
        <img src="<?= e($cardPoster) ?>" alt="<?= e($cardTitle) ?>" class="movie-card__poster" loading="lazy">
        <?php else: ?>
        <div class="movie-card__poster skeleton" style="width:100%;height:100%;"></div>
        <?php endif; ?>

        <?php if ($isUpcoming): ?>
        <span class="upcoming-badge">СКОРО</span>
        <?php endif; ?>

        <?php if ($displayRating): ?>
        <span class="card-badge">★ <?= e($displayRating) ?></span>
        <?php endif; ?>
    </a>

    <div class="movie-card__info">
        <a href="<?= APP_URL ?>/movie.php?id=<?= $cardId ?>">
            <h3 class="movie-card__title"><?= e($cardTitle) ?></h3>
        </a>
        <div class="movie-card__meta">
            <span class="movie-card__year"><?= $cardYear ?></span>
            <?php if ($displayRating): ?>
            <span class="movie-card__rating">★ <?= e($displayRating) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($currentUser)): ?>
        <div class="movie-card__actions" aria-label="Действия с фильмом">
            <button class="quick-btn <?= $wlStatus === 'want' ? 'active' : '' ?>"
                    data-action="watchlist" data-movie-id="<?= $cardId ?>" data-type="want"
                    data-default-label="+ Хочу" data-active-label="✓ Хочу"
                    title="Добавить в список желаний">
                <?= $wlStatus === 'want' ? '✓ Хочу' : '+ Хочу' ?>
            </button>
            <button class="quick-btn <?= $wlStatus === 'watching' ? 'active' : '' ?>"
                    data-action="watchlist" data-movie-id="<?= $cardId ?>" data-type="watching"
                    data-default-label="▶ Смотрю" data-active-label="✓ Смотрю"
                    title="Отметить, что смотрю сейчас">
                <?= $wlStatus === 'watching' ? '✓ Смотрю' : '▶ Смотрю' ?>
            </button>
            <button class="quick-btn <?= $wlStatus === 'watched' ? 'active' : '' ?>"
                    data-action="watchlist" data-movie-id="<?= $cardId ?>" data-type="watched"
                    data-default-label="✓ Смотрел" data-active-label="✓ Смотрел"
                    title="Отметить как просмотренный">
                ✓ Смотрел
            </button>
            <button class="quick-btn"
                    data-action="list-picker" data-movie-id="<?= $cardId ?>"
                    title="Добавить фильм в коллекцию">
                В коллекцию
            </button>
        </div>
        <?php else: ?>
        <a href="<?= APP_URL ?>/login.php" class="movie-card__login">Войти, чтобы добавить</a>
        <?php endif; ?>
    </div>
</article>
