<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Properties\PropertyValueService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Входящее сообщение MQTT: разбор, привязки, свойство/метод и история.
 *
 * Быстрый путь:
 *  - привязки берутся из памяти (BindingIndex), без SQL на сообщение;
 *  - у привязанного топика обновляется последнее значение и, если включён
 *    журнал, буферизуется запись истории — пачкой, а не INSERT на сообщение;
 *  - неизвестные топики регистрируются с ограничением по времени, чтобы
 *    дерево датчиков не устраивало шторм вставок;
 *  - ошибка не теряет сообщение: оно остаётся в ограниченной очереди повторов
 *    процесса приёма и повторяется с задержкой.
 *
 * 2026-10-27 — перенос обработки alpha на сервисы ядра.
 */
final class InboundHandler
{
    private const MAX_RETRY = 2000;

    private const MAX_ATTEMPTS = 5;

    /** @var array<string, string> */
    private array $lastValues = [];

    /** @var array<string, int> */
    private array $unlinkedExpires = [];

    /** @var array<string, int> */
    private array $dedupExpires = [];

    /** @var list<array{topic:string,payload:string,attempts:int,due:float}> */
    private array $retry = [];

    /** @var list<array<string, mixed>> */
    private array $history = [];

    private float $historyFlushedAt = 0.0;

    private int $nextCleanup = 0;

