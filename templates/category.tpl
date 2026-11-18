{extends "layout.tpl"}

{block "content"}
    <header class="page-header">
        <h1 class="page-header__title">{$category.name}</h1>
        <p class="page-header__description">{$category.description}</p>
        <nav class="sort" aria-label="Сортировка статей">
            {foreach $sorts as $sort}
                <a class="sort__link" href="{$sort.url}"{if $sort.current} aria-current="true"{/if}>{$sort.label}</a>
            {/foreach}
        </nav>
    </header>

    {if $posts}
        <div class="cards">
            {foreach $posts as $post}
                {include "partials/post-card.tpl" post=$post level=2 priority=$post@first}
            {/foreach}
        </div>
        {include "partials/pagination.tpl" pagination=$pagination}
    {else}
        <p class="empty">В этой категории пока нет статей.</p>
    {/if}
{/block}
