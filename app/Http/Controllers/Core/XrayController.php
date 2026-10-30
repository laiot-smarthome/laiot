<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers\Core;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Единый вход в диагностику ядра.
 * 2026-10-30 — страница X-ray; интерактив и действия обслуживает Yoyo.
 */
final readonly class XrayController extends Controller
{
    public function __construct(private Factory $view, private PanelRenderer $panel, private ValidationFactory $validation)
    {
    }

    public function index(Request $request): Response
    {
        $input = $this->validation->make(['tab' => $request->query('tab', 'debug')], [
            'tab' => ['required', Rule::in(['debug', 'property', 'script', 'event', 'timers', 'cycles'])],
        ])->validate();

        return $this->panel->render(new PanelPage('X-Ray', $this->view->make('pages.xray.index', ['tab' => $input['tab']])->render(), 'xray'));
    }
}
