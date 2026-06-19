<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$currentUser = currentUser();
$userId      = $currentUser['id'];
$error       = '';
$success     = '';

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_list'])) {
    csrfVerify();
    $title       = sanitizeString($_POST['title'] ?? '', 200);
    $description = sanitizeString($_POST['description'] ?? '', 1000);
    $isPublic    = !empty($_POST['is_public']) ? 1 : 0;

    if (!$title) {
        $error = 'Название обязательно.';
    } else {
        $listId  = dbInsert('user_lists', [
            'user_id'     => $userId,
            'title'       => $title,
            'description' => $description,
            'is_public'   => $isPublic,
        ]);
        $success = 'Коллекция создана!';
        redirect(APP_URL . '/list-view.php?id=' . $listId);
    }
}

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_list'])) {
    csrfVerify();
    $listId = sanitizeInt($_POST['list_id'] ?? 0);
    $list   = dbFetch("SELECT id FROM user_lists WHERE id = :id AND user_id = :uid", ['id' => $listId, 'uid' => $userId]);
    if ($list) {
        dbDelete('user_lists', ['id' => $listId]);
        $success = 'Коллекция удалена.';
    }
}

$lists     = getUserLists($userId);
$pageTitle = 'Мои коллекции';
require_once 'includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="flex-between mb-xl" style="flex-wrap:wrap;gap:var(--gap);">
            <div>
                <div class="section-rule"></div>
                <h1 class="section-title">Мои <em>коллекции</em></h1>
            </div>
            <button class="btn btn--primary" id="toggle-create-list">+ Создать коллекцию</button>
        </div>

        
        <div id="create-list-form" style="display:none;background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);margin-bottom:var(--gap-xl);">
            <h3 style="font-family:var(--font-display);font-size:1.4rem;color:var(--cream);margin-bottom:var(--gap-lg);">Новая коллекция</h3>
            <?php if ($error): ?>
            <div class="flash flash--error mb"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="create_list" value="1">
                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" name="title" class="form-control" placeholder="Мои любимые фильмы..." maxlength="200" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="1000"
                              placeholder="Опишите вашу коллекцию..."></textarea>
                </div>
                <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:var(--gap-lg);cursor:pointer;color:var(--text-muted);">
                    <input type="checkbox" name="is_public" value="1" checked style="accent-color:var(--amber);">
                    Публичная коллекция
                </label>
                <div style="display:flex;gap:var(--gap);">
                    <button type="submit" class="btn btn--primary">Создать</button>
                    <button type="button" class="btn btn--ghost" onclick="document.getElementById('create-list-form').style.display='none'">Отмена</button>
                </div>
            </form>
        </div>

        <?php if (empty($lists)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">Нет коллекций</div>
            <p>Создайте первую подборку фильмов!</p>
        </div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:var(--gap-lg);">
            <?php foreach ($lists as $list): ?>
            <div class="list-card">
                <a href="<?= APP_URL ?>/list-view.php?id=<?= $list['id'] ?>" class="list-card__cover">
                    <?php
                    $coverMovies = dbFetchAll(
                        "SELECT m.poster_url FROM user_list_movies ulm
                         JOIN movies m ON m.id = ulm.movie_id
                         WHERE ulm.list_id = :lid AND m.poster_url IS NOT NULL LIMIT 4",
                        ['lid' => $list['id']]
                    );
                    ?>
                    <?php if (count($coverMovies) >= 2): ?>
                    <div class="list-card__cover-grid">
                        <?php foreach (array_slice($coverMovies, 0, 4) as $cm): ?>
                        <img src="<?= e(tmdbImageUrl($cm['poster_url'])) ?>" alt="" class="list-card__cover-img" loading="lazy">
                        <?php endforeach; ?>
                    </div>
                    <?php elseif ($list['cover_url']): ?>
                    <img src="<?= e(tmdbImageUrl($list['cover_url'])) ?>" alt="" class="list-card__cover-img" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:100%;background:var(--noir-4);display:flex;align-items:center;justify-content:center;font-size:3rem;opacity:0.3;">📋</div>
                    <?php endif; ?>
                </a>
                <div class="list-card__info">
                    <a href="<?= APP_URL ?>/list-view.php?id=<?= $list['id'] ?>">
                        <h3 class="list-card__title"><?= e($list['title']) ?></h3>
                    </a>
                    <div class="list-card__meta">
                        <?= $list['movie_count'] ?> фильмов
                        · <?= $list['is_public'] ? 'Публичная' : 'Приватная' ?>
                        <?php if (!$list['is_public']): ?>
                        <span class="badge badge--muted" style="margin-left:0.3rem;">🔒</span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:var(--gap-sm);margin-top:var(--gap);">
                        <a href="<?= APP_URL ?>/list-view.php?id=<?= $list['id'] ?>" class="btn btn--ghost btn--sm">Открыть</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить эту коллекцию?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_list" value="1">
                            <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--sm">Удалить</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.getElementById('toggle-create-list')?.addEventListener('click', function() {
    const form = document.getElementById('create-list-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
});
</script>

<?php require_once 'includes/footer.php'; ?>
