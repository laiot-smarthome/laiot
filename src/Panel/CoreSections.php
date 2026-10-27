<?php

declare(strict_types=1);

namespace MajorDoMo\Panel;

/**
 * Реестр ядровых разделов админки.
 *
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
    /**
     * @return list<array{id:string,label:string,href:string,icon:string,group:string,menu?:bool}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'dashboard', 'label' => 'Обзор', 'href' => '/admin', 'icon' => 'ti-layout-dashboard', 'group' => 'Обзор'],
            ['id' => 'status', 'label' => 'Состояние', 'href' => '/status', 'icon' => 'ti-activity', 'group' => 'Обзор'],

            // Классы — единственный вход в модель дома. Объекты, свойства,
            // методы, значения и история живут внутри карточки класса/объекта.
            ['id' => 'classes', 'label' => 'Классы', 'href' => '/classes', 'icon' => 'ti-hierarchy', 'group' => 'Дом'],
            ['id' => 'locations', 'label' => 'Локации', 'href' => '/locations', 'icon' => 'ti-map-pin', 'group' => 'Дом'],

            ['id' => 'scripts', 'label' => 'Скрипты', 'href' => '/scripts', 'icon' => 'ti-file-code', 'group' => 'Автоматизация'],
            ['id' => 'events', 'label' => 'События', 'href' => '/events', 'icon' => 'ti-bolt', 'group' => 'Автоматизация'],
            ['id' => 'jobs', 'label' => 'Очередь', 'href' => '/jobs', 'icon' => 'ti-stack', 'group' => 'Автоматизация'],
            ['id' => 'knowledge_base', 'label' => 'База знаний', 'href' => '/knowledge-base', 'icon' => 'ti-book', 'group' => 'Автоматизация'],

            ['id' => 'modules', 'label' => 'Модули', 'href' => '/admin/modules', 'icon' => 'ti-puzzle', 'group' => 'Система'],
            ['id' => 'users', 'label' => 'Пользователи и роли', 'href' => '/users', 'icon' => 'ti-users', 'group' => 'Система'],
            ['id' => 'settings', 'label' => 'Настройки', 'href' => '/settings', 'icon' => 'ti-settings', 'group' => 'Система'],
            ['id' => 'actions_log', 'label' => 'Журнал', 'href' => '/journal', 'icon' => 'ti-list-details', 'group' => 'Система'],
            ['id' => 'system_errors', 'label' => 'Ошибки', 'href' => '/errors', 'icon' => 'ti-alert-triangle', 'group' => 'Система'],
            ['id' => 'api', 'label' => 'API и webhooks', 'href' => '/api', 'icon' => 'ti-api', 'group' => 'Система'],
            ['id' => 'api_docs', 'label' => 'API-документация', 'href' => '/admin/api', 'icon' => 'ti-book', 'group' => 'Система'],
            ['id' => 'tokens', 'label' => 'API-токены', 'href' => '/admin/tokens', 'icon' => 'ti-key', 'group' => 'Система'],
            ['id' => 'notifications', 'label' => 'Оповещения', 'href' => '/notifications', 'icon' => 'ti-bell', 'group' => 'Система'],
            ['id' => 'maintenance', 'label' => 'Обслуживание', 'href' => '/admin/maintenance', 'icon' => 'ti-tool', 'group' => 'Система'],
            ['id' => 'ui_kit', 'label' => 'UI Kit', 'href' => '/ui-kit', 'icon' => 'ti-palette', 'group' => 'Система'],

            // Эти страницы есть, но в основном меню не нужны.
            ['id' => 'profile', 'label' => 'Профиль', 'href' => '/admin/profile', 'icon' => 'ti-user-circle', 'group' => 'Служебное', 'menu' => false],
        ];
    }

    /**
     * @return array{id:string,label:string,href:string,icon:string,group:string,menu?:bool}|null
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
     * @return list<array{label:string,items:list<array{id:string,label:string,href:string,icon:string}>}>
     */
    public static function menu(): array
    {
        $groups = [];
        foreach (self::all() as $section) {
            if (($section['menu'] ?? true) === false) {
                continue;
            }
            $group = $section['group'];
            unset($section['group'], $section['menu']);
            $groups[$group][] = $section;
        }

        $menu = [];
        foreach (['Обзор', 'Дом', 'Автоматизация', 'Система'] as $label) {
            if (isset($groups[$label])) {
                $menu[] = ['label' => $label, 'items' => $groups[$label]];
            }
        }

        return $menu;
    }
}
