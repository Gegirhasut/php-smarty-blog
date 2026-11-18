{* Дата и просмотры статьи; minutes - время чтения, только на странице статьи *}
<p class="post-info">
    <time class="post-info__date" datetime="{$post->publishedAt->format('Y-m-d')}">{$post->publishedAt|date_ru}</time>
    <span class="post-info__views"><span class="visually-hidden">Просмотры:</span> {$post->views|number_format:0:'':' '}</span>
    {if $minutes}
        <span class="post-info__reading">{$minutes} мин чтения</span>
    {/if}
</p>
