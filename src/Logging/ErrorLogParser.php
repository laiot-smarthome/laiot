<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

/**
 * Разбор отдельного файла ошибки для админки.
 *
 * ErrorFileWriter пишет текстовый снимок: message, class, file:line, path,
 * context и trace. Показывать пользователю сырой текст неудобно, но и терять
 * строки нельзя: парсер выделяет поля, а исходный файл остаётся доступен
 * целиком. Если файла нет, используется context из строки system_errors.
 *
 * 2026-10-27 — парсер создан для единой панели разбора ошибки.
 */
final class ErrorLogParser
{
    /**
     * @param array<string,mixed> $fallbackContext Контекст из БД, если файла нет.
     * @return array{
     *     error:string,class:string,file:string,line:int,path:string,
     *     request_id:string,context:array<string,mixed>,context_json:string,
     *     trace:string,frames:list<array{index:int,location:string,file:string,line:int,call:string}>,
     *     raw:string
     * }
     */
    public function parse(?string $content, array $fallbackContext = []): array
    {
        $raw = $content ?? '';
        $result = [
            'error' => '',
            'class' => '',
            'file' => '',
            'line' => 0,
            'path' => '',
            'request_id' => '',
            'context' => [],
            'context_json' => '',
            'trace' => '',
            'frames' => [],
            'raw' => $raw,
        ];
        if ($raw === '') {
            return $this->withContext($result, $fallbackContext);
        }

        $traceLines = [];
        $inTrace = false;
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            if ($inTrace) {
                $traceLines[] = $line;
                continue;
            }
            if ($line === 'trace:') {
                $inTrace = true;
                continue;
            }
            if (str_starts_with($line, 'error: ')) {
                $result['error'] = substr($line, 7);
                continue;
            }
            if (str_starts_with($line, 'class: ')) {
                $result['class'] = substr($line, 7);
                continue;
            }
            if (str_starts_with($line, 'file: ')) {
                [$file, $lineNumber] = $this->splitFileLine(substr($line, 6));
                $result['file'] = $file;
                $result['line'] = $lineNumber;
                continue;
            }
            if (str_starts_with($line, 'path: ')) {
                $result['path'] = substr($line, 6);
                continue;
            }
            if (str_starts_with($line, 'request_id: ')) {
                $result['request_id'] = substr($line, 12);
                continue;
            }
            if (str_starts_with($line, 'context: ')) {
                $decoded = json_decode(substr($line, 9), true);
                if (is_array($decoded)) {
                    $result['context'] = $decoded;
                }
            }
        }
        $result['trace'] = trim(implode("\n", $traceLines));
        $result['frames'] = $this->frames($result['trace']);

        return $this->withContext($result, $fallbackContext);
    }

    /**
     * @param array{
     *     error:string,class:string,file:string,line:int,path:string,
     *     request_id:string,context:array<string,mixed>,context_json:string,
     *     trace:string,frames:list<array{index:int,location:string,file:string,line:int,call:string}>,
     *     raw:string
     * } $result
     * @param array<string,mixed> $fallback
     * @return array{
     *     error:string,class:string,file:string,line:int,path:string,
     *     request_id:string,context:array<string,mixed>,context_json:string,
     *     trace:string,frames:list<array{index:int,location:string,file:string,line:int,call:string}>,
     *     raw:string
     * }
     */
    private function withContext(array $result, array $fallback): array
    {
        if ($result['context'] === [] && $fallback !== []) {
            $result['context'] = $fallback;
        }
        if ($result['class'] === '' && isset($fallback['class'])) {
            $result['class'] = (string)$fallback['class'];
        }
        if ($result['file'] === '' && isset($fallback['file'])) {
            $result['file'] = (string)$fallback['file'];
            $result['line'] = isset($fallback['line']) ? (int)$fallback['line'] : 0;
        }
        if ($result['path'] === '' && isset($fallback['path'])) {
            $result['path'] = (string)$fallback['path'];
        }
        $result['context_json'] = $result['context'] === []
            ? ''
            : (string)json_encode($result['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $result;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function splitFileLine(string $value): array
    {
        $value = trim($value);
        if (preg_match('/^(.*):(\d+)$/D', $value, $matches) === 1) {
            return [$matches[1], (int)$matches[2]];
        }

        return [$value, 0];
    }

    /**
     * @return list<array{index:int,location:string,file:string,line:int,call:string}>
     */
    private function frames(string $trace): array
    {
        if ($trace === '' || $trace === '(нет)') {
            return [];
        }
        $frames = [];
        foreach (preg_split('/\R/', $trace) ?: [] as $line) {
            if (preg_match('/^#(\d+)\s+(.+)$/D', $line, $matches) !== 1) {
                continue;
            }
            $location = trim($matches[2]);
            $file = '';
            $lineNumber = 0;
            $call = $location;
            if (preg_match('/^(.+?)\((\d+)\):\s*(.*)$/D', $location, $parts) === 1) {
                $file = $parts[1];
                $lineNumber = (int)$parts[2];
                $call = $parts[3];
            }
            $frames[] = [
                'index' => (int)$matches[1],
                'location' => $location,
                'file' => $file,
                'line' => $lineNumber,
                'call' => $call,
            ];
        }

        return $frames;
    }
}
