<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Logging\ErrorFileWriter;
use MajorDoMo\Logging\ErrorLogParser;
use MajorDoMo\Logging\ErrorRepository;

/**
 * Ошибки: отдельный экран списка и отдельный экран разбора.
 *
 * Раньше список и детали делили одну страницу, поэтому при большом журнале
 * всё превращалось в сплошной текст. Теперь компонент переключает два
 * полноширинных экрана: список групп/записей и детали выбранной ошибки с
 * повторами, местом, стеком, контекстом и копированием для AI. Переход —
 * Yoyo-состояние, без перезагрузки; фильтры и режим списка сохраняются.
 *
 * 2026-10-27 — экран ошибок разделён на список и детали.
 * 2026-10-29 — Request ID последней записи, фильтры без коллизий hx-vals.
 */
final class SystemErrors extends Component
{
    public string $search = '';

    public string $level = '';

    public bool $flat = false;

    public string $message = '';

    public int $selectedId = 0;

    /** @var list<string> Поля фильтра приходят из формы, без старых значений в hx-vals. */
    protected $props = ['flat', 'message', 'selectedId'];

    private ?ErrorRepository $errors = null;

    private ?ErrorFileWriter $files = null;

    private ?ErrorLogParser $parser = null;

    private ?SessionStore $session = null;

    public function mount(
        ErrorRepository $errors,
        ErrorFileWriter $files,
        ErrorLogParser $parser,
        SessionStore $session
    ): void {
        $this->errors = $errors;
        $this->files = $files;
        $this->parser = $parser;
        $this->session = $session;
    }

    public function select(int $id): void
    {
        if ($id > 0) {
            $this->selectedId = $id;
        }
    }

    /**
     * Открыть группу: детали последней записи и список повторов.
     */
    public function openGroup(int $latestId): void
    {
        $error = $this->errors?->find($latestId);
        if ($error === null) {
            return;
        }
        $this->selectedId = $latestId;
    }

    public function back(): void
    {
        $this->selectedId = 0;
    }

    public function allGroups(): void
    {
        $this->message = '';
        $this->flat = false;
        $this->selectedId = 0;
    }

    public function toggleFlat(): void
    {
        $this->flat = !$this->flat;
        $this->message = '';
        $this->selectedId = 0;
    }

    public function reset(): void
    {
        $this->search = '';
        $this->level = '';
        $this->message = '';
        $this->flat = false;
        $this->selectedId = 0;
    }

    public function render(): mixed
    {
        $allRows = $this->errors?->latest(500, $this->level) ?? [];
        $rows = $this->filter($allRows);
        $csrf = $this->session?->csrfToken() ?? '';

        $selected = null;
        foreach ($allRows as $row) {
            if ((int)$row['id'] === $this->selectedId) {
                $selected = $row;
                break;
            }
        }
        if ($selected !== null) {
            return $this->view('system-error-detail', [
                'detail' => $this->detail($selected, $allRows),
                'search' => $this->search,
                'level' => $this->level,
                'flat' => $this->flat,
                'message' => $this->message,
                'csrf' => $csrf,
            ]);
        }

        return $this->view('system-errors', [
            'rows' => $rows,
            'groups' => $this->group($rows),
            'search' => $this->search,
            'level' => $this->level,
            'flat' => $this->flat,
            'message' => $this->message,
            'csrf' => $csrf,
        ]);
    }

