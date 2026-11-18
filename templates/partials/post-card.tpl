{* Карточка статьи. post - статья, level - уровень заголовка на странице, priority - первая картинка на экране,
   modifier - вариант карточки (compact в сайдбаре: без описания).
   У картинки пустой alt: рядом ссылка с названием статьи, и читалка экрана не повторит его дважды.
   У статьи без обложки - заглушка её основной категории того же размера.
   Ссылка в заголовке растянута CSS на всю карточку *}
<article class="card{if $modifier} card--{$modifier}{/if}">
    {if $post->image}
        {include "partials/cover.tpl" class="card__image" src=$post->image alt="" priority=$priority}
    {else}
        {include "partials/placeholder.tpl" class="card__image" slug=$post->mainCategorySlug priority=$priority}
    {/if}
    <div class="card__body">
        <h{$level} class="card__title"><a class="card__link" href="{$urls->post($post->slug)}">{$post->title}</a></h{$level}>
        {if $modifier !== 'compact'}
            <p class="card__description">{$post->description}</p>
        {/if}
        {include "partials/post-info.tpl" post=$post class="card__info"}
    </div>
</article>
