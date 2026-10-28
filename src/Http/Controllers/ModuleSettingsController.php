<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Modules\ModuleSettings;
use MajorDoMo\Modules\ModuleSubscriptions;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Настройки установленного SDK-модуля и выбор его подписок.
 *
 * Форма строится по схеме `module.json`, значения хранятся в
 * `module_registry.settings`; модуль читает их через `$context->settings()`.
 * Подписки выбирает пользователь: страница показывает действующие свойства
 * выбранного объекта, а не заставляет модуль рисовать собственную таблицу.
 *
 * 2026-10-28 — результаты сохранения передаются одноразовым Notify.
 * 2026-10-27 — общий экран настроек и подписок вместо самодельных форм модулей.
 */
final readonly class ModuleSettingsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ModuleManager $modules,
        private ObjectRepository $objects,
        private PropertyRepository $properties,
        private PropertySubscriptionRepository $subscriptionRepository,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function show(string $id, Request $request): Response
    {
        $module = $this->installed($id);
        if ($module instanceof RedirectResponse) {
            return $module;
        }

        $schema = $this->schema($module);
        $hasSubscriber = $this->hasSubscriber($module);
        if ($schema === [] && !$hasSubscriber) {
            return $this->fail('Модуль не объявляет настройки или подписки.');
        }

        $settings = ModuleSettings::fromStored($schema, $this->modules->registry()->settings($id));
        $subscriptions = $hasSubscriber ? $this->subscriptionRepository->forModule($id) : [];
        $objectId = (int)$request->query->get('object_id', 0);
        $properties = [];
        $attached = [];
        if ($hasSubscriber && $objectId > 0 && $this->objects->find($objectId) !== null) {
            $properties = $this->properties->forObject($objectId);
            foreach ($subscriptions as $subscription) {
                if ($subscription['object_id'] === $objectId) {
                    $attached[$subscription['property_id']] = true;
                }
            }
        } else {
            $objectId = 0;
        }

        $content = $this->view->make('pages.module-settings', [
            'module' => [
                'id' => $id,
                'name' => (string)($module['name'] ?? $id),
                'version' => (string)($module['version'] ?? '0.0.0'),
            ],
            'schema' => $schema,
            'values' => $settings->all(),
            'hasSubscriber' => $hasSubscriber,
            'subscriptions' => $subscriptions,
            'objects' => $hasSubscriber ? $this->objects->all() : [],
            'objectId' => $objectId,
            'properties' => $properties,
            'attached' => $attached,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Настройки модуля', $content, 'modules'));
    }

    public function save(string $id, Request $request): Response
    {
        $module = $this->installed($id);
        if ($module instanceof RedirectResponse) {
            return $module;
        }
        $schema = $this->schema($module);
        if ($schema === []) {
            return $this->fail('Модуль не объявляет настройки.');
        }

        try {
            $values = ModuleSettings::cast($schema, $request->all());
        } catch (InvalidArgumentException $error) {
            return $this->settingsError($id, $error->getMessage());
        }

        $this->modules->registry()->saveSettings($id, $values);

        return $this->panel->redirect('/admin/modules/' . $id . '/settings', 'Настройки сохранены. Для работающей интеграции изменения вступят в силу после её перезапуска.');
    }

    public function saveSubscriptions(string $id, Request $request): Response
    {
        $module = $this->installed($id);
        if ($module instanceof RedirectResponse) {
            return $module;
        }
        if (!$this->hasSubscriber($module)) {
            return $this->fail('Модуль не объявляет property_subscriber и не может подписываться на свойства.');
        }

        $objectId = (int)$request->input('object_id', 0);
        if ($objectId <= 0 || $this->objects->find($objectId) === null) {
            return $this->settingsError($id, 'Выберите объект.');
        }

        $selected = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int)$value,
            (array)$request->input('properties', [])
        )));
        $available = array_map(
            static fn (array $property): int => (int)$property['id'],
            $this->properties->forObject($objectId)
        );
        foreach ($selected as $propertyId) {
            if (!in_array($propertyId, $available, true)) {
                return $this->settingsError($id, 'Свойство ' . $propertyId . ' не действует у выбранного объекта.');
            }
        }

        $current = [];
        foreach ($this->subscriptionRepository->forObject($objectId) as $subscription) {
            if ($subscription['module'] === $id) {
                $current[] = (int)$subscription['property_id'];
            }
        }

        $subscriptions = new ModuleSubscriptions($id, true, $this->subscriptionRepository, $this->properties, $this->objects);
        try {
            foreach ($selected as $propertyId) {
                if (!in_array($propertyId, $current, true)) {
                    $subscriptions->attach($objectId, $propertyId);
                }
            }
            foreach ($current as $propertyId) {
                if (!in_array($propertyId, $selected, true)) {
                    $subscriptions->detach($objectId, $propertyId);
                }
            }
        } catch (InvalidArgumentException | RuntimeException $error) {
            return $this->settingsError($id, $error->getMessage());
        }

        return $this->panel->redirect('/admin/modules/' . $id . '/settings', 'Подписки обновлены.');
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function installed(string $id): array|RedirectResponse
    {
        $module = $this->modules->discovered()[$id] ?? null;
        if ($module === null) {
            return $this->fail('Модуль не найден: ' . $id);
        }
        if ((bool)($module['core'] ?? false)) {
            return $this->fail('Core-модуль не настраивается через реестр.');
        }
        if ($this->modules->registryRow($id) === null) {
            return $this->fail('Сначала установите модуль.');
        }

        return $module;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}>
     */
    private function schema(array $module): array
    {
        $schema = $module['manifest']['settings'] ?? [];

        return is_array($schema) ? $schema : [];
    }

    /**
     * @param array<string, mixed> $module
     */
    private function hasSubscriber(array $module): bool
    {
        $class = $module['manifest']['property_subscriber'] ?? null;

        return is_string($class) && $class !== '';
    }

    private function fail(string $message): RedirectResponse
    {
        return $this->panel->redirect('/admin/modules', $message, 'danger');
    }

    private function settingsError(string $id, string $message): RedirectResponse
    {
        return $this->panel->redirect('/admin/modules/' . $id . '/settings', $message, 'danger');
    }
}
