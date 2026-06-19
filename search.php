<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

startSession();
rateLimitOrDie('search', RATE_LIMIT_MAX_PAGE);

$filters = [
    'q'          => sanitizeString($_GET['q'] ?? '', 200),
    'genre'      => sanitizeString($_GET['genre'] ?? '', 60),
    'year_from'  => sanitizeInt($_GET['year_from'] ?? 0) ?: null,
    'year_to'    => sanitizeInt($_GET['year_to']   ?? 0) ?: null,
    'country'    => sanitizeString($_GET['country'] ?? '', 2),
    'rating_min' => isset($_GET['rating_min']) ? min(10, max(0, (float)$_GET['rating_min'])) : null,
    'status'     => in_array($_GET['status'] ?? '', ['released','upcoming','in_production']) ? $_GET['status'] : null,
    'sort'       => sanitizeString($_GET['sort'] ?? 'release_year DESC', 50),
];

$page    = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 24;
$result  = searchMovies($filters, $page, $perPage);

$countries  = getAllCountries();
$genresList = getAllGenres();

$pageTitle       = $filters['q'] ? 'Поиск: ' . $filters['q'] : 'Все фильмы';
$pageDescription = 'Расширенный поиск фильмов по жанру, году, стране и рейтингу';

require_once 'includes/header.php';
?>

<section class="section">
    <div class="container">
        
        <div style="margin-bottom:var(--gap-xl);">
            <div class="section-rule"></div>
            <h1 class="section-title">
                <?php if ($filters['q']): ?>
                    Результаты по <em>«<?= e($filters['q']) ?>»</em>
                <?php else: ?>
                    Все <em>фильмы</em>
                <?php endif; ?>
            </h1>
            <p style="color:var(--text-muted);margin-top:var(--gap-sm);">Найдено: <?= $result['total'] ?> фильмов</p>
        </div>

        <div class="search-layout">

            
            <aside class="filters-sidebar">
                <form id="search-form" method="GET" action="">
                    <div class="filters-title">Фильтры</div>

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Поиск</div>
                        <div class="search-field">
                            <input type="search" name="q" class="form-control" placeholder="Название фильма..."
                                   value="<?= e($filters['q']) ?>" autocomplete="off" data-search-suggest>
                            <div class="search-suggestions" aria-live="polite"></div>
                        </div>
                    </div>

                    <input type="hidden" name="genre" id="genre-hidden" value="<?= e($filters['genre']) ?>">
                    <input type="hidden" name="page" value="1">

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Жанр</div>
                        <div class="genre-filter-grid">
                            <?php foreach ($genresList as $g): ?>
                            <button type="button" class="genre-filter-btn <?= $filters['genre'] === $g['slug'] ? 'active' : '' ?>"
                                    data-slug="<?= e($g['slug']) ?>">
                                <?= e($g['name']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Год выпуска</div>
                        <div class="range-row">
                            <input type="number" name="year_from" class="range-input" placeholder="От"
                                   min="1888" max="<?= date('Y')+2 ?>" value="<?= $filters['year_from'] ?>">
                            <span>—</span>
                            <input type="number" name="year_to" class="range-input" placeholder="До"
                                   min="1888" max="<?= date('Y')+2 ?>" value="<?= $filters['year_to'] ?>">
                        </div>
                    </div>

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Страна</div>
                        <select name="country" class="form-control">
                            <option value="">Все страны</option>
                            <?php foreach ($countries as $c): ?>
                            <option value="<?= e($c['code']) ?>" <?= $filters['country'] === $c['code'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Минимальный рейтинг: <strong id="rating-val"><?= $filters['rating_min'] ?? 0 ?></strong></div>
                        <input type="range" name="rating_min" min="0" max="10" step="0.5"
                               value="<?= $filters['rating_min'] ?? 0 ?>"
                               style="width:100%;accent-color:var(--amber);"
                               oninput="document.getElementById('rating-val').textContent=this.value">
                    </div>

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Статус</div>
                        <select name="status" class="form-control">
                            <option value="">Все</option>
                            <option value="released"      <?= $filters['status']==='released'      ? 'selected':'' ?>>Вышедшие</option>
                            <option value="upcoming"      <?= $filters['status']==='upcoming'      ? 'selected':'' ?>>Ожидаемые</option>
                            <option value="in_production" <?= $filters['status']==='in_production' ? 'selected':'' ?>>В производстве</option>
                        </select>
                    </div>

                    
                    <div class="filter-group">
                        <div class="filter-group-label">Сортировка</div>
                        <select name="sort" class="form-control">
                            <option value="release_year DESC" <?= $filters['sort']==='release_year DESC' ? 'selected':'' ?>>Сначала новые</option>
                            <option value="release_year ASC"  <?= $filters['sort']==='release_year ASC'  ? 'selected':'' ?>>Сначала старые</option>
                            <option value="views DESC"        <?= $filters['sort']==='views DESC'        ? 'selected':'' ?>>По популярности</option>
                            <option value="kp_rating DESC"    <?= $filters['sort']==='kp_rating DESC'    ? 'selected':'' ?>>По рейтингу Кинопоиска</option>
                            <option value="title ASC"         <?= $filters['sort']==='title ASC'         ? 'selected':'' ?>>По названию А-Я</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn--primary btn--full">Применить</button>
                    <?php if (array_filter($filters)): ?>
                    <a href="<?= APP_URL ?>/search.php" class="btn btn--ghost btn--full mt-sm">Сбросить</a>
                    <?php endif; ?>
                </form>
            </aside>

            
            <div>
                <?php if (empty($result['movies'])): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🎬</div>
                    <div class="empty-state-title">Фильмы не найдены</div>
                    <p>Попробуйте изменить параметры поиска.</p>
                </div>
                <?php else: ?>
                <div class="grid-movies">
                    <?php foreach ($result['movies'] as $m): ?>
                    <?php include 'includes/movie-card.php'; ?>
                    <?php endforeach; ?>
                </div>

                
                <?php if ($result['pages'] > 1): ?>
                <?php
                $urlBase = APP_URL . '/search.php?' . http_build_query(array_filter(array_merge($filters, ['page' => '{page}'])));
                echo paginate($result['total'], $perPage, $page, $urlBase);
                ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
