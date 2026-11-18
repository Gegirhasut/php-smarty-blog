{* Последний пункт - текущая страница, ссылка на неё не нужна *}
<nav class="breadcrumbs" aria-label="Хлебные крошки">
    <ol class="breadcrumbs__list">
        {foreach $breadcrumbs as $crumb}
            <li class="breadcrumbs__item">
                {if $crumb@last}
                    <span class="breadcrumbs__current" aria-current="page">{$crumb.name}</span>
                {else}
                    <a class="breadcrumbs__link" href="{$crumb.url}">{$crumb.name}</a>
                {/if}
            </li>
        {/foreach}
    </ol>
</nav>
