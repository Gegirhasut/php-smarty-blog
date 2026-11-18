{* Карточка статьи. post - статья, level - уровень заголовка на странице, priority - первая картинка на экране,
   modifier - вариант карточки (compact в сайдбаре).
   У картинки пустой alt: рядом ссылка с названием статьи, и читалка экрана не повторит его дважды *}
<article class="card{if $modifier} card--{$modifier}{/if}">
    {include "partials/cover.tpl" class="card__image" src=$post->image alt="" priority=$priority}
    <div class="card__body">
        <h{$level} class="card__title"><a class="card__link" href="{$urls->post($post->slug)}">{$post->title}</a></h{$level}>
        <p class="card__description">{$post->description}</p>
        {include "partials/post-info.tpl" post=$post}
    </div>
</article>
