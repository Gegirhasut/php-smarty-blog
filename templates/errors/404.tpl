{extends "layout.tpl"}

{block "content"}
    <section class="message">
        <p class="message__code">404</p>
        <h1 class="message__title">Страница не найдена</h1>
        <p class="message__text">Возможно, её удалили или в адресе опечатка.</p>
        <a class="button button--primary" href="{$urls->home()}">Перейти на главную</a>
    </section>
{/block}
