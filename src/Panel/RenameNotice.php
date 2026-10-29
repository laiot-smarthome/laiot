<?php

declare(strict_types=1);

namespace MajorDoMo\Panel;

use MajorDoMo\Rename\NameReferenceUpdater;

/**
 * Общее сообщение о сохранении имени и каскадном обновлении ссылок.
 *
 * 2026-10-29 — четыре контроллера используют один формат результата операции.
 */
final readonly class RenameNotice
{
    public function __construct(private NameReferenceUpdater $references)
    {
    }

    public function message(string $old, string $new, string $saved): string
    {
        $old = trim($old);
        $new = trim($new);
        if ($old === '' || $old === $new) {
            return $saved;
        }
        $message = 'Имя «' . $old . '» изменено на «' . $new . '». Обновлено ссылок: '
            . $this->references->changes() . '.';
        $warnings = $this->references->warnings();
        if ($warnings !== []) {
            $message .= ' Не удалось обновить файлов модулей: ' . count($warnings) . ' (подробности в логе).';
        }

        return $message;
    }
}
