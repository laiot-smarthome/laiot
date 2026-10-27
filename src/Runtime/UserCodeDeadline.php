<?php

declare(strict_types=1);

namespace MajorDoMo\Runtime;

use RuntimeException;

/**
 * Общий срок для вложенных вызовов доверенного пользовательского PHP.
 *
 * На Linux первый сигнал разворачивает стек, чтобы обработчик записал ошибку.
 * Если пользовательский catch поглотил таймаут, второй сигнал завершает процесс:
 * долгий код не должен навсегда занимать полосу. Очередь восстановит резервацию.
 *
 * 2026-10-27 — ограничение OnChange и подписчиков без отдельного fork на команду.
 */
final class UserCodeDeadline
{
    private bool $active = false;

    public function __construct(private readonly int $seconds)
    {
        if ($seconds < 1) {
            throw new RuntimeException('Срок пользовательского обработчика должен быть положительным.');
        }
    }

    public function supported(): bool
    {
        return function_exists('pcntl_alarm') && function_exists('pcntl_signal_get_handler')
            && function_exists('pcntl_async_signals');
    }

    /** @return mixed Результат пользовательского кода. */
    public function run(callable $action): mixed
    {
        if ($this->active || !$this->supported()) {
            return $action();
        }

        $previousHandler = pcntl_signal_get_handler(SIGALRM);
        $previousAsync = pcntl_async_signals(true);
        $previousAlarm = pcntl_alarm(0);
        $started = time();
        $this->active = true;
        $expired = false;
        pcntl_signal(SIGALRM, function () use (&$expired): never {
            $expired = true;
            pcntl_signal(SIGALRM, SIG_DFL);
            pcntl_alarm(1);
            throw new RuntimeException('Обработчик превысил допустимое время ' . $this->seconds . ' с.');
        });
        pcntl_alarm($this->seconds);
        try {
            $result = $action();
            if ($expired) {
                throw new RuntimeException('Обработчик превысил допустимое время ' . $this->seconds . ' с.');
            }
            return $result;
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler);
            pcntl_async_signals($previousAsync);
            if ($previousAlarm > 0) {
                pcntl_alarm(max(1, $previousAlarm - (time() - $started)));
            }
            $this->active = false;
        }
    }
}
