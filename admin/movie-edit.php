<?php
$adminTitle = 'Редактирование фильма';
require_once __DIR__ . '/includes/header.php';

$id      = sanitizeInt($_GET['id'] ?? 0);
$movie   = $id ? dbFetch("SELECT * FROM movies WHERE id = :id", ['id' => $id]) : null;
$isNew   = !$movie;
$error   = '';
$success = '';

$allGenres    = getAllGenres();
$allCountries = getAllCountries();
$allDirectors = dbFetchAll("SELECT id, name FROM directors ORDER BY name");
$allActors    = dbFetchAll("SELECT id, name FROM actors ORDER BY name");

$movieGenres    = $id ? array_column(dbFetchAll("SELECT genre_id FROM movie_genres WHERE movie_id = :id", ['id' => $id]), 'genre_id') : [];
$movieDirectors = $id ? array_column(dbFetchAll("SELECT director_id FROM movie_directors WHERE movie_id = :id", ['id' => $id]), 'director_id') : [];
$movieActors    = $id ? dbFetchAll("SELECT actor_id, character_name FROM movie_actors WHERE movie_id = :id ORDER BY actor_order", ['id' => $id]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $data = [
        'title'          => sanitizeString($_POST['title'] ?? '', 500),
        'original_title' => sanitizeString($_POST['original_title'] ?? '', 500),
        'description'    => sanitizeString($_POST['description'] ?? '', 5000),
        'release_year'   => sanitizeInt($_POST['release_year'] ?? 0) ?: null,
        'release_date'   => sanitizeString($_POST['release_date'] ?? '', 10) ?: null,
        'duration'       => sanitizeInt($_POST['duration'] ?? 0) ?: null,
        'poster_url'     => sanitizeString($_POST['poster_url'] ?? '', 500),
        'backdrop_url'   => sanitizeString($_POST['backdrop_url'] ?? '', 500),
        'trailer_url'    => sanitizeString($_POST['trailer_url'] ?? '', 500),
        'country_id'     => sanitizeInt($_POST['country_id'] ?? 0) ?: null,
        'imdb_id'        => sanitizeString($_POST['imdb_id'] ?? '', 20) ?: null,
        'tmdb_id'        => sanitizeInt($_POST['tmdb_id'] ?? 0) ?: null,
        'kp_rating'      => isset($_POST['kp_rating']) ? (float)$_POST['kp_rating'] : null,
        'age_rating'     => sanitizeString($_POST['age_rating'] ?? '', 10),
        'language'       => sanitizeString($_POST['language'] ?? '', 5),
        'status'         => in_array($_POST['status'] ?? '', ['released','upcoming','in_production','cancelled']) ? $_POST['status'] : 'released',
        'is_featured'    => !empty($_POST['is_featured']) ? 1 : 0,
    ];

    if (!$data['title']) { $error = 'Название обязательно.'; }

     
    if (!$error && !empty($_FILES['poster_file']['name'])) {
        $url = saveUploadedImage($_FILES['poster_file'], 'posters', 'p');
        if ($url) $data['poster_url'] = $url;
    }

    if (!$error) {
        if ($isNew) {
            $id    = dbInsert('movies', $data);
            $isNew = false;
        } else {
            dbUpdate('movies', $data, ['id' => $id]);
        }

         
        dbQuery("DELETE FROM movie_genres WHERE movie_id = :id", ['id' => $id]);
        foreach ($_POST['genre_ids'] ?? [] as $gid) {
            $gid = (int)$gid;
            if ($gid) dbInsert('movie_genres', ['movie_id' => $id, 'genre_id' => $gid]);
        }

         
        dbQuery("DELETE FROM movie_directors WHERE movie_id = :id", ['id' => $id]);
        foreach ($_POST['director_ids'] ?? [] as $did) {
            $did = (int)$did;
            if ($did) dbInsert('movie_directors', ['movie_id' => $id, 'director_id' => $did]);
        }

         
        dbQuery("DELETE FROM movie_actors WHERE movie_id = :id", ['id' => $id]);
        $actorIds  = $_POST['actor_ids']    ?? [];
        $actorRoles = $_POST['actor_roles'] ?? [];
        foreach ($actorIds as $i => $aid) {
            $aid = (int)$aid;
            if ($aid) {
                dbInsert('movie_actors', [
                    'movie_id'       => $id,
                    'actor_id'       => $aid,
                    'character_name' => sanitizeString($actorRoles[$i] ?? '', 200),
                    'actor_order'    => $i,
                ]);
            }
        }

        $success = 'Фильм сохранён!';
        $movie   = dbFetch("SELECT * FROM movies WHERE id = :id", ['id' => $id]);
        $movieGenres    = array_column(dbFetchAll("SELECT genre_id FROM movie_genres WHERE movie_id = :id", ['id' => $id]), 'genre_id');
        $movieDirectors = array_column(dbFetchAll("SELECT director_id FROM movie_directors WHERE movie_id = :id", ['id' => $id]), 'director_id');
        $movieActors    = dbFetchAll("SELECT actor_id, character_name FROM movie_actors WHERE movie_id = :id ORDER BY actor_order", ['id' => $id]);
    }
}
?>

