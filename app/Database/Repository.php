<?php

declare(strict_types=1);

namespace LaIOT\Database;

/**
 * Базовый репозиторий: одна зависимость на соединение и общие правила
 * выборок. Конкретные репозитории не наследуют SQL-обёртки legacy.
 */
abstract class Repository
{
    public function __construct(protected readonly DatabaseManager $database)
    {
    }
}
