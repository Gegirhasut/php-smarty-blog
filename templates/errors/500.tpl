{* Без layout, чтобы страница открылась, даже если ошибка в самом layout *}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ошибка сервера — {$site.name}</title>
</head>
<body>
    <h1>Что-то пошло не так</h1>
    <p>Попробуйте обновить страницу чуть позже или вернитесь <a href="/">на главную</a>.</p>
    {if $error}
        <pre>{$error}</pre>
    {/if}
</body>
</html>
