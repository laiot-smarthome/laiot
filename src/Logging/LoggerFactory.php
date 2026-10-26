<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

use LogicException;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Каналы Monolog MyDoMo.
 *
 * Пока все каналы пишут в один файл, но с именем канала в записи; отдельные
 * файлы/ротация добавляются на этапе production-настройки.
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
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        $path = (string)($this->config['path'] ?? 'php://stderr');
        if (!str_contains($path, '://')) {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new LogicException('Не удалось создать каталог логов: ' . $directory);
            }
        }

        $level = (bool)($this->config['debug'] ?? false) ? Level::Debug : Level::Info;
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler($path, $level));
        $logger->pushProcessor(new RequestIdProcessor($this->context));

        return $this->channels[$name] = $logger;
    }
}
