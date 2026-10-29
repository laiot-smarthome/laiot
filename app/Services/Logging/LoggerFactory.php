<?php

declare(strict_types=1);

namespace LaIOT\Services\Logging;

use LogicException;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Каналы Monolog LaIOT.
 *
 * В production каждый канал пишет в свой файл `var/log/<channel>.log` с
 * суточной ротацией (`max_files`). Поток (`php://stderr`) и явно заданный
 * одиночный файл остаются режимом для тестов и CLI: тогда все каналы идут
 * туда, различаясь именем в записи.
 *
 * Отдельные файлы — не «красивость»: ошибки HTTP, очереди и безопасности
 * разбираются по-разному, а общий поток из тысяч строк этого не даёт.
 *
 * Изменения:
 * 2026-10-27 — каналы пишут в отдельные файлы с ротацией; уровень из конфигурации.
 */
final class LoggerFactory
{
    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly RequestContext $context
    ) {
    }

    public function channel(string $name = 'app'): LoggerInterface
    {
        $name = $this->name($name);
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        $level = $this->level();
        $path = (string)($this->config['path'] ?? '');
        $directory = trim((string)($this->config['directory'] ?? ''));

        if (str_contains($path, '://')) {
            $handler = new StreamHandler($path, $level);
        } elseif ($directory !== '') {
            // Канал — отдельный файл; ротация по дням, старые удаляются.
            $this->ensureDirectory($directory);
            $handler = new RotatingFileHandler(
                $directory . '/' . $name . '.log',
                max(1, (int)($this->config['max_files'] ?? 14)),
                $level
            );
        } elseif ($path !== '') {
            $this->ensureDirectory(dirname($path));
            $handler = new StreamHandler($path, $level);
        } else {
            throw new LogicException('Не задан путь и каталог логов LaIOT.');
        }

        $logger = new Logger($name);
        $logger->pushHandler($handler);
        $logger->pushProcessor(new RequestIdProcessor($this->context));

        return $this->channels[$name] = $logger;
    }

    private function level(): Level
    {
        if ((bool)($this->config['debug'] ?? false)) {
            return Level::Debug;
        }

        return match (strtolower((string)($this->config['level'] ?? 'info'))) {
            'debug' => Level::Debug,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Info,
        };
    }

    private function name(string $name): string
    {
        $clean = strtolower((string)preg_replace('/[^A-Za-z0-9_-]/', '', $name));

        return $clean !== '' ? $clean : 'app';
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new LogicException('Не удалось создать каталог логов: ' . $directory);
        }
    }
}
