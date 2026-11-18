{extends "layout.tpl"}

{block "content"}
    <section class="hero">
        <h1 class="hero__title">Блог о мире ИИ</h1>
        <p class="hero__text">Инструменты, исследования, большие лаборатории, деньги и люди, которые создают новое поколение ИИ.</p>
    </section>

    {foreach $sections as $section}
        {$category = $section.category}
        <section class="section" aria-labelledby="category-{$category->slug}">
            <header class="section__header">
                <h2 class="section__title" id="category-{$category->slug}">{$category->name}</h2>
                <p class="section__description">{$category->description}</p>
                {* У всех ссылок «Все статьи» одинаковый текст, aria-label говорит, куда ведёт каждая *}
                <a class="section__more" href="{$urls->category($category->slug)}" aria-label="Все статьи: {$category->name}">Все статьи</a>
            </header>
            <div class="cards">
                {foreach $section.posts as $post}
                    {include "partials/post-card.tpl" post=$post level=3 priority=($section@first && $post@first)}
                {/foreach}
            </div>
        </section>
    {foreachelse}
        <p class="empty">Статей пока нет.</p>
    {/foreach}
{/block}
