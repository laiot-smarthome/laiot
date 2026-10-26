---
title: Админка MyDoMo — новая реализация разделов
audience: agent, user
category: core
tags: mydomo, админка, blade, x-md, разделы, дизайн, dashboard
description: Как устроена веб-админка нового ядра — сборка страниц, компоненты, разделы, данные и что ещё не перенесено из alpha.
order: 14
---

# Админка MyDoMo

Это состояние веб-админки нового ядра после переписывания разделов с
alpha-модулей. Дизайн остаётся alpha/MDJUI, реализация — новая: Blade,
Alpine, `x-md.*` и `md-*` из `resources/css/ui-system.css`, без jQuery,
JTemplate и старой панели.

## 1. Как собирается страница

```text
routes/web.php
  → контроллер (src/Http/Controllers, src/Http/Controllers/Core)
  → Blade-представление (resources/views/pages)
  → PanelRenderer (src/Http/PanelRenderer.php)
  → layouts.alpha (resources/views/layouts/alpha.blade.php)
  → public/assets/admin/mydomo.css + mydomo.js
```

- `PanelPage` — заголовок страницы (хлебная крошка) и активный пункт меню.
- `CoreSections` — единственный реестр ядровых разделов: меню, группы,
  ссылки. Ядровый раздел не модуль: у него нет `module.json` и `ModuleDb`.
- SDK-модули (MQTT, Zigbee, сторонние) добавляют свои пункты через
  `ModuleManager::menuItems()` и живут на `/module/<id>`.
- Новые страницы не создают layout, токены и вторую библиотеку иконок.

## 2. Общие компоненты

Интерактивные блоки внутри страницы собираются **Yoyo-компонентами**, а не
отдельными JSON-эндпоинтами: серверный компонент сам перерисовывает свой HTML
по действию (`yoyo:get`/`yoyo:post` через `/yoyo`). Отдельные `/api/v1/panel/*`
остаются только для виджетов оболочки, которые живут вне Blade-страницы
(поиск в шапке, колокольчик уведомлений), и для внешнего API.

Компонент — класс в `src/Yoyo` + шаблон `resources/views/yoyo/<name>.blade.php`.
Имя `kebab-case` Yoyo сам разрешает в класс `MajorDoMo\Yoyo\StudlyCase` по
namespace из конфигурации, поэтому ручной `Yoyo::registerComponent()` не
нужен. Состояние — публичные свойства, действие — метод, данные — сервис
ядра; компонент ничего не знает про HTTP-роуты.

| Компонент | Назначение |
| --- | --- |
| `x-md.page-hero` | заголовок раздела: eyebrow, title, lede, метаданные, действия |
| `x-md.table` | каркас таблицы: тулбар, прокрутка, пустое состояние, футер; `variant="tree"` для дерева классов |
| `x-md.tabs` | серверные вкладки: ссылки с `?tab=`, без Bootstrap-JS |
| `x-md.icon-button` | иконочная кнопка строки таблицы с `aria-label` и `title` |
| `x-md.modal` + `mdConfirmDialog` | общий диалог подтверждения: формы помечаются `data-md-confirm`, а `window.confirm()` не используется |
| `property-history` (Yoyo) | история свойства: кнопка в строке значения и drawer, который компонент перерисовывает сам; период 24ч/7д/30д/всё, текущее значение, статистика, график и записи. Отдельного JSON-роута нет — данные считает `PropertyHistoryService` на сервере |
| `events-table` (Yoyo) | журнал событий: поиск, тип и статус — состояния компонента; таблица перерисовывается через `/yoyo`, без GET-перезагрузки и без JSON-эндпоинта |
| `x-md.button`, `x-md.card`, `x-md.kpi`, `x-md.badge`, `x-md.alert`, `x-md.hint`, `x-md.empty-state` | базовые элементы alpha-дизайна |
| `x-md.field`, `x-md.input`, `x-md.select`, `x-md.textarea`, `x-md.checkbox`, `x-md.switch` | формы |
| `x-md.code` | редактор кода: textarea-фолбэк без JS и CodeMirror 6 отдельным lazy-chunk (`mydomo-codeeditor.js/css`), только при наличии `textarea[data-code-editor]` |

Раскладка страниц и форм использует готовые классы `md-ui-page`,
`md-ui-page--stack`, `md-ui-hero`, `md-ui-kpi-grid`, `md-ui-card-grid`,
`md-ui-form*`, `md-table*`, `md-dash*`. В `resources/css/mydomo.css`
добавлены только утилиты, которых не было в alpha-слое: `.md-ui-actions`,
`.md-inline-form`, `.md-table__muted`, `.md-table__value`, `.md-table__code`,
`.md-ui-search`, стили `.md-prose` для Markdown базы знаний и мобильные
правила тулбара.

## 3. Разделы и данные

