<?php
$adminTitle = 'Комментарии';
require_once __DIR__ . '/includes/header.php';

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id     = sanitizeInt($_POST['comment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id) {
        if ($action === 'delete') {
            dbUpdate('comments', ['is_deleted' => 1, 'content' => '[Удалено модератором]'], ['id' => $id]);
            $success = 'Комментарий удалён.';
        } elseif ($action === 'restore') {
            dbUpdate('comments', ['is_deleted' => 0], ['id' => $id]);
            $success = 'Комментарий восстановлен.';
        }
    }
}

$showDeleted = !empty($_GET['deleted']);
$page        = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage     = 30;
$offset      = ($page - 1) * $perPage;
$cond        = $showDeleted ? "" : "AND c.is_deleted = 0";

$countRow = dbFetch("SELECT COUNT(*) AS total FROM comments c WHERE 1=1 $cond");
$total    = (int)($countRow['total'] ?? 0);

$comments = dbFetchAll(
    "SELECT c.*, u.username, m.title AS movie_title, m.id AS movie_id
     FROM comments c
     JOIN users u  ON u.id = c.user_id
     JOIN movies m ON m.id = c.movie_id
     WHERE 1=1 $cond
     ORDER BY c.created_at DESC LIMIT :lim OFFSET :off",
    ['lim' => $perPage, 'off' => $offset]
);
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Комментарии</h1>
    <div style="display:flex;gap:var(--gap-sm);">
        <a href="?deleted=<?= $showDeleted ? 0 : 1 ?>" class="btn btn--ghost btn--sm">
            <?= $showDeleted ? 'Активные' : 'Показать удалённые' ?>
        </a>
    </div>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>

<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Автор</th><th>Фильм</th><th>Комментарий</th><th>Лайков</th><th>Дата</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($comments as $c): ?>
        <tr <?= $c['is_deleted'] ? 'style="opacity:0.5;"' : '' ?>>
            <td style="color:var(--text-faint);"><?= $c['id'] ?></td>
            <td style="color:var(--cream);"><?= e($c['username']) ?></td>
            <td>
                <a href="<?= APP_URL ?>/movie.php?id=<?= $c['movie_id'] ?>#comment-<?= $c['id'] ?>"
                   style="color:var(--amber);font-size:0.85rem;" target="_blank">
                    <?= e(mb_substr($c['movie_title'], 0, 30)) ?>↗
                </a>
            </td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.85rem;">
                <?= e(mb_substr($c['content'], 0, 120)) ?>
            </td>
            <td style="color:var(--text-muted);"><?= $c['likes_count'] ?></td>
            <td style="color:var(--text-faint);font-size:0.78rem;"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                    <?php if ($c['is_deleted']): ?>
                    <input type="hidden" name="action" value="restore">
                    <button class="btn btn--ghost btn--sm">Восст.</button>
                    <?php else: ?>
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn--danger btn--sm" onclick="return confirm('Удалить комментарий?')">Удалить</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= paginate($total, $perPage, $page, APP_URL . '/admin/comments.php?deleted=' . (int)$showDeleted . '&page={page}') ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
