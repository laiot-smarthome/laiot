<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Welcome;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;

/**
 * Контроллер модуля-образца: namespace, views и routes регистрируются
 * ядром из module.json, без правки Application.
 */
final readonly class WelcomeController extends Controller
{
    public function __construct(private Factory $view)
    {
    }

    public function index(): Response
    {
        return $this->html($this->view->make('welcome::index', [
            'module' => 'welcome',
        ])->render());
    }

    public function hello(string $name): Response
    {
        return $this->html($this->view->make('welcome::hello', [
            'name' => $name,
        ])->render());
    }
}