    /**
     * @param list<array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string}> $rows
     * @return list<array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string}>
     */
    private function filter(array $rows): array
    {
        if ($this->search !== '') {
            $needle = mb_strtolower(trim($this->search));
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => str_contains(mb_strtolower($row['message']), $needle)
                    || str_contains(mb_strtolower($row['request_id']), $needle)
            ));
        }
        if ($this->message !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['message'] === $this->message));
        }

        return $rows;
    }

    /**
     * @param list<array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string}> $rows
     * @return list<array{level:string,message:string,count:int,first_at:string,last_at:string,request_id:string,latest_id:int}>
     */
    private function group(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['level'] . '|' . $row['message'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'level' => $row['level'],
                    'message' => $row['message'],
                    'count' => 0,
                    'first_at' => $row['created_at'],
                    'last_at' => $row['created_at'],
                    'request_id' => $row['request_id'],
                    'latest_id' => (int)$row['id'],
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['last_at'] = max($groups[$key]['last_at'], $row['created_at']);
            $groups[$key]['first_at'] = min($groups[$key]['first_at'], $row['created_at']);
        }
        $groups = array_values($groups);
        usort($groups, static fn (array $left, array $right): int => strcmp($right['last_at'], $left['last_at']));

        return $groups;
    }

    /**
     * @param array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string} $selected
     * @param list<array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string}> $allRows
     * @return array{
     *     error:array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string,context:string},
     *     file:array{name:string,size:int,modified:string,content:string,truncated:bool}|null,
     *     parsed:array<string,mixed>,
     *     occurrences:list<array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string}>,
     *     repeat_count:int,first_at:string,last_at:string,copy_text:string
     * }
     */
    private function detail(array $selected, array $allRows): array
    {
        $error = $this->errors?->find((int)$selected['id']) ?? [
            ...$selected,
            'context' => '',
        ];
        $file = $error['log_file'] !== '' ? $this->files?->read($error['log_file']) : null;
        $fallback = [];
        if ($error['context'] !== '') {
            $decoded = json_decode($error['context'], true);
            if (is_array($decoded)) {
                $fallback = $decoded;
            }
        }
        $parsed = $this->parser?->parse($file['content'] ?? null, $fallback) ?? [];
        $occurrences = array_values(array_filter(
            $allRows,
            static fn (array $row): bool => $row['level'] === $error['level'] && $row['message'] === $error['message']
        ));
        $stats = $this->errors?->groupStats((string)$error['level'], (string)$error['message'])
            ?? ['count' => count($occurrences), 'first_at' => '', 'last_at' => ''];
        $repeatCount = max($stats['count'], count($occurrences));
        $firstAt = $stats['first_at'];
        $lastAt = $stats['last_at'];
        if ($firstAt === '') {
            $firstAt = $occurrences === [] ? (string)$error['created_at'] : (string)min(array_column($occurrences, 'created_at'));
        }
        if ($lastAt === '') {
            $lastAt = $occurrences === [] ? (string)$error['created_at'] : (string)max(array_column($occurrences, 'created_at'));
        }

        return [
            'error' => $error,
            'file' => $file,
            'parsed' => $parsed,
            'occurrences' => $occurrences,
            'repeat_count' => $repeatCount,
            'first_at' => $firstAt,
            'last_at' => $lastAt,
            'copy_text' => $this->diagnostic($error, $parsed, $repeatCount, $firstAt, $lastAt),
        ];
    }

    /**
     * @param array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string,context:string} $error
     * @param array<string,mixed> $parsed
     */
    private function diagnostic(
        array $error,
        array $parsed,
        int $repeats,
        string $firstAt,
        string $lastAt
    ): string {
        $lines = [
            'MyDoMo: ошибка #' . $error['id'],
            'Уровень: ' . $error['level'],
            'Когда: ' . $error['created_at'],
            'Request ID: ' . ($error['request_id'] !== '' ? $error['request_id'] : '—'),
            'Сообщение: ' . $error['message'],
        ];
        if (($parsed['error'] ?? '') !== '' && $parsed['error'] !== $error['message']) {
            $lines[] = 'Ошибка в файле: ' . $parsed['error'];
        }
        if (($parsed['class'] ?? '') !== '') {
            $lines[] = 'Класс: ' . $parsed['class'];
        }
        if (($parsed['file'] ?? '') !== '') {
            $lines[] = 'Файл: ' . $parsed['file'] . (($parsed['line'] ?? 0) > 0 ? ':' . $parsed['line'] : '');
        }
        if (($parsed['path'] ?? '') !== '') {
            $lines[] = 'Путь: ' . $parsed['path'];
        }
        $lines[] = 'Повторов: ' . $repeats;
        if ($repeats > 1) {
            $lines[] = 'Первый раз: ' . $firstAt;
            $lines[] = 'Последний раз: ' . $lastAt;
        }
        $lines[] = '';
        $lines[] = 'Контекст:';
        $lines[] = ($parsed['context_json'] ?? '') !== '' ? $parsed['context_json'] : '—';
        $lines[] = '';
        $lines[] = 'Стек:';
        $lines[] = ($parsed['trace'] ?? '') !== '' ? $parsed['trace'] : '—';
        $raw = (string)($parsed['raw'] ?? '');
        if ($raw !== '') {
            $lines[] = '';
            $lines[] = 'Исходный файл:';
            $lines[] = strlen($raw) > 20000
                ? substr($raw, 0, 20000) . "\n… (файл обрезан для копирования)"
                : $raw;
        }

        return implode("\n", $lines);
    }
}
