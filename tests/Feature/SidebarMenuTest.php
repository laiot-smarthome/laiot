<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Services\Panel\CoreSections;
use PHPUnit\Framework\TestCase;

/**
 * Меню панели: группы, каталоги, бейджи и ключи раскрытия.
 *
 * Проверка не поднимает приложение: состав меню — статический реестр, а
 * разметка проверяется в AlphaLayoutTest и в тестах страниц.
 * 2026-10-29 — каталог обслуживания включает штатный раздел обновления системы.
 */
final class SidebarMenuTest extends TestCase
{
    public function testMenuIsSplitIntoGroupsAndCatalogs(): void
    {
        $menu = CoreSections::structure();

        self::assertSame(['overview', 'home', 'automation', 'system'], array_column($menu, 'id'));

        $catalogs = [];
        $flat = [];
        foreach ($menu[3]['items'] as $item) {
            if (!empty($item['items'])) {
                $catalogs[$item['id']] = array_column($item['items'], 'id');
                continue;
            }
            $flat[] = $item['id'];
        }

        self::assertSame(['modules', 'users', 'settings'], $flat);
        self::assertSame(['diagnostics', 'api', 'service'], array_keys($catalogs));
        self::assertSame(['actions_log', 'system_errors'], $catalogs['diagnostics']);
        self::assertSame(['api', 'api_docs', 'tokens'], $catalogs['api']);
        self::assertSame(['notifications', 'maintenance', 'updates', 'ui_kit'], $catalogs['service']);

        $ids = [];
        foreach ($menu as $group) {
            foreach ($group['items'] as $item) {
                $ids[] = $item['id'];
                foreach ($item['items'] ?? [] as $child) {
                    $ids[] = $child['id'];
                }
            }
        }
        self::assertNotContains('profile', $ids, 'Профиль живёт в подвале меню, а не в списке разделов.');
    }

    public function testBadgesAreAttachedAndSummedPerGroup(): void
    {
        $badges = [
            'system_errors' => ['count' => 3, 'tone' => 'danger', 'title' => 'Ошибок: 3'],
            'jobs' => ['count' => 0, 'tone' => 'warning', 'title' => 'Очередь: 0'],
            'notifications' => ['count' => 4, 'tone' => 'warning', 'title' => 'Оповещения: 4'],
        ];
        $menu = CoreSections::menu($badges);

        $system = $this->group($menu, 'system');
        self::assertSame(7, $system['badge']['count']);
        self::assertSame('danger', $system['badge']['tone']);

        $errors = $this->item($menu, 'system', 'system_errors');
        self::assertSame('3', $errors['badge']['label']);
        self::assertSame('danger', $errors['badge']['tone']);

        // Нулевой счётчик тоже остаётся в разметке: JS не создаёт бейдж заново.
        $jobs = $this->item($menu, 'automation', 'jobs');
        self::assertSame(0, $jobs['badge']['count']);
        self::assertSame('0', $jobs['badge']['label']);

        $automation = $this->group($menu, 'automation');
        self::assertSame(0, $automation['badge']['count']);
        self::assertSame('warning', $automation['badge']['tone']);
    }

    public function testActiveSectionOpensGroupAndCatalog(): void
    {
        $menu = CoreSections::menu([]);

        self::assertSame(
            ['group' => 'system', 'catalog' => 'system:diagnostics'],
            CoreSections::activeKeys($menu, 'system_errors')
        );
        self::assertSame(
            ['group' => 'home', 'catalog' => null],
            CoreSections::activeKeys($menu, 'classes')
        );
        self::assertSame(
            ['group' => null, 'catalog' => null],
            CoreSections::activeKeys($menu, 'нет-такого')
        );
    }

    public function testCustomMenuGetsStableKeys(): void
    {
        $prepared = CoreSections::prepare([
            ['label' => 'Без id', 'items' => [
                ['id' => 'notifications', 'label' => 'Оповещения', 'href' => '/notifications', 'icon' => 'ti-bell'],
            ]],
        ], [
            'notifications' => ['count' => 12, 'tone' => 'info', 'title' => 'Непрочитанных: 12'],
        ]);

        self::assertSame('group-0', $prepared[0]['key']);
        self::assertSame('notifications', $prepared[0]['items'][0]['key']);
        self::assertSame(12, $prepared[0]['items'][0]['badge']['count']);
    }

    public function testLongCountIsCapped(): void
    {
        self::assertSame('99+', CoreSections::badgeLabel(120));
        self::assertSame('7', CoreSections::badgeLabel(7));
    }

    public function testGroupLabelIsReadableForSearch(): void
    {
        self::assertSame('Автоматизация', CoreSections::groupLabel('automation'));
        self::assertSame('Система', CoreSections::groupLabel('system'));
        self::assertSame('неизвестно', CoreSections::groupLabel('неизвестно'));
    }

    /**
     * @param list<array<string,mixed>> $menu
     * @return array<string,mixed>
     */
    private function group(array $menu, string $id): array
    {
        foreach ($menu as $group) {
            if ($group['id'] === $id) {
                return $group;
            }
        }
        self::fail('Группа меню не найдена: ' . $id);
    }

    /**
     * @param list<array<string,mixed>> $menu
     * @return array<string,mixed>
     */
    private function item(array $menu, string $groupId, string $itemId): array
    {
        foreach ($this->group($menu, $groupId)['items'] as $item) {
            if (($item['id'] ?? '') === $itemId) {
                return $item;
            }
            foreach ($item['items'] ?? [] as $child) {
                if (($child['id'] ?? '') === $itemId) {
                    return $child;
                }
            }
        }
        self::fail('Пункт меню не найден: ' . $itemId);
    }
}