| Раздел | Маршрут | Источник данных | Возможности |
| --- | --- | --- | --- |
| Обзор | `/admin` | `DashboardStats`, `Queue` | состояние, счётчики, активность свойств, очередь, ошибки, действия, быстрые ссылки |
| Состояние | `/status` | `DatabaseManager`, `CacheStore`, модули | проверка БД, кэша, SDK-модулей |
| Объекты | `/objects` | `ObjectRepository` | поиск, фильтры класса и локации, карточка с вкладками «Основное», «Свойства», «Методы», «История», «Журнал» |
| Классы | `/classes` | `ClassRepository`, `ObjectRepository` | дерево классов с раскрытием объектов, счётчики, вкладки карточки |
| Свойства | `/properties` | `PropertyRepository` | поиск, фильтр класса, тип, история, OnChange |
| Методы | `/methods` | `MethodRepository` | поиск, фильтр класса, редактор кода без запуска |
| Значения | `/values` | `ValueRepository`, `PropertyValueService` | поиск, фильтр по объекту, правка значения через общий сервис, drawer истории с графиком |
| История | `/history` | `HistoryRepository` | поиск, фильтр по объекту, drawer истории с графиком |
| Локации | `/locations` | `LocationRepository` | поиск, приоритет, число объектов |
| Скрипты | `/scripts` | `ScriptRepository` | поиск, фильтр категории, категории с переименованием и удалением, импорт/экспорт JSON, признак периодического запуска |
| События | `/events` | `PropertyEventRepository` | фильтры типа и статуса, журнал цепочки; фильтры живут в Yoyo-компоненте `events-table` |
| Очередь | `/jobs` | `Queue` | статистика, ожидающие, упавшие, повтор и удаление |
| База знаний | `/knowledge-base` | `KnowledgeBase` | поиск, категории, статья с оглавлением и `md-prose` |
| Пользователи | `/users` | `UserRepository`, `RoleRepository` | поиск, роли, администратор, soft delete |
| Роли | `/roles` | `RoleRepository` | каталог ролей и прав |
| Настройки | `/settings` | `SettingsRepository` | поиск, разделы по префиксу имени, редактирование значения |
| Журнал | `/journal` | `ActionLogRepository` | фильтр типа, поиск, удаление записи |
| Ошибки | `/errors` | `ErrorRepository` | группы одинаковых сообщений, поиск, плоский список, удаление записи или группы |
| Модули | `/admin/modules` | `ModuleInstaller` | состояние модуля (ядро / включён / выключен / не установлен), установка, включение, отключение, удаление из реестра |
| API и webhook | `/api` | `WebhookService` | список, секрет один раз, методы, перевыпуск |
| API-документация | `/admin/api` | `Router` | живой список маршрутов и правила авторизации |
| API-токены | `/admin/tokens` | `ApiTokenRepository` | выпуск, срок, отзыв |
| Оповещения | `/notifications` | `NotificationService` | список непрочитанных, явное прочтение |
| Обслуживание | `/admin/maintenance` | `MaintenanceMode` | включение и выключение режима |
| Профиль | `/admin/profile` | `UserRepository` | имя и смена своего пароля |

## 4. Обзор

`DashboardStats` (`src/Panel/DashboardStats.php`) собирает счётчики и
последние записи. Каждый запрос защищён проверкой таблицы: обзор обязан
открываться на свежей установке без миграций. Страница использует готовые
классы `md-dash*` из `ui-v2.css`, поэтому не требует нового CSS и графиков.
График активности и живые KPI alpha-панели не перенесены: для них нужен
отдельный lazy-chunk и endpoint.

## 5. Проверки

- `tests/Feature/AdminSectionsTest.php` — обзор, фильтры объектов, вкладки
  карточки, дерево классов, общий диалог подтверждения и отсутствие
  legacy-ссылок `/module/` в ядровых страницах.
- Профильные тесты разделов (`CoreObjectsTest`, `CorePropertiesTest`,
  `CoreMethodsTest`, `CoreEventsTest`, `CoreJobsTest`, `CoreScriptsTest`,
  `CoreUsersTest`, `CoreSettingsTest`, `CoreActionsLogTest`,
  `CoreSystemErrorsTest`, `ModulesPageTest`, `KnowledgeBaseTest`,
  `ApiWebhooksTest`, `HistoryChartTest`).
- `phpstan`, `php-cs-fixer`, `php vendor/bin/phpunit`.
- Визуально проверены 1500 px и 390 px, светлая и тёмная тема.

## 6. Что ещё не перенесено из alpha

Перечислено, чтобы не считать админку законченной:

- объектные методы-переопределения (alpha хранил методы объекта поверх
  класса) — в новой схеме методы только у класса;
- унаследованные свойства и методы классов и их отдельные секции;
- trace-drawer цепочки свойства (история значения с графиком уже есть,
  причинная цепочка OnChange — нет);
- живые KPI и график активности на обзоре, как в alpha-панели;
- клиентская фильтрация таблиц без перезагрузки;
- отметка «просмотрено» и история повторов ошибок (сейчас есть группы и
  удаление, но нет отдельного статуса прочтения);
- Yoyo-компоненты для интерактивных блоков.

Уже перенесены: редактор кода CodeMirror 6 (ленивый чанк, PHP-подсветка,
oneDark) и drawer истории свойства с периодом, статистикой, графиком
ApexCharts и журналом записей.

## 7. Как повторить проверку

Страницы и данные проверяются без браузера и без рабочей БД:

```bash
php vendor/bin/phpunit --filter AdminSectionsTest
php vendor/bin/phpunit --filter HistoryChartTest
php vendor/bin/phpunit
php vendor/bin/phpstan analyse --memory-limit=1G
php vendor/bin/php-cs-fixer fix --dry-run --config=.php-cs-fixer.dist.php
php tests/docs_quality_smoke.php
npm run build:mydomo
```

`AdminSectionsTest` поднимает полную схему миграциями на SQLite в памяти,
создаёт объекты, свойства и событие, затем проверяет обзор, фильтры,
вкладки карточки, дерево классов и отсутствие ссылок `/module/`.

Скриншоты для визуальной проверки лежали локально в `docs/ui-redesign/`
(каталог в `.gitignore`): обзор, объекты, карточка объекта, классы, скрипты,
события, очередь, пользователи, настройки, webhook, база знаний, мобильная
и тёмная темы. В репозиторий они не входят — повторяемую проверку даёт
тест, а не PNG.

