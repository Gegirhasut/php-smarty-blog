{* Иконка категории по её slug (карта - в category-art.tpl). У новой категории - общая иконка *}
{include "partials/category-art.tpl"}
{include "partials/icon.tpl" name=$categoryIcons[$slug]|default:'newspaper'}
