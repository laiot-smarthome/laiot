<?php

declare(strict_types=1);

namespace MajorDoMo\Cache;

use Closure;
use DateInterval;
use Illuminate\Cache\Repository;
use InvalidArgumentException;

/**
 * Единый фасад кэша MyDoMo.
 *
 * Ключи всегда получают namespace `mydomo.`, поэтому ядро и модули не
 * пересекаются. Конкретный драйвер (array/file/database) выбирает фабрика.
 */
final class CacheStore
{
    /**
     * @param (Closure(): bool)|null $clear
     */
    public function __construct(
        private readonly Repository $repository,
        private readonly string $prefix = 'mydomo.',
        private readonly ?Closure $clear = null
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->repository->put($this->key($key), $value, $ttl);
    }

    /**
     * Атомарно создать ключ, если его нет: основа worker-lock.
     */
    public function add(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->repository->add($this->key($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->repository->has($this->key($key));
    }

    public function forget(string $key): bool
    {
        return $this->repository->forget($this->key($key));
    }

    public function clear(): bool
    {
        if ($this->clear instanceof Closure) {
            return ($this->clear)();
        }

        return $this->repository->clear();
    }

    /**
     * @param Closure(): mixed $callback
     */
    public function remember(string $key, DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return $this->repository->remember($this->key($key), $ttl, $callback);
    }

    /**
     * @param Closure(): mixed $callback
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->repository->rememberForever($this->key($key), $callback);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->repository->pull($this->key($key), $default);
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->repository->increment($this->key($key), $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->repository->decrement($this->key($key), $value);
    }

    private function key(string $key): string
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,180}$/D', $key)) {
            throw new InvalidArgumentException('Некорректный ключ кэша: ' . $key);
        }

        return $this->prefix . $key;
    }
}
