<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;

/**
 * Контроллер вертикального среза: DI контейнера + Blade.
 */
final readonly class SpikeController extends Controller
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

        return $this->html($html);
    }

    public function show(string $id): Response
    {
        return $this->text('Spike item ' . $id);
    }
}
