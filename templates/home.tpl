{extends "layout.tpl"}

{block "content"}
    <section class="hero" aria-labelledby="hero-title">
        {* Картинка - фон баннера и смысла не несёт, поэтому alt пустой. Она первая на экране и грузится сразу *}
        <img class="hero__image" src="{'/assets/img/hero.webp'|asset}" alt="" width="1600" height="533" fetchpriority="high">
        <div class="hero__body">
            <h1 class="hero__title" id="hero-title">Блог о мире ИИ</h1>
            <p class="hero__text">Инструменты, исследования, большие лаборатории, деньги и люди, которые создают новое поколение ИИ.</p>
            {if $sections}
                <a class="hero__button button button--accent" href="#articles">Новые статьи{include "partials/icon.tpl" name="arrow-right"}</a>
            {/if}
        </div>
    </section>

    <div class="sections" id="articles">
        {foreach $sections as $section}
            {$category = $section.category}
            <section class="section" aria-labelledby="category-{$category->slug}">
                <header class="section__header">
                    <span class="section__icon icon-tile">{include "partials/category-icon.tpl" slug=$category->slug}</span>
                    <h2 class="section__title" id="category-{$category->slug}">{$category->name}</h2>
                    <p class="section__description">{$category->description}</p>
                    {* У всех ссылок «Все статьи» одинаковый текст, aria-label говорит, куда ведёт каждая.
                       На телефоне видна только стрелка: с подписью шапка секции не помещается в ширину *}
                    <a class="section__more button button--light button--small" href="{$urls->category($category->slug)}" aria-label="Все статьи: {$category->name}"><span class="section__more-label">Все статьи</span>{include "partials/icon.tpl" name="arrow-right"}</a>
                </header>
                <div class="cards cards--row">
                    {foreach $section.posts as $post}
                        {include "partials/post-card.tpl" post=$post level=3}
                    {/foreach}
                </div>
            </section>
        {foreachelse}
            <p class="empty">Статей пока нет.</p>
        {/foreach}
    </div>
{/block}
