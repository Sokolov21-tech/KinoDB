<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

startSession();

$id   = sanitizeInt($_GET['id'] ?? 0);
$list = $id ? dbFetch("SELECT ul.*, u.username FROM user_lists ul JOIN users u ON u.id = ul.user_id WHERE ul.id = :id", ['id' => $id]) : null;

if (!$list) { http_response_code(404); die('Коллекция не найдена.'); }

$currentUser = currentUser();
$isOwner     = $currentUser && $currentUser['id'] === (int)$list['user_id'];

 
if (!$list['is_public'] && !$isOwner) {
    http_response_code(403); die('Эта коллекция приватная.');
}

 
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    csrfVerify();
    if (isset($_POST['update_list'])) {
        dbUpdate('user_lists', [
            'title'       => sanitizeString($_POST['title'] ?? '', 200),
            'description' => sanitizeString($_POST['description'] ?? '', 1000),
            'is_public'   => !empty($_POST['is_public']) ? 1 : 0,
        ], ['id' => $id]);
        redirect(APP_URL . '/list-view.php?id=' . $id);
    }
    if (isset($_POST['remove_movie'])) {
        $mid = sanitizeInt($_POST['movie_id'] ?? 0);
        dbQuery("DELETE FROM user_list_movies WHERE list_id = :lid AND movie_id = :mid", ['lid' => $id, 'mid' => $mid]);
        redirect(APP_URL . '/list-view.php?id=' . $id);
    }
}

$movies = dbFetchAll(
    "SELECT m.id, m.title, m.release_year, m.poster_url, m.kp_rating, ulm.note, ulm.order_pos,
            (SELECT AVG(r.rating) FROM ratings r WHERE r.movie_id = m.id) AS avg_rating
     FROM user_list_movies ulm JOIN movies m ON m.id = ulm.movie_id
     WHERE ulm.list_id = :lid ORDER BY ulm.order_pos, ulm.added_at",
    ['lid' => $id]
);

$pageTitle       = $list['title'];
$pageDescription = mb_substr($list['description'] ?? '', 0, 160);
require_once 'includes/header.php';
?>

<section class="section">
    <div class="container">

        
        <div style="display:grid;grid-template-columns:1fr auto;gap:var(--gap-xl);align-items:start;margin-bottom:var(--gap-xl);">
            <div>
                <div class="section-rule"></div>
                <h1 class="section-title"><?= e($list['title']) ?></h1>
                <p style="color:var(--text-muted);margin:var(--gap-sm) 0;">
                    Автор: <strong style="color:var(--cream);"><?= e($list['username']) ?></strong>
                    · <?= count($movies) ?> фильмов
                    <?php if (!$list['is_public']): ?> · <span class="badge badge--muted">🔒 Приватная</span><?php endif; ?>
                </p>
                <?php if ($list['description']): ?>
                <p style="color:var(--text-muted);"><?= nl2br(e($list['description'])) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($isOwner): ?>
            <div style="display:flex;gap:var(--gap-sm);">
                <button class="btn btn--ghost btn--sm" id="toggle-edit-list">✎ Редактировать</button>
                <a href="<?= APP_URL ?>/lists.php" class="btn btn--ghost btn--sm">← Мои коллекции</a>
            </div>
            <?php endif; ?>
        </div>

        
        <?php if ($isOwner): ?>
        <div id="edit-list-form" style="display:none;background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);margin-bottom:var(--gap-xl);">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="update_list" value="1">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap);">
                    <div class="form-group">
                        <label class="form-label">Название</label>
                        <input type="text" name="title" class="form-control" value="<?= e($list['title']) ?>" required>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;color:var(--text-muted);">
                            <input type="checkbox" name="is_public" value="1" <?= $list['is_public'] ? 'checked':'' ?> style="accent-color:var(--amber);">
                            Публичная
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($list['description'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn--primary">Сохранить</button>
            </form>
        </div>
        <?php endif; ?>

        
        <?php if (empty($movies)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">Коллекция пуста</div>
            <?php if ($isOwner): ?>
            <p style="margin:var(--gap) 0 var(--gap-xl);">Ищите фильмы и добавляйте их в эту коллекцию.</p>
            <a href="<?= APP_URL ?>/search.php" class="btn btn--primary">Найти фильмы</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="grid-movies">
            <?php foreach ($movies as $m): ?>
            <div style="position:relative;">
                <?php include 'includes/movie-card.php'; ?>
                <?php if ($m['note']): ?>
                <div style="padding:0.5rem 0.75rem;font-size:0.78rem;color:var(--text-muted);border-top:1px solid var(--border);">
                    <?= nl2br(e($m['note'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($isOwner): ?>
                <form method="POST" style="position:absolute;top:0.5rem;left:0.5rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="remove_movie" value="1">
                    <input type="hidden" name="movie_id" value="<?= $m['id'] ?>">
                    <button class="btn btn--danger btn--sm" style="opacity:0;transition:opacity 0.2s;"
                            onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0'"
                            onclick="return confirm('Убрать из коллекции?')">✕</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<script>
document.getElementById('toggle-edit-list')?.addEventListener('click', function() {
    const form = document.getElementById('edit-list-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
});
</script>

<?php require_once 'includes/footer.php'; ?>
