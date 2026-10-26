<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Response;

/**
 * Контроллер вертикального среза: DI контейнера + Blade.
 */
final readonly class SpikeController
{
    public function __construct(private Factory $view)
    {
    }

    public function index(): Response
    {
        $html = $this->view->make('spike', [
            'title' => 'MyDoMo spike',
            'checks' => [
                'router' => 'ok',
                'blade' => 'ok',
                'container' => 'ok',
            ],
        ])->render();

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function show(string $id): Response
    {
        return new Response('Spike item ' . $id, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
