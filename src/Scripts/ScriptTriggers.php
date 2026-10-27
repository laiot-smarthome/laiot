<?php

declare(strict_types=1);

namespace MajorDoMo\Scripts;

use InvalidArgumentException;

/**
 * Системное событие, по которому запускается сценарий.
 *
 * Сценарий хранит имя события (`event_name`); пустое значение — запуск только
 * вручную. Ядро отправляет `system.minute`, `system.hour` и `system.day`, но
 * модуль вправе отправить своё `system.*`, поэтому имя проверяется шаблоном,
 * а не списком: каталог ниже нужен только для подсказок и подписей.
 *
 * 2026-10-27 — запуск по системному событию вместо признака расписания.
 */
final class ScriptTriggers
{
    /** Шаблон имени совпадает с `SystemEvents::emit()`. */
    public const PATTERN = '/^system\.[a-z][a-z0-9_.-]{0,180}$/D';

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
                'Системное событие указывается как system.<имя>, например system.minute.'
            );
        }

        return $event;
    }

    /**
     * Подпись для списка и карточки: «вручную» или понятное имя события.
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
