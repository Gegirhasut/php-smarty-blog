{* Заглушка вместо обложки - картинка основной категории статьи. Заглушки нарисованы для категорий
   из category-art.tpl, у другой категории (или у статьи без категорий) - общая, с роботом: адрес всегда на существующий файл.
   class, priority - как у cover.tpl; картинка декоративная, alt пустой *}
{include "partials/category-art.tpl"}
{$name = $slug|default:''}
{if !isset($categoryIcons[$name])}
    {$name = 'ii-agenty'}
{/if}
{include "partials/cover.tpl" class=$class src="/assets/img/placeholders/{$name}.webp"|asset alt="" priority=$priority}
