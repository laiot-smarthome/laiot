<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

/**
 * Проверка кода модуля на запрещённые legacy-вызовы.
 *
 * Это не sandbox и не анализ безопасности: сканер ищет то, что новый SDK
 * обещает не использовать — прямой SQL, `$db`, `eval`, shell и PDO/mysqli.
 * Такой код нельзя ставить в качестве модуля MyDoMo; проверка запускается
 * вручную (`module:check`) и при будущей установке сторонних пакетов.
 *
 * 2026-10-27 — сканер создан для module:check.
 */
final readonly class ModuleChecker
{
    /** @var list<string> */
    private const FUNCTIONS = [
        'sqlselect',
        'sqlselectone',
        'sqlexec',
        'sqlinsert',
        'sqlupdate',
        'sqldelete',
        'addlinkedproperty',
        'registerevent',
    ];

    /** @var list<string> */
    private const VARIABLES = ['$db', '$mysqli', '$pdo', '$link'];

    /** @var list<string> */
    private const CLASSES = ['pdo', 'mysqli', 'sqlite3'];

    /**
     * @param array<string, mixed> $module Элемент ModuleManager::discovered().
     * @return list<array{file:string,line:int,rule:string,snippet:string}>
     */
    public function scanModule(array $module): array
    {
        $path = (string)($module['path'] ?? '');
        if ($path === '' || !is_dir($path)) {
            return [];
        }

        $files = [];
        $src = $path . '/' . (string)($module['src'] ?? 'src');
        if (is_dir($src)) {
            foreach ($this->phpFiles($src) as $file) {
                $files[] = $file;
            }
        }
        foreach (['routes', 'helpers'] as $key) {
            $relative = $module[$key] ?? null;
            if (is_string($relative) && $relative !== '' && is_file($path . '/' . $relative)) {
                $files[] = $path . '/' . $relative;
            }
        }

        $findings = [];
        foreach (array_values(array_unique($files)) as $file) {
            foreach ($this->scanFile($file) as $finding) {
                $findings[] = $finding;
            }
        }
        usort($findings, static fn (array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

        return $findings;
    }

    /**
     * @return list<array{file:string,line:int,rule:string,snippet:string}>
     */
    public function scanFile(string $file): array
    {
        $code = @file_get_contents($file);
        if ($code === false || trim($code) === '') {
            return [];
        }

        $lines = preg_split('/\R/', $code) ?: [];
        $tokens = token_get_all($code);
        $findings = [];
        $line = 1;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token)) {
                $line = $token[2];
            }
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_EVAL) {
                $findings[] = $this->finding($file, $line, 'eval', $lines);
                continue;
            }
            if ($token[0] === T_VARIABLE) {
                $name = strtolower($token[1]);
                if (in_array($name, self::VARIABLES, true)) {
                    $findings[] = $this->finding($file, $line, $name, $lines);
                }
                if ($name === '$this' && $this->objectProperty($tokens, $i) === 'db') {
                    $findings[] = $this->finding($file, $line, '$this->db', $lines);
                }
                continue;
            }
            if ($token[0] !== T_STRING) {
                continue;
            }
            $name = strtolower($token[1]);
            if ($this->functionCall($tokens, $i) && in_array($name, self::FUNCTIONS, true)) {
                $findings[] = $this->finding($file, $line, $name . '()', $lines);
            }
            if (in_array($name, self::CLASSES, true) || in_array(strtolower($token[1]), ['shell_exec', 'exec', 'system', 'passthru'], true)) {
                $findings[] = $this->finding($file, $line, $token[1], $lines);
            }
        }

        return $findings;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    private function functionCall(array $tokens, int $index): bool
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            $next = $tokens[$i];
            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_string($next) && $next === '(';
        }

        return false;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    private function objectProperty(array $tokens, int $index): ?string
    {
        $seenArrow = false;
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            $next = $tokens[$i];
            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($next) && $next[0] === T_OBJECT_OPERATOR) {
                $seenArrow = true;
                continue;
            }
            if ($seenArrow && is_array($next) && $next[0] === T_STRING) {
                return strtolower($next[1]);
            }

            return null;
        }

        return null;
    }

    /**
     * @return \Generator<int,string>
     */
    private function phpFiles(string $directory): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    /**
     * @param list<string> $lines
     * @return array{file:string,line:int,rule:string,snippet:string}
     */
    private function finding(string $file, int $line, string $rule, array $lines): array
    {
        $snippet = trim((string)($lines[$line - 1] ?? ''));

        return [
            'file' => $file,
            'line' => $line,
            'rule' => $rule,
            'snippet' => mb_substr($snippet, 0, 200),
        ];
    }
}
