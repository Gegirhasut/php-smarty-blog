{* Дата и просмотры статьи; minutes - время чтения, только на странице статьи; class - элемент блока вокруг (BEM-микс) *}
<p class="post-info{if $class} {$class}{/if}">
    <time class="post-info__item" datetime="{$post->publishedAt->format('Y-m-d')}">{include "partials/icon.tpl" name="calendar"}{$post->publishedAt|date_ru}</time>
    <span class="post-info__item">{include "partials/icon.tpl" name="eye"}<span class="visually-hidden">Просмотры:</span> {$post->views|number_format:0:'':' '}</span>
    {if $minutes}
        <span class="post-info__item">{include "partials/icon.tpl" name="clock"}{$minutes} мин чтения</span>
    {/if}
</p>
