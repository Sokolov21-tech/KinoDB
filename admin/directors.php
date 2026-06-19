<?php
$adminTitle = 'Режиссёры';
require_once __DIR__ . '/includes/header.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_director'])) {
    csrfVerify();
    $id   = sanitizeInt($_POST['director_id'] ?? 0);
    $data = [
        'name'          => sanitizeString($_POST['name']          ?? '', 200),
        'original_name' => sanitizeString($_POST['original_name'] ?? '', 200),
        'bio'           => sanitizeString($_POST['bio']           ?? '', 3000),
        'nationality'   => sanitizeString($_POST['nationality']   ?? '', 100),
        'birth_date'    => sanitizeString($_POST['birth_date']    ?? '', 10) ?: null,
        'photo_url'     => sanitizeString($_POST['photo_url']     ?? '', 500),
        'tmdb_id'       => sanitizeInt($_POST['tmdb_id'] ?? 0) ?: null,
    ];
    if (!$data['name']) { $error = 'Имя обязательно.'; }
    if (!$error) {
        if (!empty($_FILES['photo_file']['name'])) {
            $url = saveUploadedImage($_FILES['photo_file'], 'avatars', 'd');
            if ($url) $data['photo_url'] = $url;
        }
        $id ? dbUpdate('directors', $data, ['id' => $id]) : dbInsert('directors', $data);
        $success = 'Сохранено!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_director'])) {
    csrfVerify();
    $id = sanitizeInt($_POST['director_id'] ?? 0);
    if ($id) { dbDelete('directors', ['id' => $id]); $success = 'Режиссёр удалён.'; }
}

$q       = sanitizeString($_GET['q'] ?? '', 200);
$page    = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$cond    = $q ? "WHERE MATCH(name, original_name) AGAINST(:q IN BOOLEAN MODE)" : "";
$params  = $q ? ['q' => '+' . implode('* +', preg_split('/\s+/', trim($q))) . '*'] : [];

$countRow  = dbFetch("SELECT COUNT(*) AS total FROM directors $cond", $params);
$total     = (int)($countRow['total'] ?? 0);
$directors = dbFetchAll(
    "SELECT d.*, COUNT(md.movie_id) AS movie_count FROM directors d
     LEFT JOIN movie_directors md ON md.director_id = d.id
     $cond GROUP BY d.id ORDER BY d.name LIMIT :lim OFFSET :off",
    array_merge($params, ['lim' => $perPage, 'off' => $offset])
);

$editing = null;
if (isset($_GET['edit'])) {
    $editing = dbFetch("SELECT * FROM directors WHERE id = :id", ['id' => sanitizeInt($_GET['edit'])]);
}
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Режиссёры</h1>
    <a href="?edit=0" class="btn btn--primary">+ Добавить режиссёра</a>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="flash flash--error   mb"><?= e($error)   ?></div><?php endif; ?>

<?php if ($editing !== null): ?>
<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);margin-bottom:var(--gap-xl);">
    <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);margin-bottom:var(--gap-lg);">
        <?= $editing ? 'Редактировать: ' . e($editing['name']) : 'Новый режиссёр' ?>
    </h3>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="save_director" value="1">
        <input type="hidden" name="director_id" value="<?= (int)($editing['id'] ?? 0) ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap);">
            <div class="form-group">
                <label class="form-label">Имя (рус) *</label>
                <input type="text" name="name" class="form-control" value="<?= e($editing['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Оригинальное имя</label>
                <input type="text" name="original_name" class="form-control" value="<?= e($editing['original_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Гражданство</label>
                <input type="text" name="nationality" class="form-control" value="<?= e($editing['nationality'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Дата рождения</label>
                <input type="date" name="birth_date" class="form-control" value="<?= e($editing['birth_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Фото (URL)</label>
                <input type="url" name="photo_url" class="form-control" value="<?= e($editing['photo_url'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">TMDB ID</label>
                <input type="number" name="tmdb_id" class="form-control" value="<?= $editing['tmdb_id'] ?? '' ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Фото (файл)</label>
            <input type="file" name="photo_file" class="form-control" accept="image/*">
        </div>
        <div class="form-group">
            <label class="form-label">Биография</label>
            <textarea name="bio" class="form-control" rows="4" maxlength="3000"><?= e($editing['bio'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:var(--gap);">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="<?= APP_URL ?>/admin/directors.php" class="btn btn--ghost">Отмена</a>
        </div>
    </form>
</div>
<?php endif; ?>

<form method="GET" class="mb">
    <div style="display:flex;gap:var(--gap-sm);max-width:360px;">
        <input type="search" name="q" class="form-control" placeholder="Поиск режиссёров..." value="<?= e($q) ?>">
        <button type="submit" class="btn btn--primary">Найти</button>
    </div>
</form>

<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
    <table class="data-table">
        <thead><tr><th>Фото</th><th>Имя</th><th>Гражданство</th><th>Фильмов</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($directors as $d): ?>
        <tr>
            <td>
                <?php if ($d['photo_url']): ?>
                <img src="<?= e(tmdbImageUrl($d['photo_url'])) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" loading="lazy">
                <?php else: ?>
                <div style="width:36px;height:36px;border-radius:50%;background:var(--noir-4);"></div>
                <?php endif; ?>
            </td>
            <td>
                <div style="color:var(--cream);"><?= e($d['name']) ?></div>
                <?php if ($d['original_name']): ?><div style="font-size:0.75rem;color:var(--text-muted);"><?= e($d['original_name']) ?></div><?php endif; ?>
            </td>
            <td style="color:var(--text-muted);"><?= e($d['nationality'] ?? '—') ?></td>
            <td style="color:var(--text-muted);"><?= $d['movie_count'] ?></td>
            <td>
                <div style="display:flex;gap:0.3rem;">
                    <a href="?edit=<?= $d['id'] ?>" class="btn btn--ghost btn--sm">✎</a>
                    <form method="POST" onsubmit="return confirm('Удалить «<?= e(addslashes($d['name'])) ?>»?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_director" value="1">
                        <input type="hidden" name="director_id" value="<?= $d['id'] ?>">
                        <button class="btn btn--danger btn--sm">✕</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= paginate($total, $perPage, $page, APP_URL . '/admin/directors.php?q=' . urlencode($q) . '&page={page}') ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
