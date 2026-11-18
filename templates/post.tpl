{extends "layout.tpl"}

{block "content"}
    {* Статья и сайдбар рядом, на узком экране сайдбар уходит под текст *}
    <div class="post-page">
        <article class="post">
            <header class="post__header">
                <ul class="post__categories">
                    {foreach $postCategories as $item}
                        <li><a class="post__category" href="/category/{$item.slug}">{$item.name}</a></li>
                    {/foreach}
                </ul>
                <h1 class="post__title">{$post.title}</h1>
                {include "partials/post-info.tpl" post=$post minutes=$readingMinutes}
                <p class="post__lead">{$post.description}</p>
            </header>

            {include "partials/cover.tpl" class="post__image" src=$post.image alt=$post.title priority=true}

            {* Блоки из ArticleText: текст выводится с обычным экранированием, без сырого HTML *}
            <div class="post__content">
                {foreach $blocks as $block}
                    {if $block.type === 'heading'}
                        <h2>{$block.text}</h2>
                    {elseif $block.type === 'list'}
                        <ul>
                            {foreach $block.items as $item}
                                <li>{$item}</li>
                            {/foreach}
                        </ul>
                    {else}
                        <p>{$block.text}</p>
                    {/if}
                {/foreach}
            </div>
        </article>

        <aside class="post-page__sidebar sidebar">
            {if $similar}
                <section class="sidebar__section" aria-labelledby="similar-title">
                    <h2 class="sidebar__title" id="similar-title">Похожие статьи</h2>
                    {foreach $similar as $item}
                        {include "partials/post-card.tpl" post=$item level=3 modifier="compact"}
                    {/foreach}
                </section>
            {/if}
            <section class="sidebar__section" aria-labelledby="categories-title">
                <h2 class="sidebar__title" id="categories-title">Категории</h2>
                <ul class="sidebar__list">
                    {foreach $categories as $item}
                        <li><a class="sidebar__link" href="/category/{$item.slug}">{$item.name}</a></li>
                    {/foreach}
                </ul>
            </section>
        </aside>
    </div>
{/block}
