
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a href="<?= APP_URL ?>/" class="logo logo--footer">
                <span class="logo-icon">▶</span>
                <span class="logo-text">Kino<em>DB</em></span>
            </a>
            <p class="footer-tagline">Кино — это жизнь, из которой вырезали скуку</p>
        </div>
        <nav class="footer-nav" aria-label="Навигация в подвале">
            <div class="footer-nav-col">
                <h4 class="footer-heading">Фильмы</h4>
                <a href="<?= APP_URL ?>/search.php">Все фильмы</a>
                <a href="<?= APP_URL ?>/search.php?sort=views+DESC">Топ 100</a>
                <a href="<?= APP_URL ?>/search.php?status=upcoming">Скоро в кино</a>
                <a href="<?= APP_URL ?>/search.php?genre=documentary">Документальное</a>
            </div>
            <div class="footer-nav-col">
                <h4 class="footer-heading">Жанры</h4>
                <a href="<?= APP_URL ?>/search.php?genre=action">Экшен</a>
                <a href="<?= APP_URL ?>/search.php?genre=drama">Драма</a>
                <a href="<?= APP_URL ?>/search.php?genre=sci-fi">Фантастика</a>
                <a href="<?= APP_URL ?>/search.php?genre=thriller">Триллер</a>
            </div>
            <div class="footer-nav-col">
                <h4 class="footer-heading">Пользователь</h4>
                <a href="<?= APP_URL ?>/register.php">Регистрация</a>
                <a href="<?= APP_URL ?>/login.php">Войти</a>
                <a href="<?= APP_URL ?>/watchlist.php">Список желаемого</a>
                <a href="<?= APP_URL ?>/lists.php">Коллекции</a>
            </div>
        </nav>
    </div>
    <div class="footer-bottom">
        <p>© <?= date('Y') ?> <?= APP_NAME ?>. Все права защищены.</p>
        <p class="footer-legal">Данные о фильмах предоставлены TMDB &amp; OMDB API.</p>
    </div>
</footer>


<?php $publicBasePath = rtrim((string)(parse_url(APP_URL, PHP_URL_PATH) ?: ''), '/'); ?>
<script src="<?= e($publicBasePath) ?>/assets/js/main.js?v=<?= filemtime(ROOT_PATH . '/assets/js/main.js') ?>" defer></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
