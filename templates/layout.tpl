<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{if $meta->title}{$meta->title} — {/if}{$site.name}</title>
    {if $meta->description}
        <meta name="description" content="{$meta->description}">
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
    </header>

    <main class="main">
        {block "content"}{/block}
    </main>

    <footer class="footer">
        <p>© {$smarty.now|date_format:'Y'} {$site.name}</p>
    </footer>
</body>
</html>
