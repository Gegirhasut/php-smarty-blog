<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{if $meta->title}{$meta->title} — {/if}{$site.name}</title>
    {if $meta->description}
        <meta name="description" content="{$meta->description}">
    {/if}
    {if $meta->robots}
        <meta name="robots" content="{$meta->robots}">
    {/if}
    {if $meta->canonical}
        <link rel="canonical" href="{$site.url}{$meta->canonical}">
        <meta property="og:type" content="{$meta->ogType}">
        <meta property="og:site_name" content="{$site.name}">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:url" content="{$site.url}{$meta->canonical}">
        <meta property="og:title" content="{$meta->title|default:$site.name}">
        {if $meta->description}
            <meta property="og:description" content="{$meta->description}">
        {/if}
        {if $meta->image}
            <meta property="og:image" content="{$site.url}{$meta->image}">
            <meta name="twitter:card" content="summary_large_image">
        {else}
            <meta name="twitter:card" content="summary">
        {/if}
    {/if}
    {if $meta->schema}
        <script type="application/ld+json">{$meta->jsonLd() nofilter}</script>
    {/if}
</head>
<body>
    <header class="header">
        <a class="header__logo" href="/">{$site.name}</a>
        {* Меню строится из базы, поэтому его нет на странице 404: там соединение не открывается *}
        {if $categories}
            <nav class="header__nav nav" aria-label="Категории">
                <ul class="nav__list">
                    {foreach $categories as $item}
                        <li class="nav__item">
                            <a class="nav__link" href="/category/{$item.slug}"{if $item.slug === $currentCategory} aria-current="page"{/if}>{$item.name}</a>
                        </li>
                    {/foreach}
                </ul>
            </nav>
        {/if}
    </header>

    <main class="main">
        {if $meta->breadcrumbs}
            {include "partials/breadcrumbs.tpl" breadcrumbs=$meta->breadcrumbs}
        {/if}
        {block "content"}{/block}
    </main>

    <footer class="footer">
        <a class="footer__logo" href="/">{$site.name}</a>
        <p class="footer__text">Блог об ИИ: модели и инструменты, агенты, исследования, деньги и люди.</p>
        <p class="footer__copyright">© {$smarty.now|date_format:'Y'} {$site.name}</p>
    </footer>
</body>
</html>
