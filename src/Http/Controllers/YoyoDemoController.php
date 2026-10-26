<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\Session\SessionStore;

/**
 * Страница-демонстрация Yoyo + Blade.
 */
final readonly class YoyoDemoController extends Controller
{
    public function __construct(
        private Factory $view,
        private SessionStore $session
    ) {
    }

    public function __invoke(): Response
    {
        return $this->html($this->view->make('yoyo-demo', [
            'csrf' => $this->session->csrfToken(),
        ])->render());
    }
}
