{* Номера страниц из Pagination::links(); null в списке - пропуск. Одна страница - навигация не нужна.
   На телефоне у «Назад» и «Вперёд» видны только стрелки, текст остаётся для читалок экрана *}
{if $pagination.prev || $pagination.next}
    <nav class="pagination" aria-label="Страницы">
        {if $pagination.prev}
            <a class="pagination__arrow" href="{$pagination.prev}" rel="prev">{include "partials/icon.tpl" name="arrow-left"}<span class="pagination__label">Назад</span></a>
        {/if}
        <ul class="pagination__list">
            {foreach $pagination.pages as $link}
                <li>
                    {if $link}
                        <a class="pagination__link" href="{$link.url}"{if $link.current} aria-current="page"{/if}>{$link.number}</a>
                    {else}
                        <span class="pagination__gap">…</span>
                    {/if}
                </li>
            {/foreach}
        </ul>
        {if $pagination.next}
            <a class="pagination__arrow" href="{$pagination.next}" rel="next"><span class="pagination__label">Вперёд</span>{include "partials/icon.tpl" name="arrow-right"}</a>
        {/if}
    </nav>
{/if}
