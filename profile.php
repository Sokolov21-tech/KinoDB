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
$user        = dbFetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
$error       = '';
$success     = '';
$activeTab   = in_array($_GET['tab'] ?? '', ['ratings', 'settings', 'security'], true) ? $_GET['tab'] : 'ratings';

 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $activeTab = 'settings';
        $bio      = sanitizeString($_POST['bio'] ?? '', 500);
        $updates  = ['bio' => $bio];

         
        if (!empty($_FILES['avatar']['name'])) {
            $url = saveUploadedImage($_FILES['avatar'], 'avatars', 'u' . $userId . '_');
            if ($url) {
                $updates['avatar'] = $url;
                $_SESSION['user_avatar'] = $url;
            } else {
                $error = 'Ошибка загрузки аватара.';
            }
        }

        if (!$error) {
            dbUpdate('users', $updates, ['id' => $userId]);
            $success = 'Профиль обновлён!';
            $user    = dbFetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        }
    }

    if ($action === 'change_password') {
        $activeTab = 'security';
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';

        if (!password_verify($old, $user['password_hash'])) {
            $error = 'Неверный текущий пароль.';
        } elseif ($new !== $new2) {
            $error = 'Новые пароли не совпадают.';
        } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
            $error = 'Новый пароль слишком короткий.';
        } else {
            dbUpdate('users', ['password_hash' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12])], ['id' => $userId]);
            $success = 'Пароль изменён!';
        }
    }
}

 
$statsRow = dbFetch(
    "SELECT
         (SELECT COUNT(*) FROM ratings    WHERE user_id = :uid1) AS ratings_count,
         (SELECT COUNT(*) FROM comments   WHERE user_id = :uid2 AND is_deleted = 0) AS comments_count,
         (SELECT COUNT(*) FROM watchlist  WHERE user_id = :uid3 AND type = 'watched') AS watched_count,
         (SELECT COUNT(*) FROM user_lists WHERE user_id = :uid4) AS lists_count",
    ['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId, 'uid4' => $userId]
);
$recentRatings = dbFetchAll(
    "SELECT r.rating, r.created_at, m.id, m.id AS movie_id, m.title, m.release_year,
            m.poster_url, m.kp_rating, m.status,
            (SELECT AVG(r2.rating) FROM ratings r2 WHERE r2.movie_id = m.id) AS avg_rating
     FROM ratings r JOIN movies m ON m.id = r.movie_id
     WHERE r.user_id = :uid ORDER BY r.updated_at DESC LIMIT 6",
    ['uid' => $userId]
);

$pageTitle = 'Мой профиль';
require_once 'includes/header.php';
?>

<section class="section">
    <div class="container">

        <?php if ($success): ?>
        <div class="flash flash--success" style="margin-bottom:var(--gap-xl);"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flash flash--error" style="margin-bottom:var(--gap-xl);"><?= e($error) ?></div>
        <?php endif; ?>

        
        <div class="profile-header">
            <form method="POST" enctype="multipart/form-data" style="position:relative;display:inline-block;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="bio" value="<?= e($user['bio'] ?? '') ?>">
                <?php if ($user['avatar']): ?>
                <img src="<?= e($user['avatar']) ?>" alt="Аватар" class="profile-avatar" id="avatar-preview">
                <?php else: ?>
                <div class="profile-avatar-placeholder" id="avatar-preview">
                    <?= mb_strtoupper(mb_substr($user['username'], 0, 1)) ?>
                </div>
                <?php endif; ?>
                <label style="position:absolute;bottom:4px;right:4px;width:28px;height:28px;border-radius:50%;background:var(--amber);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.8rem;color:var(--noir);"
                       title="Изменить аватар">
                    ✎
                    <input type="file" name="avatar" accept="image/*" style="display:none;" id="avatar-file-input"
                           data-preview-target="avatar-preview" onchange="this.form.submit()">
                </label>
            </form>

            <div class="profile-info">
                <h1 class="profile-username"><?= e($user['username']) ?></h1>
                <?php if ($user['bio']): ?>
                <p class="profile-bio"><?= e($user['bio']) ?></p>
                <?php endif; ?>
                <div class="profile-stats">
                    <div class="profile-stat">
                        <span class="profile-stat-val"><?= $statsRow['watched_count'] ?></span>
                        <span class="profile-stat-label">Просмотрено</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-val"><?= $statsRow['ratings_count'] ?></span>
                        <span class="profile-stat-label">Оценок</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-val"><?= $statsRow['comments_count'] ?></span>
                        <span class="profile-stat-label">Комментариев</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-val"><?= $statsRow['lists_count'] ?></span>
                        <span class="profile-stat-label">Коллекций</span>
                    </div>
                </div>
                <p style="font-size:0.78rem;color:var(--text-faint);margin-top:var(--gap-sm);">
                    Участник с <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                    <?php if ($user['twofa_enabled']): ?>
                    · <span class="badge badge--green">2FA включена</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        
        <div class="tabs" data-tabs="profile-tabs">
            <a href="?tab=ratings" class="tab-btn <?= $activeTab === 'ratings' ? 'active' : '' ?>"
               data-tab-target="profile-ratings" aria-controls="profile-ratings"
               aria-selected="<?= $activeTab === 'ratings' ? 'true' : 'false' ?>">Последние оценки</a>
            <a href="?tab=settings" class="tab-btn <?= $activeTab === 'settings' ? 'active' : '' ?>"
               data-tab-target="profile-settings" aria-controls="profile-settings"
               aria-selected="<?= $activeTab === 'settings' ? 'true' : 'false' ?>">Настройки</a>
            <a href="?tab=security" class="tab-btn <?= $activeTab === 'security' ? 'active' : '' ?>"
               data-tab-target="profile-security" aria-controls="profile-security"
               aria-selected="<?= $activeTab === 'security' ? 'true' : 'false' ?>">Безопасность</a>
        </div>

        
        <div class="tab-content <?= $activeTab === 'ratings' ? 'active' : '' ?>" id="profile-ratings" data-tab-panel="profile-tabs">
            <?php if ($recentRatings): ?>
            <div class="grid-movies">
                <?php foreach ($recentRatings as $r): ?>
                <div style="position:relative;">
                    <?php $m = $r; include 'includes/movie-card.php'; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:var(--gap-xl);text-align:center;">
                <a href="<?= APP_URL ?>/watchlist.php?tab=watched" class="btn btn--ghost">Все просмотренные →</a>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎬</div>
                <div class="empty-state-title">Нет оценок</div>
                <p>Оценивайте фильмы, чтобы они появились здесь.</p>
            </div>
            <?php endif; ?>
        </div>

        
        <div class="tab-content <?= $activeTab === 'settings' ? 'active' : '' ?>" id="profile-settings" data-tab-panel="profile-tabs">
            <div style="max-width:500px;">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="avatar_upload" id="avatar-data">

                    <div class="form-group">
                        <label class="form-label">Аватар</label>
                        <input type="file" name="avatar" accept="image/*"
                               class="form-control" data-preview-target="avatar-preview">
                        <div class="form-hint">JPEG, PNG или WebP, макс. 5 МБ</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="bio">О себе</label>
                        <textarea name="bio" id="bio" class="form-control" rows="4" maxlength="500"
                                  placeholder="Расскажите о своих вкусах в кино..."><?= e($user['bio']) ?></textarea>
                        <div class="form-hint">До 500 символов</div>
                    </div>

                    <button type="submit" class="btn btn--primary">Сохранить изменения</button>
                </form>
            </div>
        </div>

        
        <div class="tab-content <?= $activeTab === 'security' ? 'active' : '' ?>" id="profile-security" data-tab-panel="profile-tabs">
            <div style="max-width:500px;">
                <h3 style="font-family:var(--font-display);color:var(--cream);margin-bottom:var(--gap-lg);">Смена пароля</h3>
                <form method="POST" class="mb-xl">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Текущий пароль</label>
                        <input type="password" name="old_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Новый пароль</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Повторите новый пароль</label>
                        <input type="password" name="new_password2" class="form-control" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn--primary">Изменить пароль</button>
                </form>

                <div style="border-top:1px solid var(--border);padding-top:var(--gap-xl);">
                    <h3 style="font-family:var(--font-display);color:var(--cream);margin-bottom:var(--gap);">Двухфакторная аутентификация</h3>
                    <?php if ($user['twofa_enabled']): ?>
                    <p style="color:var(--text-muted);margin-bottom:var(--gap);">
                        <span class="badge badge--green">✓ Включена</span>
                    </p>
                    <a href="<?= APP_URL ?>/2fa-setup.php" class="btn btn--ghost btn--sm">Пересоздать коды</a>
                    <?php else: ?>
                    <p style="color:var(--text-muted);margin-bottom:var(--gap);">
                        Защитите аккаунт с помощью Google Authenticator или Authy.
                    </p>
                    <a href="<?= APP_URL ?>/2fa-setup.php" class="btn btn--primary">Включить 2FA</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</section>

<script>
(function () {
    var tabs = document.querySelector('[data-tabs="profile-tabs"]');
    if (!tabs) return;
    tabs.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('[data-tab-target]') : null;
        if (!btn || !tabs.contains(btn)) return;
        var panel = document.getElementById(btn.getAttribute('data-tab-target'));
        if (!panel) return;
        event.preventDefault();

        tabs.querySelectorAll('.tab-btn').forEach(function (item) {
            var active = item === btn;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-tab-panel="profile-tabs"]').forEach(function (item) {
            item.classList.remove('active');
        });
        panel.classList.add('active');
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
