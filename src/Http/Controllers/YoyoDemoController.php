<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;

/**
 * Страница-демонстрация Yoyo + Blade.
 */
final readonly class YoyoDemoController extends Controller
{
    public function __construct(private Factory $view)
    {
    }

    public function __invoke(): Response
    {
        return $this->html($this->view->make('yoyo-demo')->render());
    }
}
