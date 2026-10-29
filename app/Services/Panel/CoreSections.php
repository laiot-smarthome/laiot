<?php

declare(strict_types=1);

namespace LaIOT\Services\Panel;

/**
 * Реестр ядровых разделов админки и сборка бокового меню.
 *
 * 2026-10-29 — обновления и восстановление в каталоге «Сервис».
 * 2026-10-27 — разделы перегруппированы в каталоги: «Система» больше не один
 * длинный список, а плоские пункты плюс раскрывающиеся «Диагностика»,
 * «API и доступ» и «Сервис». Пункт меню получил счётчик внимания (ошибки,
 * сбойные задания, непрочитанные уведомления).
 * 2026-10-27 — пользователи и роли объединены в одном разделе.
 * Ядровые страницы не являются модулями: у них нет module.json, ModuleDb и
 * lifecycle. Модулями остаются MQTT, Zigbee и сторонние SDK-модули.
 *
 * 2026-10-27 — UI Kit возвращён в меню «Система» для просмотра компонентов.
 * 2026-10-26 — разделы сгруппированы по задачам, а не по типу кода: в
 * «Системе» остались только обслуживание установки, профиль и UI Kit вынесены
 * из основного меню (профиль живёт в подвале боковой панели).
 * 2026-10-27 — объекты, свойства, методы, значения и история убраны из меню:
 * единственный вход в модель дома — «Классы», остальное живёт в его карточке.
 */
final class CoreSections
{
    /** Верхние группы меню в порядке показа. */
    private const GROUPS = [
        'overview' => ['label' => 'Обзор', 'icon' => 'ti-layout-dashboard'],
        'home' => ['label' => 'Дом', 'icon' => 'ti-home'],
        'automation' => ['label' => 'Автоматизация', 'icon' => 'ti-bolt'],
        'system' => ['label' => 'Система', 'icon' => 'ti-settings'],
    ];

    /** Каталоги внутри группы: раскрывающийся список второго уровня. */
    private const CATALOGS = [
        'diagnostics' => ['label' => 'Диагностика', 'icon' => 'ti-stethoscope', 'group' => 'system'],
        'api' => ['label' => 'API и доступ', 'icon' => 'ti-plug', 'group' => 'system'],
        'service' => ['label' => 'Сервис', 'icon' => 'ti-lifebuoy', 'group' => 'system'],
    ];

    /** Максимум на бейдже: длинное число ломало строку меню. */
    private const BADGE_MAX = 99;

    /**
     * Разделы ядра. `group` — id группы меню, подпись даёт `groupLabel()`;
     * `catalog` — каталог второго уровня внутри группы.
     *
     * @return list<array{id:string,label:string,href:string,icon:string,group:string,catalog?:string,menu?:bool}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'dashboard', 'label' => 'Обзор', 'href' => '/admin', 'icon' => 'ti-layout-dashboard', 'group' => 'overview'],
            ['id' => 'status', 'label' => 'Состояние', 'href' => '/status', 'icon' => 'ti-activity', 'group' => 'overview'],

            // Классы — единственный вход в модель дома. Объекты, свойства,
            // методы, значения и история живут внутри карточки класса/объекта.
            ['id' => 'classes', 'label' => 'Классы', 'href' => '/classes', 'icon' => 'ti-hierarchy', 'group' => 'home'],
            ['id' => 'locations', 'label' => 'Локации', 'href' => '/locations', 'icon' => 'ti-map-pin', 'group' => 'home'],

            ['id' => 'scripts', 'label' => 'Скрипты', 'href' => '/scripts', 'icon' => 'ti-file-code', 'group' => 'automation'],
            ['id' => 'events', 'label' => 'События', 'href' => '/events', 'icon' => 'ti-bolt', 'group' => 'automation'],
            ['id' => 'jobs', 'label' => 'Очередь', 'href' => '/jobs', 'icon' => 'ti-stack', 'group' => 'automation'],
            ['id' => 'knowledge_base', 'label' => 'База знаний', 'href' => '/knowledge-base', 'icon' => 'ti-book', 'group' => 'automation'],

            ['id' => 'modules', 'label' => 'Модули', 'href' => '/admin/modules', 'icon' => 'ti-puzzle', 'group' => 'system'],
            ['id' => 'users', 'label' => 'Пользователи и роли', 'href' => '/users', 'icon' => 'ti-users', 'group' => 'system'],
            ['id' => 'settings', 'label' => 'Настройки', 'href' => '/settings', 'icon' => 'ti-settings', 'group' => 'system'],

            ['id' => 'actions_log', 'label' => 'Журнал', 'href' => '/journal', 'icon' => 'ti-list-details', 'group' => 'system', 'catalog' => 'diagnostics'],
            ['id' => 'system_errors', 'label' => 'Ошибки', 'href' => '/errors', 'icon' => 'ti-alert-triangle', 'group' => 'system', 'catalog' => 'diagnostics'],

            ['id' => 'api', 'label' => 'API и webhooks', 'href' => '/api', 'icon' => 'ti-api', 'group' => 'system', 'catalog' => 'api'],
            ['id' => 'api_docs', 'label' => 'API-документация', 'href' => '/admin/api', 'icon' => 'ti-book', 'group' => 'system', 'catalog' => 'api'],
            ['id' => 'tokens', 'label' => 'API-токены', 'href' => '/admin/tokens', 'icon' => 'ti-key', 'group' => 'system', 'catalog' => 'api'],

            ['id' => 'notifications', 'label' => 'Оповещения', 'href' => '/notifications', 'icon' => 'ti-bell', 'group' => 'system', 'catalog' => 'service'],
            ['id' => 'maintenance', 'label' => 'Обслуживание', 'href' => '/admin/maintenance', 'icon' => 'ti-tool', 'group' => 'system', 'catalog' => 'service'],
            ['id' => 'updates', 'label' => 'Обновление системы', 'href' => '/admin/updates', 'icon' => 'ti-download', 'group' => 'system', 'catalog' => 'service'],
            ['id' => 'ui_kit', 'label' => 'UI Kit', 'href' => '/ui-kit', 'icon' => 'ti-palette', 'group' => 'system', 'catalog' => 'service'],

            // Эти страницы есть, но в основном меню не нужны.
            ['id' => 'profile', 'label' => 'Профиль', 'href' => '/admin/profile', 'icon' => 'ti-user-circle', 'group' => 'service', 'menu' => false],
        ];
    }

    /**
     * @return array{id:string,label:string,href:string,icon:string,group:string,catalog?:string,menu?:bool}|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $section) {
            if ($section['id'] === $id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Меню для layout: группы, вложенные каталоги, ключи для Alpine.
     *
     * @param array<string,array{count:int,tone:string,title:string}> $badges
     * @return list<array{id:string,key:string,label:string,icon:string,items:list<array<string,mixed>>,badge:array{count:int,tone:string,label:string,title:string}}>
     */
    public static function menu(array $badges = []): array
    {
        return self::prepare(self::structure(), $badges);
    }

