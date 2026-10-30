<?php

declare(strict_types=1);

namespace LaIOT\Services\Xray;

use Illuminate\Database\Query\Builder;
use LaIOT\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * Долговечная лента X-ray: каждое изменение, dump, событие и запуск сценария.
 * 2026-10-30 — журнал за 72 часа, отдельный от истории свойств и WebSocket.
 */
final class XrayJournal
{
    public const DAYS = 3;

    private ?bool $available = null;

    /** @var list<string> */
    private array $contexts = [];

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ValueSnapshot $snapshot,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function append(string $kind, string $name, string $source, array $payload): void
    {
        if (!$this->available()) {
            return;
        }
        $this->database->connection()->table('xray_entries')->insert([
            'kind' => $kind,
            'name' => mb_substr($name, 0, 191),
            'source' => mb_substr($source, 0, 500),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function dump(mixed $value, string $label = ''): void
    {
        $location = '';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32) as $frame) {
            $file = str_replace('\\', '/', $frame['file'] ?? '');
            if ($file !== '' && !str_contains($file, '/vendor/') && !str_contains($file, '/Support/helpers/')
                && !str_ends_with($file, '/XrayJournal.php') && !str_ends_with($file, '/XrayServiceProvider.php')) {
                $location = $file . ':' . ($frame['line'] ?? 0);
                break;
            }
        }
        $context = $this->contexts === [] ? '' : $this->contexts[array_key_last($this->contexts)];
        $this->append('debug', $label !== '' ? $label : get_debug_type($value), trim($context . ' ' . $location), [
            'value' => $this->snapshot->capture($value),
        ]);
    }

    public function enter(string $name): void
    {
        $this->contexts[] = $name;
    }

    public function leave(): void
    {
        array_pop($this->contexts);
    }

    public function script(string $name, bool $ok, int $duration, mixed $result, string $error = '', string $output = ''): void
    {
        $this->append('script', $name, 'scripts', [
            'ok' => $ok, 'duration_ms' => $duration, 'result' => $this->snapshot->capture($result),
            'error' => mb_substr($error, 0, 4096), 'output' => mb_substr($output, 0, 8192),
        ]);
    }

    public function maxId(): int
    {
        return (int)($this->query()->max('id') ?? 0);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return $this->query()->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
            ->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind')
            ->map(static fn (mixed $count): int => (int)$count)->all();
    }

    /** @return list<array<string, mixed>> */
    public function listing(string $kind, string $search, int $snapshot, int $page): array
    {
        $query = $this->query()->where('id', '<=', $snapshot);
        if ($kind !== '') {
            $query->where('kind', $kind);
        }
        if ($search !== '') {
            $query->where(static function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%')->orWhere('source', 'like', '%' . $search . '%');
            });
        }

        return $query->orderByDesc('id')->offset(($page - 1) * 50)->limit(51)->get()
            ->map(fn (object $row): array => $this->row($row))->all();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->query()->where('id', $id)->first();

        return $row === null ? null : $this->row($row);
    }

    private function query(): Builder
    {
        return $this->database->connection()->table('xray_entries')
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - self::DAYS * 86400));
    }

    /** @return array<string, mixed> */
    private function row(object $row): array
    {
        $entry = (array)$row;
        $entry['payload'] = json_decode((string)$row->payload, true, 512, JSON_THROW_ON_ERROR);

        return $entry;
    }

    private function available(): bool
    {
        if ($this->available === null) {
            $this->available = $this->database->schema()->hasTable('xray_entries');
            if (!$this->available) {
                $this->logger->warning('X-ray: журнал недоступен до применения миграции xray_entries.');
            }
        }

        return $this->available;
    }
}
