<?php
$adminTitle = 'Дашборд';
require_once __DIR__ . '/includes/header.php';

 
$stats = dbFetch(
    "SELECT
         (SELECT COUNT(*) FROM movies)       AS movies_total,
         (SELECT COUNT(*) FROM users)        AS users_total,
         (SELECT COUNT(*) FROM ratings)      AS ratings_total,
         (SELECT COUNT(*) FROM comments WHERE is_deleted = 0) AS comments_total,
         (SELECT COUNT(*) FROM movies WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS movies_month,
         (SELECT COUNT(*) FROM users  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS users_month"
);

$recentMovies = dbFetchAll("SELECT id, title, release_year, created_at FROM movies ORDER BY created_at DESC LIMIT 8");
$recentUsers  = dbFetchAll("SELECT id, username, email, created_at, role FROM users ORDER BY created_at DESC LIMIT 8");
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Дашборд</h1>
    <div style="font-family:var(--font-label);font-size:0.65rem;color:var(--text-muted);">
        <?= date('d.m.Y H:i') ?>
    </div>
</div>


<div class="admin-stat-cards">
    <div class="admin-stat-card">
        <div class="admin-stat-val"><?= number_format($stats['movies_total']) ?></div>
        <div class="admin-stat-label">Фильмов всего</div>
        <div style="font-size:0.75rem;color:var(--green);margin-top:0.3rem;">+<?= $stats['movies_month'] ?> за месяц</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-val"><?= number_format($stats['users_total']) ?></div>
        <div class="admin-stat-label">Пользователей</div>
        <div style="font-size:0.75rem;color:var(--green);margin-top:0.3rem;">+<?= $stats['users_month'] ?> за месяц</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-val"><?= number_format($stats['ratings_total']) ?></div>
        <div class="admin-stat-label">Всего оценок</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-val"><?= number_format($stats['comments_total']) ?></div>
        <div class="admin-stat-label">Комментариев</div>
    </div>
</div>


<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap-xl);">

    
    <div>
        <div class="flex-between mb">
            <h2 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);">Последние фильмы</h2>
            <a href="<?= APP_URL ?>/admin/movies.php" class="btn btn--ghost btn--sm">Все</a>
        </div>
        <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
            <table class="data-table">
                <thead><tr><th>Название</th><th>Год</th><th>Добавлен</th></tr></thead>
                <tbody>
                <?php foreach ($recentMovies as $m): ?>
                <tr>
                    <td><a href="<?= APP_URL ?>/movie.php?id=<?= $m['id'] ?>" style="color:var(--cream);"><?= e($m['title']) ?></a></td>
                    <td style="color:var(--text-muted);"><?= $m['release_year'] ?></td>
                    <td style="color:var(--text-faint);font-size:0.8rem;"><?= date('d.m.Y', strtotime($m['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div>
        <div class="flex-between mb">
            <h2 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);">Последние пользователи</h2>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn--ghost btn--sm">Все</a>
        </div>
        <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;">
            <table class="data-table">
                <thead><tr><th>Пользователь</th><th>Роль</th><th>Дата</th></tr></thead>
                <tbody>
                <?php foreach ($recentUsers as $u): ?>
                <tr>
                    <td>
                        <div style="color:var(--cream);"><?= e($u['username']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($u['email']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'badge--amber' : 'badge--muted' ?>">
                            <?= e($u['role']) ?>
                        </span>
                    </td>
                    <td style="color:var(--text-faint);font-size:0.8rem;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
