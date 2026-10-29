<?php

declare(strict_types=1);

namespace LaIOT\Yoyo;

use Clickfwd\Yoyo\Component;
use Clickfwd\Yoyo\Services\Response as YoyoResponse;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LaIOT\Services\Auth\Authorization;
use LaIOT\Services\Docs\MarkdownRenderer;
use LaIOT\Services\Updates\UpdateService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Прямой вызов через /yoyo тоже проверяет settings.manage, а изменение — POST.
 * 2026-10-29 — предпросмотр, подтверждение и отдельный аварийный доступ.
 */
final class SystemUpdates extends Component
{
    public string $channel = '';
    public string $selected = '';
    /** @var list<string> */
    protected $props = ['channel', 'selected'];
    private UpdateService $updates;
    private Authorization $authorization;
    private Factory $validator;
    private Request $httpRequest;
    /** @var array<string, mixed> */
    private array $preview = [];
    private string $error = '';

    public function mount(UpdateService $updates, Authorization $authorization, Factory $validator, Request $request): void
    {
        $this->updates = $updates;
        $this->authorization = $authorization;
        $this->validator = $validator;
        $this->httpRequest = $request;
        $this->authorize();
        if (!in_array($this->channel, ['main', 'beta'], true)) {
            $this->channel = (string)($updates->config['updates']['channel'] ?? 'beta');
        }
    }

    public function refresh(): void
    {
        $this->authorize(true);
        try {
            $this->updates->refresh();
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'success', 'text' => 'Список релизов обновлён.']);
        } catch (Throwable $error) {
            $this->fail($error);
        }
    }

    public function changeChannel(?string $channel = null): void
    {
        $this->authorize();
        $validation = $this->validator->make(['channel' => $channel ?? $this->getQueryParam('release_channel', '')], ['channel' => [Rule::in(['main', 'beta'])]]);
        if (!$validation->fails()) {
            $this->channel = (string)$validation->validated()['channel'];
            $this->selected = '';
        }
    }

    public function inspect(string $id): void
    {
        $this->authorize();
        $this->selected = $id;
        try {
            $this->preview = $this->updates->preview($id);
        } catch (Throwable $error) {
            $this->fail($error);
        }
    }

    public function start(): void
    {
        $this->authorize(true);
        $validation = $this->validator->make([
            'release' => $this->getQueryParam('release', ''), 'confirmed' => $this->getQueryParam('confirmed', ''), 'channel' => $this->channel,
        ], ['release' => ['required', 'string', 'max:120'], 'confirmed' => ['accepted'], 'channel' => [Rule::in(['main', 'beta'])]], [
            'confirmed.accepted' => 'Подтвердите остановку системы и возможную потерю более новых данных БД.',
        ]);
        if ($validation->fails()) {
            $this->error = $validation->errors()->first();
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'danger', 'text' => $this->error]);
            return;
        }
        try {
            $key = $this->updates->queue((string)$validation->validated()['release'], true);
            YoyoResponse::getInstance()->header('Set-Cookie', (string)Cookie::create('laiot_update', $key)
                ->withPath('/admin/updates/recovery')->withExpires(time() + 7 * 86400)->withSecure($this->httpRequest->isSecure())
                ->withHttpOnly(true)->withSameSite('strict'));
            YoyoResponse::getInstance()->redirect('/admin/updates/recovery');
        } catch (Throwable $error) {
            $this->fail($error);
        }
    }

    public function render(): mixed
    {
        $this->authorize();
        $notes = $this->preview === [] ? '' : (new MarkdownRenderer())->render((string)($this->preview['release']['notes'] ?? ''), 'updates', [])['html'];
        return $this->view('system-updates', [
            'current' => $this->updates->current(), 'releases' => $this->updates->listing($this->channel),
            'operation' => $this->updates->state->read(), 'preview' => $this->preview, 'notes' => $notes,
            'repository' => (string)($this->updates->config['updates']['repository'] ?? 'laiot-smarthome/laiot'), 'error' => $this->error, 'channel' => $this->channel,
        ]);
    }

    private function authorize(bool $post = false): void
    {
        if (!$this->authorization->can('settings.manage') || ($post && !$this->httpRequest->isMethod('POST'))) {
            throw new HttpException(403, 'Недостаточно прав или неверный метод запроса.');
        }
    }

    private function fail(Throwable $error): void
    {
        $this->error = $error->getMessage();
        $this->dispatchBrowserEvent('md:notify', ['tone' => 'danger', 'text' => $this->error]);
    }
}
