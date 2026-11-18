<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Meta;
use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function __construct(private readonly View $view)
    {
    }

    public function index(): Response
    {
        return new Response($this->view->render('home.tpl', [
            'meta' => new Meta(
                description: 'Блог об ИИ: новые модели и инструменты, агенты, исследования, деньги и люди.',
                canonical: '/',
            ),
        ]));
    }
}
