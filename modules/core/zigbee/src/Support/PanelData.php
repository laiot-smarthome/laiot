<?php

declare(strict_types=1);

namespace LaIOT\Zigbee\Support;

use LaIOT\Services\Locations\LocationKind;
use LaIOT\Services\Locations\LocationRepository;
use Throwable;

/**
 * Общие данные панели Zigbee: комнаты для выбора и подписи статусов.
 *
 * Локации принадлежат ядру, поэтому модуль читает их только через
 * LocationRepository и не дублирует дерево в своих таблицах.
 *
 * 2026-10-28 — общий помощник страниц Zigbee.
 */
final class PanelData
{
    /**
     * Комнаты с объектом: только им можно назначить устройство.
     *
     * @return list<array{id:int,title:string}>
     */
    public static function rooms(LocationRepository $locations): array
    {
        $rooms = [];
        try {
            foreach ($locations->all() as $location) {
                if (LocationKind::normalize((string)($location['kind'] ?? '')) !== LocationKind::ROOM) {
                    continue;
                }
                $rooms[] = ['id' => (int)$location['id'], 'title' => (string)$location['title']];
            }
        } catch (Throwable) {
            return [];
        }

        return $rooms;
    }

    public static function statusLabel(string $status): string
    {
        return $status === 'added' ? 'Добавлено' : 'Обнаружено';
    }

    public static function availabilityLabel(string $available): string
    {
        return match ($available) {
            'online' => 'В сети',
            'offline' => 'Не в сети',
            default => 'Неизвестно',
        };
    }
}
