<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use Smarty\Smarty;

final class View
{
    private const MONTHS = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    private readonly Smarty $smarty;

    public function __construct(string $templateDir, string $compileDir, bool $debug)
    {
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($templateDir);
        $this->smarty->setCompileDir($compileDir);
        $this->smarty->setEscapeHtml(true);
        $this->smarty->setCompileCheck($debug ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF);
        $this->smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'date_ru', $this->formatDate(...));
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
    private function formatDate(string $date): string
    {
        $time = new DateTimeImmutable($date);

        return $time->format('j ') . self::MONTHS[(int) $time->format('n') - 1] . $time->format(' Y');
    }
}
