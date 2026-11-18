{* Оформление категорий из сидера по slug - оформление, а не данные, поэтому живёт в шаблоне: иконка Lucide
   и заглушка обложки public/assets/img/placeholders/{slug}.webp, нарисованная для каждой из них.
   Карту получает шаблон, который включил этот *}
{$categoryIcons = [
    'ii-agenty' => 'bot',
    'instrumenty' => 'wrench',
    'issledovaniya' => 'flask-conical',
    'laboratorii' => 'building-2',
    'dengi' => 'coins',
    'lyudi-i-ii' => 'users'
] scope='parent'}
