<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Настройки MyDoMo на Blade.
 *
 * Таблица `settings` остаётся legacy alpha (uppercase-колонки); доступ идёт
 * только через SettingsRepository, без SQL в контроллере.
 */
final readonly class SettingsController extends Controller
{
    /**
     * Человеческие названия известных префиксов. Неизвестные показываются как есть,
     * чтобы новая настройка не исчезала из-за отсутствия перевода.
     */
    private const GROUP_LABELS = [
        'SITE' => 'Сайт',
        'BASE' => 'Адрес',
        'CORE' => 'Ядро',
        'DB' => 'База данных',
        'MQTT' => 'MQTT',
        'REDIS' => 'Redis',
        'AI' => 'ИИ',
        'SMTP' => 'Почта',
        'TELEGRAM' => 'Telegram',
    ];
    public function __construct(
        private Factory $view,
        private SettingsRepository $settings,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $settings = $this->settings->details($search);

        // Группировка по префиксу имени: alpha делила настройки на разделы,
        // но схема новой установки не хранит раздел отдельной колонкой.
        $groups = [];
        foreach ($settings as $setting) {
            $groups[$this->groupKey($setting['name'])][] = $setting;
        }
        uksort($groups, static function (string $left, string $right): int {
            if ($left === 'Общие') {
                return -1;
            }
            if ($right === 'Общие') {
                return 1;
            }

            return strnatcasecmp($left, $right);
        });

        $groupKeys = array_keys($groups);
        $requested = trim((string)$request->query->get('group', ''));
        $activeGroup = in_array($requested, $groupKeys, true) ? $requested : (string)($groupKeys[0] ?? '');
        $visible = $search === '' && $activeGroup !== ''
            ? $groups[$activeGroup]
            : $settings;
        if ($search !== '') {
            $activeGroup = '';
        }

        $content = $this->view->make('pages.settings.index', [
            'settings' => $visible,
            'groups' => array_map(static fn (array $rows): int => count($rows), $groups),
            'groupLabels' => array_combine(
                array_keys($groups),
                array_map(static fn (string $key): string => self::GROUP_LABELS[$key] ?? $key, array_keys($groups))
            ) ?: [],
            'activeGroup' => $activeGroup,
            'search' => $search,
            'total' => count($settings),
            'done' => (string)$request->query->get('done', '') !== '',
        ])->render();

        return $this->panel->render(new PanelPage('Настройки', $content, 'settings'));
    }

    /**
     * «AI_API_KEY» → «AI», «BASE_URL» → «BASE»; без подчёркивания — «Общие».
     */
    private function groupKey(string $name): string
    {
        $position = strpos($name, '_');
        if ($position === false || $position === 0) {
            return 'Общие';
        }

        return mb_strtoupper(substr($name, 0, $position));
    }

    public function edit(string $id): Response
    {
        $setting = $this->settings->find((int)$id);
        if ($setting === null) {
            return $this->panel->render(new PanelPage('Настройки', '<p class="md-alert md-alert--danger">Настройка не найдена.</p>', 'settings'), 404);
        }

        $content = $this->view->make('pages.settings.edit', [
            'setting' => $setting,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Настройки', $content, 'settings'));
    }

    public function save(string $id, Request $request): Response
    {
        $setting = $this->settings->find((int)$id);
        if ($setting === null) {
            return new RedirectResponse('/settings');
        }

        $this->settings->save((int)$id, (string)$request->request->get('value', ''));

        return new RedirectResponse('/settings?done=1&group=' . rawurlencode($this->groupKey($setting['name'])));
    }
}
