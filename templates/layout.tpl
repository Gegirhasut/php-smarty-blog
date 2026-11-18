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
        <link rel="canonical" href="{$urls->absolute($meta->canonical)}">
        <meta property="og:type" content="{$meta->ogType}">
        <meta property="og:site_name" content="{$site.name}">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:url" content="{$urls->absolute($meta->canonical)}">
        <meta property="og:title" content="{$meta->title|default:$site.name}">
        {if $meta->description}
            <meta property="og:description" content="{$meta->description}">
        {/if}
        {if $meta->image}
            <meta property="og:image" content="{$urls->absolute($meta->image)}">
            <meta name="twitter:card" content="summary_large_image">
        {else}
            <meta name="twitter:card" content="summary">
        {/if}
    {/if}
    {* Шрифт нужен первому экрану: браузер начинает качать его вместе с CSS, а не после разбора стилей.
       Адреса - как в @font-face, иначе файл скачается дважды *}
    <link rel="preload" href="/assets/fonts/manrope-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{'/assets/css/app.css'|asset}">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#3b2bd9">
    <link rel="alternate" type="application/rss+xml" title="{$site.name}" href="{$urls->feed()}">
    {if $meta->schema}
        <script type="application/ld+json">{$meta->jsonLd() nofilter}</script>
    {/if}
</head>
<body>
    <a class="skip-link" href="#main">Перейти к содержимому</a>

    <header class="header">
        <div class="header__inner container">
            <a class="header__logo" href="{$urls->home()}">
                <img src="{'/assets/img/logo.webp'|asset}" alt="{$site.name}" width="405" height="96">
            </a>
            {* Меню строится из базы, поэтому его нет на странице 404: там соединение не открывается *}
            {if $categories}
                {* На телефоне меню открывает флажок, без JS: его состояние CSS видит через :checked *}
                <input class="header__toggle visually-hidden" type="checkbox" id="menu-toggle">
                <label class="header__burger" for="menu-toggle">
                    {include "partials/icon.tpl" name="menu"}
                    {include "partials/icon.tpl" name="x"}
                    <span class="visually-hidden">Меню</span>
                </label>
                <nav class="header__nav nav" aria-label="Категории">
                    <ul class="nav__list">
                        {foreach $categories as $item}
                            <li class="nav__item">
                                <a class="nav__link" href="{$urls->category($item->slug)}"{if $item->slug === $currentCategory} aria-current="page"{/if}>{$item->name}</a>
                            </li>
                        {/foreach}
                    </ul>
                </nav>
            {/if}
        </div>
    </header>

    <main class="main container" id="main">
        {if $meta->breadcrumbs}
            {include "partials/breadcrumbs.tpl" breadcrumbs=$meta->breadcrumbs}
        {/if}
        {block "content"}{/block}
    </main>

    <footer class="footer">
        <div class="footer__inner container">
            <a class="footer__logo" href="{$urls->home()}">
                <img src="{'/assets/img/logo.webp'|asset}" alt="{$site.name}" width="405" height="96">
            </a>
            <p class="footer__text">{$site.description}</p>
            <a class="footer__link" href="{$urls->feed()}">{include "partials/icon.tpl" name="rss"}RSS</a>
            <p class="footer__copyright">© {$smarty.now|date_format:'Y'} {$site.name}</p>
        </div>
    </footer>
</body>
</html>