<div class="admin-topbar">
    <h1 class="admin-page-title"><?= $isNew ? 'Добавить фильм' : 'Редактировать: ' . e($movie['title'] ?? '') ?></h1>
    <a href="<?= APP_URL ?>/admin/movies.php" class="btn btn--ghost">← К списку</a>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="flash flash--error   mb"><?= e($error)   ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap-xl);">

        
        <div>
            <div class="form-group">
                <label class="form-label">Название (рус) *</label>
                <input type="text" name="title" class="form-control" value="<?= e($movie['title'] ?? '') ?>" required maxlength="500">
            </div>
            <div class="form-group">
                <label class="form-label">Оригинальное название</label>
                <input type="text" name="original_title" class="form-control" value="<?= e($movie['original_title'] ?? '') ?>" maxlength="500">
            </div>
            <div class="form-group">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="6" maxlength="5000"><?= e($movie['description'] ?? '') ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap);">
                <div class="form-group">
                    <label class="form-label">Год</label>
                    <input type="number" name="release_year" class="form-control" value="<?= $movie['release_year'] ?? '' ?>" min="1888" max="<?= date('Y')+5 ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Дата релиза</label>
                    <input type="date" name="release_date" class="form-control" value="<?= $movie['release_date'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Хронометраж (мин)</label>
                    <input type="number" name="duration" class="form-control" value="<?= $movie['duration'] ?? '' ?>" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Возрастной рейтинг</label>
                    <select name="age_rating" class="form-control">
                        <?php foreach (['','G','PG','PG-13','R','NC-17','0+','6+','12+','16+','18+'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($movie['age_rating'] ?? '') === $r ? 'selected':'' ?>><?= $r ?: '—' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        
        <div>
            <div class="form-group">
                <label class="form-label">Постер (URL)</label>
                <input type="url" name="poster_url" class="form-control" value="<?= e($movie['poster_url'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Постер (загрузить файл)</label>
                <input type="file" name="poster_file" class="form-control" accept="image/*">
            </div>
            <div class="form-group">
                <label class="form-label">Задний фон (URL backdrop)</label>
                <input type="url" name="backdrop_url" class="form-control" value="<?= e($movie['backdrop_url'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Трейлер (YouTube URL)</label>
                <input type="url" name="trailer_url" class="form-control" value="<?= e($movie['trailer_url'] ?? '') ?>">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap);">
                <div class="form-group">
                    <label class="form-label">IMDb ID</label>
                    <input type="text" name="imdb_id" class="form-control" value="<?= e($movie['imdb_id'] ?? '') ?>" placeholder="tt0000000">
                </div>
                <div class="form-group">
                    <label class="form-label">TMDB ID</label>
                    <input type="number" name="tmdb_id" class="form-control" value="<?= $movie['tmdb_id'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Рейтинг Кинопоиска</label>
                    <input type="number" name="kp_rating" class="form-control" value="<?= $movie['kp_rating'] ?? '' ?>" min="0" max="10" step="0.1">
                </div>
                <div class="form-group">
                    <label class="form-label">Язык</label>
                    <input type="text" name="language" class="form-control" value="<?= e($movie['language'] ?? '') ?>" placeholder="en" maxlength="5">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap);">
                <div class="form-group">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-control">
                        <?php foreach (['released'=>'Вышел','upcoming'=>'Ожидается','in_production'=>'В производстве','cancelled'=>'Отменён'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($movie['status'] ?? 'released') === $v ? 'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Страна</label>
                    <select name="country_id" class="form-control">
                        <option value="">— Не выбрана —</option>
                        <?php foreach ($allCountries as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($movie['country_id'] ?? 0) == $c['id'] ? 'selected':'' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:var(--gap-lg);cursor:pointer;color:var(--text-muted);">
                <input type="checkbox" name="is_featured" value="1" <?= !empty($movie['is_featured']) ? 'checked':'' ?> style="accent-color:var(--amber);">
                Показывать в слайдере на главной
            </label>
        </div>
    </div>

    
    <div class="form-group">
        <label class="form-label">Жанры</label>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
            <?php foreach ($allGenres as $g): ?>
            <label style="display:flex;align-items:center;gap:0.3rem;padding:0.3rem 0.6rem;border:1px solid var(--border);border-radius:var(--r-sm);cursor:pointer;font-size:0.82rem;transition:border-color 0.2s,background 0.2s;"
                   onmouseover="this.style.borderColor='var(--amber-dim)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border)'">
                <input type="checkbox" name="genre_ids[]" value="<?= $g['id'] ?>"
                       <?= in_array($g['id'], $movieGenres) ? 'checked':'' ?> style="accent-color:var(--amber);">
                <?= e($g['name']) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    
    <div class="form-group">
        <label class="form-label">Режиссёры</label>
        <select name="director_ids[]" class="form-control" multiple size="6">
            <?php foreach ($allDirectors as $d): ?>
            <option value="<?= $d['id'] ?>" <?= in_array($d['id'], $movieDirectors) ? 'selected':'' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-hint">Удерживайте Ctrl (Windows) или ⌘ (Mac) для выбора нескольких</div>
    </div>

    
    <div class="form-group">
        <label class="form-label">Актёры &amp; роли</label>
        <div id="actors-list">
            <?php foreach ($movieActors as $i => $a): ?>
            <div class="actor-row" style="display:flex;gap:var(--gap-sm);margin-bottom:var(--gap-sm);">
                <select name="actor_ids[]" class="form-control" style="flex:1;">
                    <option value="">— Актёр —</option>
                    <?php foreach ($allActors as $act): ?>
                    <option value="<?= $act['id'] ?>" <?= $act['id'] == $a['actor_id'] ? 'selected':'' ?>><?= e($act['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="actor_roles[]" class="form-control" style="flex:1;" placeholder="Роль / персонаж" value="<?= e($a['character_name'] ?? '') ?>">
                <button type="button" onclick="this.closest('.actor-row').remove()" class="btn btn--danger btn--sm" style="flex-shrink:0;">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-actor-btn" class="btn btn--ghost btn--sm mt-sm">+ Добавить актёра</button>
    </div>

    <div style="display:flex;gap:var(--gap);margin-top:var(--gap-xl);">
        <button type="submit" class="btn btn--primary btn--lg">Сохранить фильм</button>
        <a href="<?= APP_URL ?>/admin/movies.php" class="btn btn--ghost btn--lg">Отмена</a>
        <?php if (!$isNew): ?>
        <a href="<?= APP_URL ?>/movie.php?id=<?= $id ?>" class="btn btn--ghost btn--lg" target="_blank">Просмотр ↗</a>
        <?php endif; ?>
    </div>
</form>

<script>

const actorTemplate = `
<div class="actor-row" style="display:flex;gap:var(--gap-sm);margin-bottom:var(--gap-sm);">
    <select name="actor_ids[]" class="form-control" style="flex:1;">
        <option value="">— Актёр —</option>
        <?php foreach ($allActors as $act): ?>
        <option value="<?= $act['id'] ?>"><?= e(addslashes($act['name'])) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="actor_roles[]" class="form-control" style="flex:1;" placeholder="Роль / персонаж">
    <button type="button" onclick="this.closest('.actor-row').remove()" class="btn btn--danger btn--sm" style="flex-shrink:0;">✕</button>
</div>`;

document.getElementById('add-actor-btn')?.addEventListener('click', () => {
    document.getElementById('actors-list').insertAdjacentHTML('beforeend', actorTemplate);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
