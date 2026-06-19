<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

startSession();

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL); exit; }

$movie = getMovie($id);
if (!$movie) { http_response_code(404); die('Фильм не найден.'); }

$currentUser = currentUser();
$userRating  = $currentUser ? getUserRating($currentUser['id'], $id) : null;
$wlStatus    = $currentUser ? getUserWatchlistStatus($currentUser['id'], $id) : null;
$comments    = getMovieComments($id, $currentUser['id'] ?? 0);
$ratingDist  = getMovieRatingDistribution($id);
$maxDist     = max($ratingDist) ?: 1;

$pageTitle       = $movie['title'];
$pageDescription = mb_substr(strip_tags($movie['description'] ?? ''), 0, 160);

require_once 'includes/header.php';
?>


<div class="movie-backdrop">
    <?php if ($movie['backdrop_url']): ?>
    <img src="<?= e(tmdbImageUrl($movie['backdrop_url'])) ?>" alt="<?= e($movie['title']) ?>" class="movie-backdrop__img">
    <?php else: ?>
    <div style="width:100%;height:100%;background:var(--noir-3);"></div>
    <?php endif; ?>
    <div class="movie-backdrop__overlay"></div>
</div>


<section class="movie-detail">
    <div class="container">
        <div class="movie-detail-grid">

            
            <aside class="movie-poster-wrap">
                <?php if ($movie['poster_url']): ?>
                <img src="<?= e(tmdbImageUrl($movie['poster_url'])) ?>" alt="<?= e($movie['title']) ?>" class="movie-poster">
                <?php else: ?>
                <div class="movie-poster skeleton" style="aspect-ratio:2/3;"></div>
                <?php endif; ?>

                <div class="movie-actions">
                    <?php if ($movie['trailer_url']): ?>
                    <a href="<?= e($movie['trailer_url']) ?>" target="_blank" rel="noopener" class="btn btn--primary btn--full">
                        ▶ Трейлер
                    </a>
                    <?php endif; ?>

                    <?php if ($currentUser): ?>
                    <button class="btn <?= $wlStatus === 'want' ? 'btn--primary' : 'btn--ghost' ?> btn--full"
                            data-action="watchlist" data-movie-id="<?= $id ?>" data-type="want"
                            data-default-label="+ Хочу посмотреть" data-active-label="✓ В списке желаний">
                        <?= $wlStatus === 'want' ? '✓ В списке желаний' : '+ Хочу посмотреть' ?>
                    </button>
                    <button class="btn <?= $wlStatus === 'watching' ? 'btn--primary' : 'btn--ghost' ?> btn--full"
                            data-action="watchlist" data-movie-id="<?= $id ?>" data-type="watching"
                            data-default-label="▶ Смотрю" data-active-label="✓ Смотрю">
                        <?= $wlStatus === 'watching' ? '✓ Смотрю' : '▶ Смотрю' ?>
                    </button>
                    <button class="btn <?= $wlStatus === 'watched' ? 'btn--primary' : 'btn--ghost' ?> btn--full"
                            data-action="watchlist" data-movie-id="<?= $id ?>" data-type="watched"
                            data-default-label="☑ Отметить просмотренным" data-active-label="✓ Просмотрено">
                        <?= $wlStatus === 'watched' ? '✓ Просмотрено' : '☑ Отметить просмотренным' ?>
                    </button>
                    <button class="btn btn--ghost btn--full" data-action="list-picker" data-movie-id="<?= $id ?>">
                        + В коллекцию
                    </button>
                    <?php else: ?>
                    <a href="<?= APP_URL ?>/login.php" class="btn btn--ghost btn--full">Войдите для оценки</a>
                    <?php endif; ?>
                </div>

                
                <div style="margin-top:var(--gap-lg);text-align:center;">
                    <span class="label">Поделиться</span>
                    <div style="display:flex;gap:var(--gap-sm);justify-content:center;margin-top:var(--gap-sm);">
                        <a href="https://vk.com/share.php?url=<?= urlencode(APP_URL . '/movie.php?id=' . $id) ?>"
                           target="_blank" class="btn btn--ghost btn--sm">VK</a>
                        <a href="https://t.me/share/url?url=<?= urlencode(APP_URL . '/movie.php?id=' . $id) ?>&text=<?= urlencode($movie['title']) ?>"
                           target="_blank" class="btn btn--ghost btn--sm">TG</a>
                    </div>
                </div>
            </aside>

            
            <div class="movie-info">
                <div class="section-rule"></div>
                <h1 class="movie-title"><?= e($movie['title']) ?></h1>
                <?php if ($movie['original_title'] && $movie['original_title'] !== $movie['title']): ?>
                <p class="movie-original-title"><?= e($movie['original_title']) ?></p>
                <?php endif; ?>

                
                <div class="hero-genres mb-xl">
                    <?php foreach ($movie['genres'] as $g): ?>
                    <a href="<?= APP_URL ?>/search.php?genre=<?= e($g['slug']) ?>" class="genre-tag">
                        <?= e($g['name']) ?>
                    </a>
                    <?php endforeach; ?>
                    <?php if ($movie['age_rating']): ?>
                    <span class="badge badge--muted"><?= e($movie['age_rating']) ?></span>
                    <?php endif; ?>
                </div>

                
                <div class="movie-stats">
                    <div class="stat-block">
                        <span class="stat-value stat-value--amber" data-avg-rating>
                            <?= $movie['avg_rating'] > 0 ? formatRating($movie['avg_rating']) : ($movie['kp_rating'] ?: '—') ?>
                        </span>
                        <span class="stat-label">Рейтинг KinoDB</span>
                    </div>
                    <?php if ($movie['kp_rating']): ?>
                    <div class="stat-block">
                        <span class="stat-value"><?= $movie['kp_rating'] ?></span>
                        <span class="stat-label">Кинопоиск</span>
                    </div>
                    <?php endif; ?>
                    <div class="stat-block">
                        <span class="stat-value"><?= $movie['rating_count'] ?></span>
                        <span class="stat-label">Оценок</span>
                    </div>
                    <?php if ($movie['duration']): ?>
                    <div class="stat-block">
                        <span class="stat-value"><?= formatDuration($movie['duration']) ?></span>
                        <span class="stat-label">Хронометраж</span>
                    </div>
                    <?php endif; ?>
                </div>

                
                <?php if ($currentUser): ?>
                <div class="user-rating-widget">
                    <div class="label" style="margin-bottom:var(--gap-sm);">Ваша оценка</div>
                    <div class="rating-stars">
                        <?php for ($s = 1; $s <= 10; $s++): ?>
                        <button class="star-btn <?= ($userRating && $s <= $userRating) ? 'active' : '' ?>"
                                data-action="rate" data-movie-id="<?= $id ?>" data-rating="<?= $s ?>"
                                aria-label="Оценить <?= $s ?> из 10">★</button>
                        <?php endfor; ?>
                    </div>
                    <?php if ($userRating): ?>
                    <p style="font-size:0.82rem;color:var(--text-muted);margin-top:0.3rem;">
                        Ваша оценка: <strong style="color:var(--amber);"><?= $userRating ?>/10</strong>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                
                <?php if ($movie['rating_count'] > 0): ?>
                <div class="rating-bars mb-xl">
                    <div class="label mb">Распределение оценок</div>
                    <?php for ($r = 10; $r >= 1; $r--): ?>
                    <div class="rating-bar-row">
                        <span><?= $r ?></span>
                        <div class="rating-bar-track">
                            <div class="rating-bar-fill"
                                 data-width="<?= round($ratingDist[$r] / $maxDist * 100) ?>%"
                                 style="width:0%"></div>
                        </div>
                        <span><?= $ratingDist[$r] ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

                
                <?php if ($movie['description']): ?>
                <div style="margin-bottom:var(--gap-xl);">
                    <div class="label mb">Описание</div>
                    <p style="line-height:1.75;color:var(--text-muted);"><?= e($movie['description']) ?></p>
                </div>
                <?php endif; ?>

                
                <div class="movie-meta-grid">
                    <?php if (!empty($movie['directors'])): ?>
                    <div class="meta-item">
                        <div class="meta-key">Режиссёр<?= count($movie['directors']) > 1 ? 'ы' : '' ?></div>
                        <div class="meta-val"><?= e(implode(', ', array_column($movie['directors'], 'name'))) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($movie['release_year']): ?>
                    <div class="meta-item">
                        <div class="meta-key">Год выпуска</div>
                        <div class="meta-val"><a href="<?= APP_URL ?>/search.php?year_from=<?= $movie['release_year'] ?>&year_to=<?= $movie['release_year'] ?>"><?= $movie['release_year'] ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($movie['country_name']): ?>
                    <div class="meta-item">
                        <div class="meta-key">Страна</div>
                        <div class="meta-val"><a href="<?= APP_URL ?>/search.php?country=<?= e($movie['country_code']) ?>"><?= e($movie['country_name']) ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($movie['language']): ?>
                    <div class="meta-item">
                        <div class="meta-key">Язык</div>
                        <div class="meta-val"><?= strtoupper(e($movie['language'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($movie['budget']): ?>
                    <div class="meta-item">
                        <div class="meta-key">Бюджет</div>
                        <div class="meta-val">$<?= number_format($movie['budget'] / 1e6, 1) ?>M</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($movie['revenue']): ?>
                    <div class="meta-item">
                        <div class="meta-key">Сборы</div>
                        <div class="meta-val">$<?= number_format($movie['revenue'] / 1e6, 1) ?>M</div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($movie['kinopoisk_id'])): ?>
                    <div class="meta-item">
                        <div class="meta-key">Кинопоиск</div>
                        <div class="meta-val"><a href="https://www.kinopoisk.ru/film/<?= (int)$movie['kinopoisk_id'] ?>/" target="_blank" rel="noopener">kinopoisk.ru/film/<?= (int)$movie['kinopoisk_id'] ?> ↗</a></div>
                    </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <div class="meta-key">Просмотров</div>
                        <div class="meta-val"><?= number_format($movie['views']) ?></div>
                    </div>
                </div>

                
                <?php if (!empty($movie['actors'])): ?>
                <div style="margin-bottom:var(--gap-xl);">
                    <div class="label mb">В главных ролях</div>
                    <div class="cast-grid">
                        <?php foreach (array_slice($movie['actors'], 0, 8) as $a): ?>
                        <div class="cast-card">
                            <?php if ($a['photo_url']): ?>
                            <img src="<?= e(tmdbImageUrl($a['photo_url'])) ?>" alt="<?= e($a['name']) ?>" class="cast-photo" loading="lazy">
                            <?php else: ?>
                            <div class="cast-photo skeleton"></div>
                            <?php endif; ?>
                            <div class="cast-name"><?= e($a['name']) ?></div>
                            <?php if ($a['character_name']): ?>
                            <div class="cast-role"><?= e($a['character_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        
        <section class="comments-section">
            <div class="section-rule"></div>
            <h2 class="section-title mb-xl">Обсуждение <em>(<?= count($comments) ?>)</em></h2>

            <?php if ($currentUser): ?>
            <div class="comment-form">
                <form id="comment-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="movie_id" value="<?= $id ?>">
                    <textarea name="content" placeholder="Поделитесь впечатлениями о фильме..." rows="4" required maxlength="2000"></textarea>
                    <div class="comment-form-footer">
                        <label class="spoiler-toggle">
                            <input type="checkbox" name="is_spoiler" value="1" style="accent-color:var(--amber);">
                            Содержит спойлеры
                        </label>
                        <button type="submit" class="btn btn--primary">Отправить</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);margin-bottom:var(--gap-xl);">
                <a href="<?= APP_URL ?>/login.php" style="color:var(--amber);">Войдите</a>, чтобы оставить комментарий.
            </p>
            <?php endif; ?>

            <div id="comments-list">
                <?php foreach ($comments as $comment): ?>
                <?php include 'includes/comment.php'; ?>
                <?php endforeach; ?>

                <?php if (empty($comments)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💬</div>
                    <div class="empty-state-title">Пока нет комментариев</div>
                    <p>Будьте первым!</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