    public function __construct(
        private readonly Settings $settings,
        private readonly BindingIndex $bindings,
        private readonly TopicRepository $topics,
        private readonly PropertyValueService $properties,
        private readonly MethodRunner $methods,
        private readonly RuntimeState $runtime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(string $topic, string $payload): void
    {
        if ($topic === '') {
            return;
        }
        $this->runtime->received();
        $dedupKey = $this->dedupKey($topic, $payload);
        $this->cleanup();

        if ($dedupKey !== null && isset($this->dedupExpires[$dedupKey]) && $this->dedupExpires[$dedupKey] > time()) {
            $this->runtime->count('deduplicated');

            return;
        }
        if ($dedupKey !== null) {
            $this->dedupExpires[$dedupKey] = time() + $this->settings->int('dedup_seconds');
        }

        try {
            $this->process($topic, $payload);
        } catch (Throwable $error) {
            if ($dedupKey !== null) {
                unset($this->dedupExpires[$dedupKey]);
            }
            $this->runtime->error($topic . ': ' . $error->getMessage());
            $this->logger->warning('MQTT: сообщение не обработано', [
                'topic' => $topic,
                'error' => $error->getMessage(),
            ]);
            $this->enqueue($topic, $payload, 1);
        }

        $this->flushHistory(false);
    }

    /**
     * Повторяет отложенные сообщения. Вызывается каждым проходом цикла.
     */
    public function retryDue(int $limit = 50): void
    {
        if ($this->retry === []) {
            return;
        }
        $now = microtime(true);
        $done = 0;
        $remaining = [];
        foreach ($this->retry as $item) {
            if ($done >= $limit || $item['due'] > $now) {
                $remaining[] = $item;
                continue;
            }
            try {
                $this->process($item['topic'], $item['payload']);
                $done++;
            } catch (Throwable $error) {
                $item['attempts']++;
                if ($item['attempts'] >= self::MAX_ATTEMPTS) {
                    $this->runtime->error($item['topic'] . ': ' . $error->getMessage());
                    $this->logger->error('MQTT: сообщение отброшено после повторов', [
                        'topic' => $item['topic'],
                        'error' => $error->getMessage(),
                    ]);
                    continue;
                }
                $item['due'] = microtime(true) + min(30, 2 ** $item['attempts']);
                $remaining[] = $item;
            }
        }
        $this->retry = $remaining;
    }

    public function flush(): void
    {
        $this->flushHistory(true);
    }

    /**
     * Разбирает сообщение. Публичный только для тестов и повторной обработки.
     */
    public function process(string $topic, string $payload, bool $ignoreStrip = false, int $depth = 0): void
    {
        if (strlen($topic) > 255) {
            $this->runtime->count('too_long');

            return;
        }
        if ($depth > 32) {
            $this->runtime->count('too_deep');

            return;
        }
        $this->bindings->ensureFresh(0.5);
        if (!$this->bindings->ready()) {
            throw new RuntimeException('Индекс привязок MQTT недоступен.');
        }
        if (preg_match('/#$/', $topic) === 1) {
            return;
        }

        $strip = !$ignoreStrip && $this->settings->bool('stripmode');
        if ($strip && !$this->bindings->isLinkedOrPrefix($topic) && !$this->bindings->isWritePath($topic)) {
            $this->runtime->count('stripped');

            return;
        }

        $decoded = $this->decodeJsonObject($payload);
        if ($decoded !== null) {
            foreach ($decoded as $key => $child) {
                if (is_array($child)) {
                    $child = json_encode($child, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                }
                $this->process($topic . '/' . $key, (string)$child, $ignoreStrip, $depth + 1);
            }
        }

        $record = $this->bindings->record($topic);
        $linked = $record !== [] && (string)($record['linked_object'] ?? '') !== ''
            && ((string)($record['linked_property'] ?? '') !== '' || (string)($record['linked_method'] ?? '') !== '');
        $interval = $this->settings->int('unlinked_interval');
        if (!$linked && !$this->allowUnlinked($topic, $interval)) {
            return;
        }

        $this->processValue($topic, $payload, $record, $linked);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function processValue(string $topic, string $value, array $record, bool $linked): void
    {
        if ($this->bindings->isWritePath($topic) && !$linked) {
            // Это исходящий топик команды: его состояние ведёт команда, а не эхо.
            return;
        }

        if ($record === []) {
            $knownId = $this->bindings->knownId($topic);
            if ($knownId > 0) {
                $raw = $this->mapIncoming($value, '');
                $this->topics->updateValue($knownId, $raw);
                $this->lastValues[$topic] = $raw;
                if ($this->settings->bool('debug')) {
                    $this->writeHistory(0, TopicRepository::DIRECTION_IN, $topic, $raw, $value, '');
                }

                return;
            }
            if ($this->isLikelyJsonFragmentPath($topic)) {
                return;
            }

            $id = $this->topics->discover($topic, $value);
            $this->bindings->rememberKnown($topic, $id);
            $this->lastValues[$topic] = $value;
            $this->runtime->count('discovered');
            if ($this->settings->bool('debug')) {
                $this->writeHistory($id, TopicRepository::DIRECTION_IN, $topic, $value, $value, '');
            }

            return;
        }

        $old = $this->lastValues[$topic] ?? (string)$record['value'];
        if (!empty($record['only_new_value']) && $value == $old) {
            if (!empty($record['logging']) || $this->settings->bool('debug')) {
                $this->writeHistory(
                    (int)$record['id'],
                    TopicRepository::DIRECTION_IN,
                    $topic,
                    $value,
                    $value,
                    $this->linkedData($record)
                );
            }
            $this->runtime->count('duplicated');

            return;
        }

        $mapped = $this->mapIncoming($value, (string)$record['replace_list']);
        $started = microtime(true);

        if ((int)($record['linked_object_id'] ?? 0) > 0 && (int)($record['linked_property_id'] ?? 0) > 0) {
            $ok = $this->properties->setValue(
                (int)$record['linked_object_id'],
                (int)$record['linked_property_id'],
                $mapped,
                'mqtt'
            );
            if (!$ok) {
                throw new RuntimeException('Свойство отклонено: ' . $this->linkedData($record));
            }
        } elseif ((string)$record['linked_object'] !== '' && (string)$record['linked_property'] !== '') {
            // Старая строка без id (например, после ручного импорта).
            $ok = $this->properties->set((string)$record['linked_object'], (string)$record['linked_property'], $mapped, 'mqtt');
            if (!$ok) {
                throw new RuntimeException('Свойство не найдено: ' . $this->linkedData($record));
            }
        }

        if ((string)$record['linked_method'] !== '' && (string)$record['linked_object'] !== '') {
            $this->methods->run((string)$record['linked_object'], (string)$record['linked_method'], [
                'VALUE' => $mapped,
                'NEW_VALUE' => $mapped,
                'OLD_VALUE' => $old,
                'SOURCE' => $topic,
                'TITLE' => (string)$record['title'] !== '' ? (string)$record['title'] : $topic,
            ]);
        }

        $this->topics->updateValue((int)$record['id'], $value);
        $this->lastValues[$topic] = $value;

        if (!empty($record['logging']) || $this->settings->bool('debug') || $linked) {
            $this->writeHistory(
                (int)$record['id'],
                TopicRepository::DIRECTION_IN,
                $topic,
                $mapped,
                $value,
                $this->linkedData($record)
            );
        }

        $this->runtime->applied((microtime(true) - $started) * 1000);
        $this->runtime->count('bound');
        if ($this->settings->bool('debug')) {
            $this->logger->info('MQTT receive', [
                'topic' => $topic,
                'value' => $value,
                'property' => $this->linkedData($record),
            ]);
        }
    }

    private function allowUnlinked(string $topic, int $interval): bool
    {
        if ($interval <= 0) {
            return true;
        }
        $now = time();
        if (isset($this->unlinkedExpires[$topic]) && $this->unlinkedExpires[$topic] > $now) {
            return false;
        }
        if (count($this->unlinkedExpires) >= 100000) {
            $this->unlinkedExpires = [];
        }
        $this->unlinkedExpires[$topic] = $now + $interval;

        return true;
    }

    private function enqueue(string $topic, string $payload, int $attempts): void
    {
        if (count($this->retry) >= self::MAX_RETRY) {
            array_shift($this->retry);
        }
        $this->retry[] = [
            'topic' => $topic,
            'payload' => $payload,
            'attempts' => $attempts,
            'due' => microtime(true) + min(30, 2 ** $attempts),
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function linkedData(array $record): string
    {
        if ((string)$record['linked_object'] === '' || (string)$record['linked_property'] === '') {
            return '';
        }

        return $record['linked_object'] . '.' . $record['linked_property'];
    }

    private function mapIncoming(string $value, string $list): string
    {
        $list = trim($list);
        if ($list === '') {
            return $value;
        }
        foreach (explode(',', $list) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, '=')) {
                continue;
            }
            [$new, $old] = explode('=', $pair, 2);
            if ($value == trim($new)) {
                return trim($old);
            }
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $value): ?array
    {
        $payload = trim($value);
        if ($payload === '' || !str_starts_with($payload, '{') || !str_ends_with($payload, '}')) {
            return null;
        }
        try {
            $decoded = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    private function isLikelyJsonFragmentPath(string $path): bool
    {
        return preg_match('/["{}]/', $path) === 1 && preg_match('/[:,]/', $path) === 1;
    }

    private function dedupKey(string $topic, string $payload): ?string
    {
        $seconds = $this->settings->int('dedup_seconds');
        if ($seconds <= 0) {
            return null;
        }

        return hash('sha256', $seconds . ':' . $topic . "\0" . $payload);
    }

    private function cleanup(): void
    {
        $now = time();
        if ($now < $this->nextCleanup) {
            return;
        }
        $this->nextCleanup = $now + 60;
        foreach ($this->dedupExpires as $key => $expires) {
            if ($expires <= $now) {
                unset($this->dedupExpires[$key]);
            }
        }
        foreach ($this->unlinkedExpires as $key => $expires) {
            if ($expires <= $now) {
                unset($this->unlinkedExpires[$key]);
            }
        }
        if (count($this->lastValues) > 20000) {
            $this->lastValues = array_slice($this->lastValues, -10000, null, true);
        }
    }

    private function writeHistory(
        int $topicId,
        int $direction,
        string $topic,
        string $value,
        string $payload,
        string $linked
    ): void {
        $this->history[] = [
            'topic_id' => $topicId,
            'direction' => $direction,
            'topic' => mb_strcut($topic, 0, 255, 'UTF-8'),
            'value' => mb_strcut($value, 0, 65535, 'UTF-8'),
            'payload' => mb_strcut($payload, 0, 65535, 'UTF-8'),
            'linked_data' => mb_strcut($linked, 0, 255, 'UTF-8'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->flushHistory(false);
    }

    private function flushHistory(bool $force): void
    {
        if ($this->history === []) {
            return;
        }
        $now = microtime(true);
        if (!$force && count($this->history) < 100 && $now - $this->historyFlushedAt < 0.2) {
            return;
        }
        $this->historyFlushedAt = $now;
        $rows = $this->history;
        $this->history = [];
        try {
            $this->topics->addHistory($rows);
            $this->runtime->count('history', count($rows));
        } catch (Throwable $error) {
            // История не должна останавливать приём: сообщение уже применено.
            $this->runtime->error('MQTT history: ' . $error->getMessage());
            $this->logger->warning('MQTT: история не записана', ['error' => $error->getMessage()]);
        }
    }
}