    /**
     * Группы без бейджей: только id, label, icon и вложенность.
     *
     * @return list<array{id:string,label:string,icon:string,items:list<array<string,mixed>>}>
     */
    public static function structure(): array
    {
        $grouped = [];
        foreach (self::all() as $section) {
            if (($section['menu'] ?? true) === false) {
                continue;
            }
            $grouped[$section['group']][$section['catalog'] ?? ''][] = $section;
        }

        $menu = [];
        foreach (self::GROUPS as $id => $meta) {
            if (!isset($grouped[$id])) {
                continue;
            }
            $items = [];
            foreach ($grouped[$id][''] ?? [] as $section) {
                $items[] = self::plainItem($section);
            }
            foreach (self::CATALOGS as $catalogId => $catalog) {
                if ($catalog['group'] !== $id || empty($grouped[$id][$catalogId])) {
                    continue;
                }
                $children = [];
                foreach ($grouped[$id][$catalogId] as $section) {
                    $children[] = self::plainItem($section);
                }
                $items[] = [
                    'id' => $catalogId,
                    'label' => $catalog['label'],
                    'icon' => $catalog['icon'],
                    'catalog' => true,
                    'items' => $children,
                ];
            }
            $menu[] = ['id' => $id, 'label' => $meta['label'], 'icon' => $meta['icon'], 'items' => $items];
        }

        return $menu;
    }

    /**
     * Дополняет любое меню (в том числе меню SDK-модулей и страниц) ключами
     * для Alpine и счётчиками внимания. Ключ группы или пункта стабилен в
     * пределах одной сборки меню, поэтому его можно хранить в localStorage.
     *
     * @param list<array<string,mixed>> $menu
     * @param array<string,array{count:int,tone:string,title:string}> $badges
     * @return list<array<string,mixed>>
     */
    public static function prepare(array $menu, array $badges = []): array
    {
        $prepared = [];
        foreach ($menu as $groupIndex => $group) {
            $groupKey = self::groupKey($group, $groupIndex);
            $items = [];
            foreach ($group['items'] ?? [] as $itemIndex => $item) {
                if (!empty($item['items']) && is_array($item['items'])) {
                    $catalogKey = $groupKey . ':' . self::itemId($item, 'catalog-' . $itemIndex);
                    $children = [];
                    foreach ($item['items'] as $child) {
                        $children[] = self::preparedItem($child, $badges);
                    }
                    $items[] = [
                        'id' => (string)($item['id'] ?? 'catalog-' . $itemIndex),
                        'key' => $catalogKey,
                        'label' => (string)($item['label'] ?? ''),
                        'icon' => (string)($item['icon'] ?? 'ti-folder'),
                        'catalog' => true,
                        'items' => $children,
                        'badge' => self::sumBadges($children),
                    ];
                    continue;
                }
                $items[] = self::preparedItem($item, $badges);
            }

            $prepared[] = [
                'id' => (string)($group['id'] ?? 'group-' . $groupIndex),
                'key' => $groupKey,
                'label' => (string)($group['label'] ?? ''),
                'icon' => (string)($group['icon'] ?? 'ti-folder'),
                'items' => $items,
                'badge' => self::sumBadges($items),
            ];
        }

        return $prepared;
    }

