{* Номера страниц из Pagination::links(); null в списке - пропуск. Одна страница - навигация не нужна *}
{if $pagination.prev || $pagination.next}
    <nav class="pagination" aria-label="Страницы">
        {if $pagination.prev}
            <a class="pagination__arrow pagination__arrow--prev" href="{$pagination.prev}" rel="prev">Назад</a>
        {/if}
        <ul class="pagination__list">
            {foreach $pagination.pages as $link}
                <li class="pagination__item">
                    {if $link}
                        <a class="pagination__link" href="{$link.url}"{if $link.current} aria-current="page"{/if}>{$link.number}</a>
                    {else}
                        <span class="pagination__gap">…</span>
                    {/if}
                </li>
            {/foreach}
        </ul>
        {if $pagination.next}
            <a class="pagination__arrow pagination__arrow--next" href="{$pagination.next}" rel="next">Вперёд</a>
        {/if}
    </nav>
{/if}
