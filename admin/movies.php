<?php
$adminTitle = 'Управление фильмами';
require_once __DIR__ . '/includes/header.php';

$error   = '';
$success = '';

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_movie'])) {
    csrfVerify();
    $id = sanitizeInt($_POST['movie_id'] ?? 0);
    if ($id && dbExists('movies', ['id' => $id])) {
        dbDelete('movies', ['id' => $id]);
        $success = 'Фильм удалён.';
    }
}

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    csrfVerify();
    $id  = sanitizeInt($_POST['movie_id'] ?? 0);
    $cur = dbFetch("SELECT is_featured FROM movies WHERE id = :id", ['id' => $id]);
    if ($cur) {
        dbUpdate('movies', ['is_featured' => $cur['is_featured'] ? 0 : 1], ['id' => $id]);
    }
}

 
$q    = sanitizeString($_GET['q'] ?? '', 200);
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$searchCond = $q ? "WHERE MATCH(title, original_title, description) AGAINST(:q IN BOOLEAN MODE)" : "";
$params     = $q ? ['q' => '+' . implode('* +', preg_split('/\s+/', trim($q))) . '*'] : [];

$countRow = dbFetch("SELECT COUNT(*) AS total FROM movies $searchCond", $params);
$total    = (int)($countRow['total'] ?? 0);
$pages    = (int)ceil($total / $perPage);

$movies = dbFetchAll(
    "SELECT m.id, m.title, m.release_year, m.status, m.is_featured, m.kp_rating, m.views,
            COUNT(DISTINCT r.id) AS rating_count,
            ROUND(AVG(r.rating),1) AS avg_rating
     FROM movies m LEFT JOIN ratings r ON r.movie_id = m.id
     $searchCond
     GROUP BY m.id ORDER BY m.created_at DESC LIMIT :lim OFFSET :off",
    array_merge($params, ['lim' => $perPage, 'off' => $offset])
);
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Фильмы</h1>
    <div style="display:flex;gap:var(--gap-sm);">
        <a href="<?= APP_URL ?>/admin/movie-edit.php" class="btn btn--primary">+ Добавить фильм</a>
        <a href="<?= APP_URL ?>/admin/import.php" class="btn btn--ghost">↓ Импорт из Кинопоиска</a>
    </div>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="flash flash--error   mb"><?= e($error)   ?></div><?php endif; ?>


<form method="GET" class="mb">
    <div style="display:flex;gap:var(--gap-sm);max-width:400px;">
        <input type="search" name="q" class="form-control" placeholder="Поиск фильмов..." value="<?= e($q) ?>">
        <button type="submit" class="btn btn--primary">Найти</button>
    </div>
</form>

<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Год</th>
                <th>Рейтинг</th>
                <th>Оценок</th>
                <th>Просм.</th>
                <th>Статус</th>
                <th>Фичер</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($movies as $m): ?>
        <tr>
            <td style="color:var(--text-faint);"><?= $m['id'] ?></td>
            <td>
                <a href="<?= APP_URL ?>/movie.php?id=<?= $m['id'] ?>" style="color:var(--cream);" target="_blank">
                    <?= e($m['title']) ?>
                </a>
            </td>
            <td style="color:var(--text-muted);"><?= $m['release_year'] ?></td>
            <td style="color:var(--amber);"><?= $m['avg_rating'] ?: ($m['kp_rating'] ?: '—') ?></td>
            <td style="color:var(--text-muted);"><?= $m['rating_count'] ?></td>
            <td style="color:var(--text-muted);"><?= number_format($m['views']) ?></td>
            <td>
                <span class="badge <?= $m['status'] === 'released' ? 'badge--green' : ($m['status'] === 'upcoming' ? 'badge--amber' : 'badge--muted') ?>">
                    <?= e($m['status']) ?>
                </span>
            </td>
            <td>
                <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="toggle_featured" value="1">
                    <input type="hidden" name="movie_id" value="<?= $m['id'] ?>">
                    <button type="submit" class="btn btn--sm <?= $m['is_featured'] ? 'btn--primary' : 'btn--ghost' ?>" title="Вкл/выкл избранное">
                        <?= $m['is_featured'] ? '★' : '☆' ?>
                    </button>
                </form>
            </td>
            <td>
                <div style="display:flex;gap:0.3rem;">
                    <a href="<?= APP_URL ?>/admin/movie-edit.php?id=<?= $m['id'] ?>" class="btn btn--ghost btn--sm">✎</a>
                    <form method="POST" onsubmit="return confirm('Удалить фильм «<?= e(addslashes($m['title'])) ?>»?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_movie" value="1">
                        <input type="hidden" name="movie_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn--danger btn--sm">✕</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>


<?php echo paginate($total, $perPage, $page, APP_URL . '/admin/movies.php?q=' . urlencode($q) . '&page={page}'); ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
