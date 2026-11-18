{* Заглушка вместо обложки - картинка основной категории статьи. Заглушки нарисованы для категорий из сидера,
   у другой категории (или у статьи без категорий) - общая, с роботом: адрес всегда на существующий файл.
   class, priority - как у cover.tpl; картинка декоративная, alt пустой *}
{$placeholders = [
    'ii-agenty' => true,
    'instrumenty' => true,
    'issledovaniya' => true,
    'laboratorii' => true,
    'dengi' => true,
    'lyudi-i-ii' => true
]}
{$name = $slug|default:''}
{if !isset($placeholders[$name])}
    {$name = 'ii-agenty'}
{/if}
{include "partials/cover.tpl" class=$class src="/assets/img/placeholders/{$name}.webp"|asset alt="" priority=$priority}
