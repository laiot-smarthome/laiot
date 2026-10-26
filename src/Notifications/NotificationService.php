<?php

declare(strict_types=1);

namespace MajorDoMo\Notifications;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Support\Clock;
use Throwable;

/**
 * Центр уведомлений MyDoMo.
 *
 * Модуль или сценарий оставляет короткое сообщение; админка показывает
 * непрочитанные в колокольчике и drawer, а прочтение — явное действие.
 * Уведомление не влияет на основной сценарий: ошибки записи в журнал не
 * пробрасываются в вызывающий код.
 *
 * 2026-10-26 — сервис создан для alpha-shell parity: колокольчик, drawer и API.
 */
final readonly class NotificationService
{
    public const MAX_UNREAD = 10;
    public const PAGE_SIZE = 50;
    public const MAX_LIST = 500;
    private const MESSAGE_MAX = 255;
    private const TITLE_MAX = 200;
    private const DETAILS_MAX = 16000;
    private const URL_MAX = 2048;
    private const LABEL_MAX = 100;
    private const KEY_MAX = 190;
    private const MODULE_PATTERN = '/^[a-z][a-z0-9_-]{1,63}$/D';
    private const TYPES = ['info', 'success', 'warning', 'danger'];
    private const RANKS = ['info' => 0, 'success' => 0, 'warning' => 1, 'danger' => 2];

    public function __construct(
        private DatabaseManager $database,
        private ModuleManager $modules,
        private Clock $clock
    ) {
    }

    public function hasTable(): bool
    {
        try {
            return $this->database->connection()->getSchemaBuilder()->hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $options key/title/details/url/label
     * @return array{status:bool,id?:int,error?:string}
     */
    public function send(string $module, string $message, string $type = 'info', array $options = []): array
    {
        $module = trim($module);
        if (!preg_match(self::MODULE_PATTERN, $module)) {
            return ['status' => false, 'error' => 'Некорректный код модуля.'];
        }
        $message = trim(strip_tags($message));
        if ($message === '' || mb_strlen($message) > self::MESSAGE_MAX) {
            return ['status' => false, 'error' => 'Сообщение должно быть от 1 до ' . self::MESSAGE_MAX . ' символов.'];
        }
        $type = $this->type($type);
        $payload = $this->payload($options);
        if (isset($payload['error'])) {
            return ['status' => false, 'error' => $payload['error']];
        }
        $key = (string)($payload['key'] ?? '');
        unset($payload['key']);
        $hasOptions = array_filter($payload, static fn (string $value): bool => $value !== '') !== [];
        if (!$this->hasTable()) {
            return ['status' => false, 'error' => 'Таблица уведомлений ещё не создана.'];
        }

        $connection = $this->database->connection();
        $query = $connection->table('notifications')
            ->where('module', $module)
            ->whereNull('read_at')
            ->whereNull('deleted_at');
        $identity = $key !== ''
            ? (clone $query)->where('notification_key', $key)
            : (clone $query)->where('message', $message)->where('type', $type);
        $existing = $identity->orderBy('id')->first();
        if ($existing !== null) {
            return [
                'status' => false,
                'error' => 'Такое уведомление уже есть. ID: ' . (int)$existing->id,
                'id' => (int)$existing->id,
            ];
        }
        if ($query->count() >= self::MAX_UNREAD) {
            return ['status' => false, 'error' => 'Больше ' . self::MAX_UNREAD . ' непрочитанных уведомлений модуля.'];
        }

        $now = $this->clock->nowString();
        try {
            $id = (int)$connection->table('notifications')->insertGetId([
                'module' => $module,
                'type' => $type,
                'message' => $message,
                'title' => $hasOptions ? ($payload['title'] ?? null) : null,
                'details' => $hasOptions ? ($payload['details'] ?? null) : null,
                'url' => $hasOptions ? ($payload['url'] ?? null) : null,
                'label' => $hasOptions ? ($payload['label'] ?? null) : null,
                'notification_key' => $key !== '' ? $key : null,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        } catch (Throwable $error) {
            return ['status' => false, 'error' => 'Не удалось сохранить уведомление: ' . $error->getMessage()];
        }

        return ['status' => true, 'id' => $id];
    }

    /**
     * @return array{
     *     total:int,
     *     counts:array<string,int>,
     *     levels:array<string,string>,
     *     severity:string,
     *     items:list<array{id:int,module:string,module_title:string,module_url:string,message:string,type:string,added:string,title:string,details:string,url:string,label:string}>,
     *     offset:int
     * }
     */
    public function listing(string $module = '', int $offset = 0): array
    {
        $offset = max(0, $offset);
        $summary = $this->summary($module);
        if (!$this->hasTable()) {
            return $summary + ['items' => [], 'offset' => $offset];
        }

        $query = $this->database->connection()->table('notifications')
            ->whereNull('read_at')
            ->whereNull('deleted_at');
        if ($module !== '') {
            $query->where('module', $module);
        }
        $rows = $query->orderByDesc('id')->limit(self::MAX_LIST)->get()->all();
        usort($rows, function (object $a, object $b): int {
            $rank = self::RANKS[$this->type((string)$b->type)] <=> self::RANKS[$this->type((string)$a->type)];
            if ($rank !== 0) {
                return $rank;
            }

            return (int)$b->id <=> (int)$a->id;
        });
        $rows = array_slice($rows, $offset, self::PAGE_SIZE);

        return $summary + [
            'items' => array_map(fn (object $row): array => $this->present($row), $rows),
            'offset' => $offset,
        ];
    }

    /**
     * @return array{total:int,counts:array<string,int>,levels:array<string,string>,severity:string}
     */
    public function summary(string $module = ''): array
    {
        $counts = [];
        $levels = [];
        $severity = 'info';
        if (!$this->hasTable()) {
            return ['total' => 0, 'counts' => [], 'levels' => [], 'severity' => $severity];
        }

        $rows = $this->database->connection()->table('notifications')
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->get(['module', 'type'])
            ->all();
        foreach ($rows as $row) {
            $name = (string)$row->module;
            $type = $this->type((string)$row->type);
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            if (!isset($levels[$name]) || self::RANKS[$type] > self::RANKS[$levels[$name]]) {
                $levels[$name] = $type;
            }
            if (($module === '' || $module === $name) && self::RANKS[$type] > self::RANKS[$severity]) {
                $severity = $type;
            }
        }
        $total = 0;
        foreach ($counts as $name => $count) {
            if ($module === '' || $module === $name) {
                $total += $count;
            }
        }

        return ['total' => $total, 'counts' => $counts, 'levels' => $levels, 'severity' => $severity];
    }

    public function dismiss(?int $id, ?string $module = null): int
    {
        if (!$this->hasTable()) {
            return 0;
        }
        if ($id === null && ($module === null || $module === '')) {
            return 0;
        }

        $query = $this->database->connection()->table('notifications')
            ->whereNull('read_at')
            ->whereNull('deleted_at');
        if ($id !== null) {
            $query->where('id', $id);
        }
        if ($module !== null && $module !== '') {
            $query->where('module', $module);
        }

        $now = $this->clock->nowString();

        return $query->update(['read_at' => $now, 'updated_at' => $now]);
    }

    /**
     * Внутренняя абсолютная ссылка; внешние схемы и `//` не допускаются.
     */
    public static function internalUrl(string $url): string
    {
        if ($url === '' || mb_strlen($url) > self::URL_MAX) {
            return '';
        }
        $decoded = rawurldecode($url);
        if (preg_match('/%(?![a-f0-9]{2})/i', $url)
            || !str_starts_with($decoded, '/')
            || str_starts_with($decoded, '//')
            || preg_match('/[\\\\\x00-\x20\x7f]/', $decoded)) {
            return '';
        }

        return $url;
    }

    public static function type(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'default' || $type === 'error' || $type === 'fail') {
            $type = $type === 'default' ? 'info' : 'danger';
        } elseif ($type === 'warn') {
            $type = 'warning';
        } elseif ($type === 'ok') {
            $type = 'success';
        }

        return in_array($type, self::TYPES, true) ? $type : 'info';
    }

    /**
     * @param array<string,mixed> $options
     * @return array{key?:string,title?:string,details?:string,url?:string,label?:string,error?:string}
     */
    private function payload(array $options): array
    {
        $limits = [
            'key' => self::KEY_MAX,
            'title' => self::TITLE_MAX,
            'details' => self::DETAILS_MAX,
            'url' => self::URL_MAX,
            'label' => self::LABEL_MAX,
        ];
        $payload = [];
        foreach ($limits as $name => $limit) {
            $value = $options[$name] ?? '';
            if (!is_string($value)) {
                return ['error' => 'Некорректный параметр уведомления: ' . $name];
            }
            $value = trim($value);
            if (mb_strlen($value) > $limit) {
                return ['error' => 'Слишком длинный параметр уведомления: ' . $name];
            }
            if ($name === 'url' && $value !== '' && self::internalUrl($value) === '') {
                return ['error' => 'Ссылка уведомления должна быть внутренним абсолютным путём.'];
            }
            $payload[$name] = $value;
        }

        return $payload;
    }

    /**
     * @return array{id:int,module:string,module_title:string,module_url:string,message:string,type:string,added:string,title:string,details:string,url:string,label:string}
     */
    private function present(object $row): array
    {
        $module = (string)$row->module;
        $known = $this->modules->get($module);

        return [
            'id' => (int)$row->id,
            'module' => $module,
            'module_title' => $module === 'system'
                ? 'Система'
                : (string)($known['name'] ?? $module),
            'module_url' => $known !== null ? '/module/' . $module : '/admin',
            'message' => (string)$row->message,
            'type' => $this->type((string)$row->type),
            'added' => (string)$row->created_at,
            'title' => (string)($row->title ?? ''),
            'details' => (string)($row->details ?? ''),
            'url' => self::internalUrl((string)($row->url ?? '')),
            'label' => (string)($row->label ?? ''),
        ];
    }
}
