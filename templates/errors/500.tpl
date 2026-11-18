{* Без layout, чтобы страница открылась, даже если ошибка в самом layout *}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ошибка сервера — {$site.name}</title>
    <link rel="stylesheet" href="{'/assets/css/app.css'|asset}">
</head>
<body>
    <main class="main container">
        <section class="message">
            <p class="message__code">500</p>
            <h1 class="message__title">Что-то пошло не так</h1>
            <p class="message__text">Попробуйте обновить страницу чуть позже или вернитесь на главную.</p>
            <a class="button button--primary" href="/">Перейти на главную</a>
            {if $error}
                <pre class="message__error">{$error}</pre>
            {/if}
        </section>
    </main>
</body>
</html>
