<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use Illuminate\Http\Request;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Settings\SettingsSchema;
use MajorDoMo\Settings\SettingsService;
use MajorDoMo\Settings\SettingsValidationException;
use RuntimeException;

/**
 * Форма окружения: значения и секреты не сериализуются в публичное состояние Yoyo.
 *
 * 2026-10-27 — разделы, поиск, ошибки полей и сохранение .env через POST.
 */
final class SystemSettings extends Component
{
    public string $group = 'general';
    public string $search = '';

    /** @var list<string> */
    protected $props = ['group', 'search'];

    private ?SettingsService $settings = null;
    private ?Authorization $authorization = null;
    private ?Request $httpRequest = null;
    private string $revision = '';
    private string $error = '';
    private string $message = '';

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $input = [];

    public function mount(SettingsService $settings, Authorization $authorization, Request $request): void
    {
        $this->settings = $settings;
        $this->authorization = $authorization;
        $this->httpRequest = $request;
    }

    public function findSettings(): void
    {
        $this->search = trim((string)$this->getQueryParam('q', ''));
    }

    public function save(): void
    {
        if ($this->authorization?->can('settings.manage') !== true || $this->settings === null) {
            $this->error = 'Недостаточно прав для изменения настроек.';
            return;
        }
        if ($this->httpRequest?->isMethod('POST') !== true) {
            $this->error = 'Сохранение допускается только через POST с CSRF-защитой.';
            return;
        }
        $input = $this->getQueryParam('values', []);
        $clear = $this->getQueryParam('clear', []);
        $this->revision = (string)$this->getQueryParam('revision', '');
        if (!is_array($input) || !is_array($clear)) {
            $this->error = 'Некорректный формат формы.';
            return;
        }
        // При ошибке сохраняем обычные поля. Пароли никогда не возвращаем
        // в HTML, даже если администратор только что ввёл их сам.
        foreach (SettingsSchema::fields() as $key => $field) {
            if ($field['type'] !== 'secret' && isset($input[$key]) && is_string($input[$key])) {
                $this->input[$key] = $input[$key];
            }
        }
        try {
            $this->settings->save($this->group, $input, $clear, $this->revision, $this->authorization->user() ?? []);
            $this->message = 'Настройки сохранены в .env. Веб-приложение прочитает их при следующем запросе. Перезапустите фоновые процессы, чтобы они использовали новые значения.';
            $this->input = [];
            $this->revision = '';
        } catch (SettingsValidationException $error) {
            $this->errors = $error->errors;
            $this->error = $error->getMessage();
        } catch (RuntimeException $error) {
            $this->error = $error->getMessage();
        }
    }

    public function render(): mixed
    {
        $allowed = $this->authorization?->can('settings.manage') === true;
        $snapshot = ['revision' => '', 'fields' => []];
        if ($allowed && $this->settings !== null) {
            try {
                $snapshot = $this->settings->snapshot();
            } catch (RuntimeException $error) {
                $this->error = $error->getMessage();
            }
        }
        $groups = SettingsSchema::groups();
        $group = isset($groups[$this->group]) ? $this->group : 'general';
        $this->group = $group;
        $fields = [];
        foreach ($snapshot['fields'] as $key => $field) {
            if ($this->search !== '') {
                $haystack = $key . ' ' . $field['label'] . ' ' . $field['description'];
                if (mb_stripos($haystack, $this->search) === false) {
                    continue;
                }
            } elseif ($field['group'] !== $group) {
                continue;
            }
            if (isset($this->input[$key])) {
                $field['value'] = $this->input[$key];
            }
            $fields[$key] = $field;
        }

        return $this->view('system-settings', [
            'allowed' => $allowed, 'groups' => $groups, 'group' => $group, 'search' => $this->search,
            'fields' => $fields, 'revision' => $this->revision !== '' ? $this->revision : $snapshot['revision'],
            'error' => $this->error, 'errors' => $this->errors, 'message' => $this->message,
        ]);
    }
}
