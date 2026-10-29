<?php

declare(strict_types=1);

namespace LaIOT\Services\Scripts;

use InvalidArgumentException;
use LaIOT\Services\Events\SystemEvents;

/**
 * Событие, по которому запускается сценарий.
 *
 * Сценарий хранит имя события (`event_name`); пустое значение — запуск только
 * вручную. Ядро отправляет `system.minute`, `system.hour` и `system.day`,
 * модули — `module.<id>.*` (например, `module.mqtt.ready`). Имя проверяется
 * общим шаблоном `SystemEvents`, а каталог ниже нужен только для подсказок и
 * подписей известных событий ядра.
 *
 * 2026-10-27 — события модулей: `module.<id>.*` вместо `system.*`.
 * 2026-10-27 — запуск по системному событию вместо признака расписания.
 */
final class ScriptTriggers
{
    /** Шаблон совпадает с `SystemEvents::EVENT_PATTERN`. */
    public const PATTERN = SystemEvents::EVENT_PATTERN;

    /**
     * Известные события ядра: значение — подпись для интерфейса.
     *
     * @return array<string,string>
     */
    public static function catalog(): array
    {
        return [
            'system.minute' => 'Каждую минуту',
            'system.hour' => 'Каждый час',
            'system.day' => 'Каждый день',
        ];
    }

    /**
     * Приводит имя события к хранимому виду; пустая строка — без триггера.
     */
    public static function normalize(string $event): string
    {
        $event = trim($event);
        if ($event === '') {
            return '';
        }
        if (preg_match(self::PATTERN, $event) !== 1) {
            throw new InvalidArgumentException(
                'Событие указывается как system.<имя> или module.<модуль>.<имя>: '
                . 'например, system.minute или module.mqtt.ready.'
            );
        }

        return $event;
    }

    /**
     * Подпись для списка и карточки: «вручную», имя события ядра или модуля.
     */
    public static function label(string $event): string
    {
        $event = trim($event);
        if ($event === '') {
            return 'вручную';
        }

        return self::catalog()[$event] ?? $event;
    }
}
