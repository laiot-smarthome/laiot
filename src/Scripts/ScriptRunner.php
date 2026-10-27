<?php

declare(strict_types=1);

namespace MajorDoMo\Scripts;

use InvalidArgumentException;

/**
 * Запуск пользовательского сценария.
 *
 * Сценарий — доверенный PHP без тегов, как в alpha. `use_core` определяет
 * окружение: с ядром код выполняется в текущем процессе и видит обёртки
 * `gg/sg/callMethod`, без ядра — отдельным процессом как обычный PHP-файл.
 * ID или название принимаются оба: старые сценарии вызывают `runScript()`
 * и так, и так.
 *
 * 2026-10-27 — выбор окружения сценария (с ядром или без).
 * 2026-10-26 — сервис создан для alpha-совместимых runScript/callScript/rs.
 */
final readonly class ScriptRunner
{
    public function __construct(
        private ScriptRepository $scripts,
        private ScriptExecutor $executor
    ) {
    }

    /**
     * @param int|string $id ID сценария или его название.
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    public function run(int|string $id, mixed $params = []): mixed
    {
        $script = $this->find($id);
        if ($script === null) {
            throw new InvalidArgumentException('Сценарий не найден: ' . $id);
        }

        return $this->executor->runValue(
            (string)$script['code'],
            is_array($params) ? $params : [],
            (bool)$script['use_core']
        );
    }

    /**
     * @return array{id:int,title:string,code:string,description:string,category_id:int,event_name:string,use_core:bool}|null
     */
    private function find(int|string $id): ?array
    {
        if (is_int($id) || ctype_digit((string)$id)) {
            return $this->scripts->find((int)$id);
        }

        return $this->scripts->findByTitle((string)$id);
    }
}
