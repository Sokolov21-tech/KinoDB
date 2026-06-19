<?php
$adminTitle = 'Актёры';
require_once __DIR__ . '/includes/header.php';

$success = '';
$error   = '';

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_actor'])) {
    csrfVerify();
    $id   = sanitizeInt($_POST['actor_id'] ?? 0);
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
            $url = saveUploadedImage($_FILES['photo_file'], 'avatars', 'a');
            if ($url) $data['photo_url'] = $url;
        }
        if ($id) {
            dbUpdate('actors', $data, ['id' => $id]);
        } else {
            dbInsert('actors', $data);
        }
        $success = 'Сохранено!';
    }
}

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_actor'])) {
    csrfVerify();
    $id = sanitizeInt($_POST['actor_id'] ?? 0);
    if ($id) { dbDelete('actors', ['id' => $id]); $success = 'Актёр удалён.'; }
}

$q       = sanitizeString($_GET['q'] ?? '', 200);
$page    = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$cond    = $q ? "WHERE MATCH(name, original_name) AGAINST(:q IN BOOLEAN MODE)" : "";
$params  = $q ? ['q' => '+' . implode('* +', preg_split('/\s+/', trim($q))) . '*'] : [];

$countRow = dbFetch("SELECT COUNT(*) AS total FROM actors $cond", $params);
$total    = (int)($countRow['total'] ?? 0);
$actors   = dbFetchAll(
    "SELECT a.*, COUNT(ma.movie_id) AS movie_count FROM actors a
     LEFT JOIN movie_actors ma ON ma.actor_id = a.id
     $cond GROUP BY a.id ORDER BY a.name LIMIT :lim OFFSET :off",
    array_merge($params, ['lim' => $perPage, 'off' => $offset])
);

 
$editing = null;
if (isset($_GET['edit'])) {
    $editing = dbFetch("SELECT * FROM actors WHERE id = :id", ['id' => sanitizeInt($_GET['edit'])]);
}
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Актёры</h1>
    <a href="?edit=0" class="btn btn--primary">+ Добавить актёра</a>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="flash flash--error   mb"><?= e($error)   ?></div><?php endif; ?>

<?php if ($editing !== null): ?>

<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);margin-bottom:var(--gap-xl);">
    <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);margin-bottom:var(--gap-lg);">
        <?= $editing ? 'Редактировать: ' . e($editing['name']) : 'Новый актёр' ?>
    </h3>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="save_actor" value="1">
        <input type="hidden" name="actor_id" value="<?= (int)($editing['id'] ?? 0) ?>">
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
            <a href="<?= APP_URL ?>/admin/actors.php" class="btn btn--ghost">Отмена</a>
        </div>
    </form>
</div>
<?php endif; ?>


<form method="GET" class="mb">
    <div style="display:flex;gap:var(--gap-sm);max-width:360px;">
        <input type="search" name="q" class="form-control" placeholder="Поиск актёров..." value="<?= e($q) ?>">
        <button type="submit" class="btn btn--primary">Найти</button>
    </div>
</form>

<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
    <table class="data-table">
        <thead><tr><th>Фото</th><th>Имя</th><th>Гражданство</th><th>Д.р.</th><th>Фильмов</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($actors as $a): ?>
        <tr>
            <td>
                <?php if ($a['photo_url']): ?>
                <img src="<?= e(tmdbImageUrl($a['photo_url'])) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;background:var(--noir-4);" loading="lazy">
                <?php else: ?>
                <div style="width:36px;height:36px;border-radius:50%;background:var(--noir-4);"></div>
                <?php endif; ?>
            </td>
            <td>
                <div style="color:var(--cream);"><?= e($a['name']) ?></div>
                <?php if ($a['original_name']): ?><div style="font-size:0.75rem;color:var(--text-muted);"><?= e($a['original_name']) ?></div><?php endif; ?>
            </td>
            <td style="color:var(--text-muted);"><?= e($a['nationality'] ?? '—') ?></td>
            <td style="color:var(--text-faint);font-size:0.8rem;"><?= $a['birth_date'] ? date('d.m.Y', strtotime($a['birth_date'])) : '—' ?></td>
            <td style="color:var(--text-muted);"><?= $a['movie_count'] ?></td>
            <td>
                <div style="display:flex;gap:0.3rem;">
                    <a href="?edit=<?= $a['id'] ?>" class="btn btn--ghost btn--sm">✎</a>
                    <form method="POST" onsubmit="return confirm('Удалить «<?= e(addslashes($a['name'])) ?>»?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_actor" value="1">
                        <input type="hidden" name="actor_id" value="<?= $a['id'] ?>">
                        <button class="btn btn--danger btn--sm">✕</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= paginate($total, $perPage, $page, APP_URL . '/admin/actors.php?q=' . urlencode($q) . '&page={page}') ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
