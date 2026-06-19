<?php
$adminTitle = 'Жанры и Страны';
require_once __DIR__ . '/includes/header.php';

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if (isset($_POST['save_genre'])) {
        $id   = sanitizeInt($_POST['genre_id'] ?? 0);
        $name = sanitizeString($_POST['name'] ?? '', 60);
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(sanitizeString($_POST['slug'] ?? '', 60)));
        if ($name && $slug) {
            $id ? dbUpdate('genres', ['name'=>$name,'slug'=>$slug], ['id'=>$id])
                : dbInsert('genres', ['name'=>$name,'slug'=>$slug]);
            $success = 'Жанр сохранён!';
        }
    }
    if (isset($_POST['delete_genre'])) {
        $id = sanitizeInt($_POST['genre_id'] ?? 0);
        if ($id) { dbDelete('genres', ['id'=>$id]); $success = 'Жанр удалён.'; }
    }
    if (isset($_POST['save_country'])) {
        $id   = sanitizeInt($_POST['country_id'] ?? 0);
        $name = sanitizeString($_POST['c_name'] ?? '', 100);
        $code = strtoupper(sanitizeString($_POST['c_code'] ?? '', 2));
        if ($name && strlen($code) === 2) {
            $id ? dbUpdate('countries', ['name'=>$name,'code'=>$code], ['id'=>$id])
                : dbInsert('countries', ['name'=>$name,'code'=>$code]);
            $success = 'Страна сохранена!';
        }
    }
    if (isset($_POST['delete_country'])) {
        $id = sanitizeInt($_POST['country_id'] ?? 0);
        if ($id) { dbDelete('countries', ['id'=>$id]); $success = 'Страна удалена.'; }
    }
}

$genres    = dbFetchAll("SELECT g.*, COUNT(mg.movie_id) AS movie_count FROM genres g LEFT JOIN movie_genres mg ON mg.genre_id = g.id GROUP BY g.id ORDER BY g.name");
$countries = dbFetchAll("SELECT c.*, COUNT(m.id) AS movie_count FROM countries c LEFT JOIN movies m ON m.country_id = c.id GROUP BY c.id ORDER BY c.name");
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Жанры &amp; Страны</h1>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap-xl);">

    
    <div>
        <h2 style="font-family:var(--font-display);font-size:1.4rem;color:var(--cream);margin-bottom:var(--gap-lg);">Жанры</h2>

        <form method="POST" style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-lg);margin-bottom:var(--gap);">
            <?= csrfField() ?>
            <input type="hidden" name="save_genre" value="1">
            <input type="hidden" name="genre_id" value="0">
            <div style="display:flex;gap:var(--gap-sm);">
                <input type="text" name="name" class="form-control" placeholder="Название" required maxlength="60">
                <input type="text" name="slug" class="form-control" placeholder="slug (en)" required maxlength="60">
                <button class="btn btn--primary" style="flex-shrink:0;">+ Добавить</button>
            </div>
        </form>

        <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
            <table class="data-table">
                <thead><tr><th>Название</th><th>Slug</th><th>Фильмов</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($genres as $g): ?>
                <tr>
                    <td style="color:var(--cream);"><?= e($g['name']) ?></td>
                    <td><code style="font-size:0.78rem;color:var(--amber);"><?= e($g['slug']) ?></code></td>
                    <td style="color:var(--text-muted);"><?= $g['movie_count'] ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Удалить жанр «<?= e(addslashes($g['name'])) ?>»?')" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_genre" value="1">
                            <input type="hidden" name="genre_id" value="<?= $g['id'] ?>">
                            <button class="btn btn--danger btn--sm">✕</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div>
        <h2 style="font-family:var(--font-display);font-size:1.4rem;color:var(--cream);margin-bottom:var(--gap-lg);">Страны</h2>

        <form method="POST" style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-lg);margin-bottom:var(--gap);">
            <?= csrfField() ?>
            <input type="hidden" name="save_country" value="1">
            <input type="hidden" name="country_id" value="0">
            <div style="display:flex;gap:var(--gap-sm);">
                <input type="text" name="c_name" class="form-control" placeholder="Название" required maxlength="100">
                <input type="text" name="c_code" class="form-control" placeholder="Код (RU)" required maxlength="2" style="max-width:80px;">
                <button class="btn btn--primary" style="flex-shrink:0;">+ Добавить</button>
            </div>
        </form>

        <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
            <table class="data-table">
                <thead><tr><th>Страна</th><th>Код</th><th>Фильмов</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($countries as $c): ?>
                <tr>
                    <td style="color:var(--cream);"><?= e($c['name']) ?></td>
                    <td><code style="color:var(--amber);"><?= e($c['code']) ?></code></td>
                    <td style="color:var(--text-muted);"><?= $c['movie_count'] ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Удалить «<?= e(addslashes($c['name'])) ?>»?')" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_country" value="1">
                            <input type="hidden" name="country_id" value="<?= $c['id'] ?>">
                            <button class="btn btn--danger btn--sm">✕</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