    /**
     * Ключи активной группы и каталога — для раскрытия меню на текущей странице.
     *
     * @param list<array<string,mixed>> $menu подготовленное меню
     * @return array{group:?string,catalog:?string}
     */
    public static function activeKeys(array $menu, string $active): array
    {
        if ($active === '') {
            return ['group' => null, 'catalog' => null];
        }
        foreach ($menu as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if ((string)($item['id'] ?? '') === $active) {
                    return ['group' => (string)$group['key'], 'catalog' => null];
                }
                foreach ($item['items'] ?? [] as $child) {
                    if ((string)($child['id'] ?? '') === $active) {
                        return ['group' => (string)$group['key'], 'catalog' => (string)$item['key']];
                    }
                }
            }
        }

        return ['group' => null, 'catalog' => null];
    }

    /** Подпись бейджа: длинные числа не должны растягивать пункт меню. */
    public static function badgeLabel(int $count): string
    {
        return $count > self::BADGE_MAX ? self::BADGE_MAX . '+' : (string)$count;
    }

    /** Человеческая подпись группы: в `all()` хранится id, а не текст. */
    public static function groupLabel(string $group): string
    {
        return self::GROUPS[$group]['label'] ?? $group;
    }

    /**
     * @param array<string,mixed> $section
     * @return array{id:string,label:string,href:string,icon:string}
     */
    private static function plainItem(array $section): array
    {
        return [
            'id' => (string)$section['id'],
            'label' => (string)$section['label'],
            'href' => (string)$section['href'],
            'icon' => (string)$section['icon'],
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,array{count:int,tone:string,title:string}> $badges
     * @return array<string,mixed>
     */
    private static function preparedItem(array $item, array $badges): array
    {
        $id = (string)($item['id'] ?? '');
        $item['key'] = $id !== '' ? $id : 'item-' . md5((string)($item['label'] ?? ''));
        if ($id !== '' && isset($badges[$id])) {
            $count = max(0, $badges[$id]['count']);
            $item['badge'] = [
                'count' => $count,
                'tone' => self::tone($badges[$id]['tone']),
                'label' => self::badgeLabel($count),
                'title' => $badges[$id]['title'],
            ];
        }

        return $item;
    }

    /**
     * Сумма счётчиков группы: пока группа свёрнута, ошибки всё равно видны.
     *
     * @param list<array<string,mixed>> $items
     * @return array{count:int,tone:string,label:string,title:string}
     */
    private static function sumBadges(array $items): array
    {
        $count = 0;
        $tone = 'info';
        foreach ($items as $item) {
            if (!empty($item['items']) && is_array($item['items'])) {
                $nested = self::sumBadges($item['items']);
                $count += $nested['count'];
                $tone = self::strongerTone($tone, $nested['tone']);
                continue;
            }
            if (!isset($item['badge'])) {
                continue;
            }
            $count += (int)$item['badge']['count'];
            $tone = self::strongerTone($tone, (string)$item['badge']['tone']);
        }

        return [
            'count' => $count,
            'tone' => $tone,
            'label' => self::badgeLabel($count),
            'title' => $count > 0 ? 'Требует внимания: ' . $count : '',
        ];
    }

    private static function strongerTone(string $current, string $next): string
    {
        $rank = ['info' => 0, 'success' => 0, 'warning' => 1, 'danger' => 2];

        return ($rank[$next] ?? 0) > ($rank[$current] ?? 0) ? self::tone($next) : $current;
    }

    private static function tone(string $tone): string
    {
        return in_array($tone, ['info', 'success', 'warning', 'danger'], true) ? $tone : 'info';
    }

    /**
     * @param array<string,mixed> $group
     */
    private static function groupKey(array $group, int $index): string
    {
        $id = trim((string)($group['id'] ?? ''));

        return $id !== '' ? $id : 'group-' . $index;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function itemId(array $item, string $fallback): string
    {
        $id = trim((string)($item['id'] ?? ''));

        return $id !== '' ? $id : $fallback;
    }
}
