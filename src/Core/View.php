<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeInterface;
use Smarty\Smarty;

final class View
{
    private const MONTHS = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    private readonly Smarty $smarty;

    /**
     * @param string $publicDir корень сайта: модификатор asset берёт оттуда время изменения файла
     */
    public function __construct(
        string $templateDir,
        string $compileDir,
        private readonly string $publicDir,
        bool $debug,
    ) {
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($templateDir);
        $this->smarty->setCompileDir($compileDir);
        $this->smarty->setEscapeHtml(true);
        $this->smarty->setCompileCheck($debug ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF);
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'date_ru', $this->formatDate(...));
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'asset', $this->assetUrl(...));
    }

    public function share(string $name, mixed $value): void
    {
        $this->smarty->assign($name, $value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $tpl = $this->smarty->createTemplate($template);
        $tpl->assign($data);

        return $tpl->fetch();
    }

    /**
     * «17 ноя 2026»: у date() и date_format нет русских названий месяцев.
     */
    private function formatDate(DateTimeInterface $date): string
    {
        return $date->format('j ') . self::MONTHS[(int) $date->format('n') - 1] . $date->format(' Y');
    }

    /**
     * «/assets/css/app.css?v=1763400000»: у изменённого файла новый адрес, поэтому статике можно отдавать
     * кэш на год. Файла нет (CSS ещё не собран) - адрес без версии, страница всё равно откроется.
     */
    private function assetUrl(string $path): string
    {
        $file = $this->publicDir . $path;
        $time = is_file($file) ? filemtime($file) : false;

        return $time === false ? $path : $path . '?v=' . $time;
    }
}
