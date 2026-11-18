{* Иконка категории по её slug - оформление, а не данные, поэтому живёт в шаблоне. У новой категории - общая иконка *}
{$icons = [
    'ii-agenty' => 'bot',
    'instrumenty' => 'wrench',
    'issledovaniya' => 'flask-conical',
    'laboratorii' => 'building-2',
    'dengi' => 'coins',
    'lyudi-i-ii' => 'users'
]}
{include "partials/icon.tpl" name=$icons[$slug]|default:'newspaper'}
