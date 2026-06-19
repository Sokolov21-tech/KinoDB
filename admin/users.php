<?php
$adminTitle = 'Пользователи';
require_once __DIR__ . '/includes/header.php';

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $uid    = sanitizeInt($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($uid && $uid !== (int)$adminUser['id']) {
        if ($action === 'ban') {
            dbUpdate('users', ['is_banned' => 1], ['id' => $uid]);
            $success = 'Пользователь заблокирован.';
        } elseif ($action === 'unban') {
            dbUpdate('users', ['is_banned' => 0], ['id' => $uid]);
            $success = 'Пользователь разблокирован.';
        } elseif ($action === 'promote') {
            dbUpdate('users', ['role' => 'moderator'], ['id' => $uid]);
            $success = 'Пользователь повышен до модератора.';
        } elseif ($action === 'demote') {
            dbUpdate('users', ['role' => 'user'], ['id' => $uid]);
            $success = 'Роль пользователя сброшена.';
        } elseif ($action === 'verify') {
            dbUpdate('users', ['is_verified' => 1], ['id' => $uid]);
            $success = 'Email подтверждён вручную.';
        } elseif ($action === 'delete') {
            dbDelete('users', ['id' => $uid]);
            $success = 'Пользователь удалён.';
        }
    }
}

$q      = sanitizeString($_GET['q'] ?? '', 200);
$page   = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$cond   = $q ? "WHERE u.username LIKE :q OR u.email LIKE :q" : "";
$params = $q ? ['q' => "%$q%"] : [];

$countRow = dbFetch("SELECT COUNT(*) AS total FROM users u $cond", $params);
$total    = (int)($countRow['total'] ?? 0);

$users = dbFetchAll(
    "SELECT u.id, u.username, u.email, u.role, u.is_verified, u.is_banned, u.twofa_enabled,
            u.created_at, u.last_login,
            COUNT(DISTINCT r.id) AS rating_count,
            COUNT(DISTINCT c.id) AS comment_count
     FROM users u
     LEFT JOIN ratings  r ON r.user_id = u.id
     LEFT JOIN comments c ON c.user_id = u.id AND c.is_deleted = 0
     $cond
     GROUP BY u.id ORDER BY u.created_at DESC LIMIT :lim OFFSET :off",
    array_merge($params, ['lim' => $perPage, 'off' => $offset])
);
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Пользователи</h1>
    <span style="color:var(--text-muted);font-size:0.85rem;">Всего: <?= $total ?></span>
</div>

<?php if ($success): ?><div class="flash flash--success mb"><?= e($success) ?></div><?php endif; ?>

<form method="GET" class="mb">
    <div style="display:flex;gap:var(--gap-sm);max-width:400px;">
        <input type="search" name="q" class="form-control" placeholder="Поиск по имени / email..." value="<?= e($q) ?>">
        <button type="submit" class="btn btn--primary">Найти</button>
    </div>
</form>

<div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th><th>Пользователь</th><th>Email</th><th>Роль</th>
                <th>Вер.</th><th>2FA</th><th>Оцен.</th><th>Ком.</th>
                <th>Последний вход</th><th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr <?= $u['is_banned'] ? 'style="opacity:0.5;"' : '' ?>>
            <td style="color:var(--text-faint);"><?= $u['id'] ?></td>
            <td>
                <span style="color:var(--cream);font-weight:500;"><?= e($u['username']) ?></span>
                <?php if ($u['is_banned']): ?> <span class="badge badge--red">БАН</span><?php endif; ?>
            </td>
            <td style="color:var(--text-muted);font-size:0.82rem;"><?= e($u['email']) ?></td>
            <td>
                <span class="badge <?= $u['role'] === 'admin' ? 'badge--amber' : ($u['role'] === 'moderator' ? 'badge--green' : 'badge--muted') ?>">
                    <?= e($u['role']) ?>
                </span>
            </td>
            <td>
                <?php if ($u['is_verified']): ?>
                <span style="color:var(--green);">✓</span>
                <?php else: ?>
                <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="verify">
                    <button class="btn btn--ghost btn--sm" title="Подтвердить вручную" style="color:var(--red-bright);">✗</button>
                </form>
                <?php endif; ?>
            </td>
            <td><?= $u['twofa_enabled'] ? '<span style="color:var(--green);">✓</span>' : '—' ?></td>
            <td style="color:var(--text-muted);"><?= $u['rating_count'] ?></td>
            <td style="color:var(--text-muted);"><?= $u['comment_count'] ?></td>
            <td style="color:var(--text-faint);font-size:0.78rem;"><?= $u['last_login'] ? date('d.m.Y', strtotime($u['last_login'])) : '—' ?></td>
            <td>
                <?php if ($u['id'] != $adminUser['id']): ?>
                <div style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="<?= $u['is_banned'] ? 'unban' : 'ban' ?>">
                        <button class="btn btn--sm <?= $u['is_banned'] ? 'btn--ghost' : 'btn--danger' ?>">
                            <?= $u['is_banned'] ? 'Разблок.' : 'Забанить' ?>
                        </button>
                    </form>
                    <?php if ($u['role'] === 'user'): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="promote">
                        <button class="btn btn--ghost btn--sm">↑ Мод</button>
                    </form>
                    <?php elseif ($u['role'] === 'moderator'): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="demote">
                        <button class="btn btn--ghost btn--sm">↓ User</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить пользователя «<?= e(addslashes($u['username'])) ?>»? Это действие необратимо.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn--danger btn--sm">✕</button>
                    </form>
                </div>
                <?php else: ?>
                <span style="color:var(--text-faint);font-size:0.78rem;">Вы</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= paginate($total, $perPage, $page, APP_URL . '/admin/users.php?q=' . urlencode($q) . '&page={page}') ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
