{extends "layout.tpl"}

{block "content"}
    <div class="post-page">
        <article class="post-page__main post">
            <header class="post__header">
                <ul class="post__categories chips">
                    {foreach $postCategories as $item}
                        <li><a class="chip{if $item@first} chip--primary{/if}" href="{$urls->category($item->slug)}">{$item->name}</a></li>
                    {/foreach}
                </ul>
                <h1 class="post__title">{$post->title}</h1>
                {include "partials/post-info.tpl" post=$post minutes=$readingMinutes}
                <p class="post__lead">{$post->description}</p>
            </header>

            {if $post->image}
                {include "partials/cover.tpl" class="post__image" src=$post->image alt=$post->title priority=true}
            {/if}

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
                    <div class="sidebar__posts">
                        {foreach $similar as $item}
                            {include "partials/post-card.tpl" post=$item level=3 modifier="compact"}
                        {/foreach}
                    </div>
                </section>
            {/if}
            <section class="sidebar__section" aria-labelledby="categories-title">
                <h2 class="sidebar__title" id="categories-title">Категории</h2>
                <ul class="sidebar__list">
                    {foreach $categories as $item}
                        <li>
                            <a class="sidebar__link" href="{$urls->category($item->slug)}">
                                <span class="icon-tile icon-tile--sm">{include "partials/category-icon.tpl" slug=$item->slug}</span>
                                {$item->name}
                            </a>
                        </li>
                    {/foreach}
                </ul>
            </section>
        </aside>
    </div>
{/block}
