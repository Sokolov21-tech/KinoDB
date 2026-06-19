<?php



if (!function_exists('renderCommentBlock')) {
    function renderCommentBlock(array $comment, ?array $currentUser): void {
        $cid       = (int)($comment['id'] ?? 0);
        $uid       = (int)($currentUser['id'] ?? 0);
        $authorId  = (int)($comment['user_id'] ?? 0);
        $movieId   = (int)($comment['movie_id'] ?? 0);
        $likes     = (int)($comment['likes'] ?? $comment['likes_count'] ?? 0);
        $isLiked   = !empty($comment['liked']);
        $isSpoiler = !empty($comment['is_spoiler']);
        $canDelete = $uid && ($uid === $authorId || isMod());
        ?>
        <div class="comment" id="comment-<?= $cid ?>">
            <div class="comment-header">
                <?php if (!empty($comment['avatar'])): ?>
                <img src="<?= e($comment['avatar']) ?>" alt="" class="comment-avatar" loading="lazy">
                <?php else: ?>
                <div class="comment-avatar-placeholder"><?= mb_strtoupper(mb_substr($comment['username'] ?? '?', 0, 1)) ?></div>
                <?php endif; ?>
                <span class="comment-author"><?= e($comment['username'] ?? '') ?></span>
                <span class="comment-time" title="<?= e($comment['created_at'] ?? '') ?>">
                    <?= !empty($comment['created_at']) ? timeAgo($comment['created_at']) : 'только что' ?>
                </span>
                <?php if ($isSpoiler): ?>
                <span class="badge badge--amber">Спойлер</span>
                <?php endif; ?>
            </div>

            <div class="comment-body <?= $isSpoiler ? 'spoiler' : '' ?>" <?= $isSpoiler ? 'title="Нажмите, чтобы показать"' : '' ?>>
                <?= nl2br(e($comment['content'] ?? '')) ?>
            </div>

            <div class="comment-footer">
                <?php if ($currentUser): ?>
                <button class="comment-action <?= $isLiked ? 'liked' : '' ?>"
                        data-action="comment-like" data-comment-id="<?= $cid ?>"
                        title="Нравится">
                    ♥ <span class="like-count"><?= $likes ?></span>
                </button>
                <button class="comment-action" data-action="comment-reply-toggle">
                    ↩ Ответить
                </button>
                <?php else: ?>
                <span class="comment-action" style="cursor:default;" title="Нравится">♥ <?= $likes ?></span>
                <?php endif; ?>

                <?php if ($canDelete): ?>
                <button class="comment-action text-red" data-action="comment-delete" data-comment-id="<?= $cid ?>">
                    Удалить
                </button>
                <?php endif; ?>
            </div>

            <?php if ($currentUser): ?>
            <div class="reply-form">
                <form>
                    <?= csrfField() ?>
                    <input type="hidden" name="movie_id" value="<?= $movieId ?>">
                    <input type="hidden" name="parent_id" value="<?= $cid ?>">
                    <textarea class="reply-input" name="content" placeholder="Ваш ответ..." rows="3" maxlength="2000" required></textarea>
                    <div style="margin-top:var(--gap-sm);display:flex;gap:var(--gap-sm);">
                        <button type="submit" class="btn btn--primary btn--sm">Ответить</button>
                        <button type="button" class="btn btn--ghost btn--sm" onclick="this.closest('.reply-form').classList.remove('open')">Отмена</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!empty($comment['replies'])): ?>
            <div class="comment-replies">
                <?php foreach ($comment['replies'] as $reply): ?>
                <?php renderCommentBlock($reply, $currentUser); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

renderCommentBlock($comment, $currentUser ?? null);
