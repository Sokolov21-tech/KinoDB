<?php
require_once 'includes/config.php';
require_once 'includes/security.php';

$code = sanitizeInt($_GET['code'] ?? 0);
$allowed = [403, 404, 429, 500];
if (!in_array($code, $allowed, true)) { $code = 404; }

http_response_code($code);

$messages = [
    403 => ['Доступ запрещён',     'У вас нет прав для просмотра этой страницы.'],
    404 => ['Страница не найдена', 'Запрошенная страница не существует или была удалена.'],
    429 => ['Слишком много запросов', 'Вы превысили допустимое число запросов. Подождите немного.'],
    500 => ['Ошибка сервера',      'Произошла внутренняя ошибка. Попробуйте позже.'],
];

[$title, $desc] = $messages[$code];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $code ?> — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Syncopate:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .error-page { text-align:center; max-width:480px; padding: var(--gap-xl); }
        .error-code { font-family: var(--font-display); font-size: 8rem; font-weight:700;
                      color:var(--amber); line-height:1; margin-bottom:var(--gap); }
        .error-title { font-family: var(--font-display); font-size:1.8rem; color:var(--cream);
                       margin-bottom:var(--gap); }
        .error-desc { color:var(--text-muted); margin-bottom:var(--gap-xl); line-height:1.7; }
    </style>
</head>
<body>
<div class="grain-overlay" aria-hidden="true"></div>
<div class="error-page">
    <div class="error-code"><?= $code ?></div>
    <h1 class="error-title"><?= $title ?></h1>
    <p class="error-desc"><?= $desc ?></p>
    <a href="<?= APP_URL ?>/" class="btn btn--primary">На главную</a>
</div>
</body>
</html>
