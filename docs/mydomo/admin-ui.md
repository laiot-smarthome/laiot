---
title: Админка MyDoMo — новая реализация разделов
audience: agent, user
category: core
tags: mydomo, админка, blade, x-md, разделы, дизайн, dashboard
description: Как устроена веб-админка нового ядра — сборка страниц, компоненты, разделы, данные и что ещё не перенесено из alpha.
order: 14
---

# Админка MyDoMo

Контракты компонентов и примеры использования — в [UI Kit](ui-kit.md).

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
| `script-categories` (Yoyo) | категории сценариев: кнопка в шапке открывает нативную модалку, создание, переименование и удаление перерисовывают список через `/yoyo` POST с CSRF; отдельной страницы и блока на главной нет |
| `x-md.button`, `x-md.card`, `x-md.kpi`, `x-md.badge`, `x-md.alert`, `x-md.hint`, `x-md.empty-state` | базовые элементы alpha-дизайна |
| `x-md.field`, `x-md.input`, `x-md.select`, `x-md.textarea`, `x-md.checkbox`, `x-md.switch`, `x-md.radio`, `x-md.range` | формы; дополнительные атрибуты передаются нативному контролу |
| `x-md.dropdown`, `x-md.tooltip` | локальные раскрывающиеся действия и подсказки на Alpine |
| `x-md.choice-group`, `x-md.device-card`, `x-md.sensor`, `x-md.avatar-group`, `x-md.button-group` | выбор карточками и общие элементы панели |
| `x-md.light-control`, `x-md.thermostat`, `x-md.scene-card`, `x-md.meter` | элементы управления и показателей умного дома; действия и сохранение подключает страница |
| `x-md.data-table` | локальная таблица Tabulator с поиском, сортировкой и страницами; без JavaScript показывается обычная таблица |
| `x-md.code` | редактор кода: textarea-фолбэк без JS и CodeMirror 6 отдельным lazy-chunk (`mydomo-codeeditor.js/css`), только при наличии `textarea[data-code-editor]` |

`x-md.select` с `searchable` использует локальный Tom Select; `multiple`
разрешает несколько значений. Таблица принимает `columns` с ключами `key`,
`label` и `rows` с соответствующими значениями. Ячейки таблицы выводятся
как текст. Она предназначена для уже загруженных небольших наборов; поиск
по серверным данным и авторизация остаются в Yoyo и сервисах ядра. При
удалении узла Alpine освобождает экземпляры Tom Select и Tabulator.

Однократное уведомление: `$store.mdToasts.push({title, text, tone, duration})`.
Длительность задаётся в миллисекундах; `0` оставляет сообщение до закрытия.

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
| Классы | `/classes` | `ClassRepository`, `PropertyRepository`, `MethodRepository`, `ObjectRepository` | дерево классов и подклассов, карточка с вкладками «Основное», «Свойства», «Методы», «Объекты»; наследование и происхождение членов видны в списках |
| Объект класса | `/objects/{id}/edit` | `ObjectRepository`, `PropertyRepository`, `ValueRepository`, `PropertyValueService`, `HistoryRepository` | значения и объектные свойства/методы, история и журнал; только у объекта есть значения |
| Локации | `/locations` | `LocationRepository` | поиск, приоритет, число объектов |
| Скрипты | `/scripts` | `ScriptRepository` | поиск, фильтр категории, категории в модалке `script-categories`, импорт/экспорт JSON, запуск по системному событию, проверка синтаксиса кода при сохранении |
| События | `/events` | `PropertyEventRepository` | фильтры типа и статуса, журнал цепочки; фильтры живут в Yoyo-компоненте `events-table` |
| Очередь | `/jobs` | `Queue` | статистика, ожидающие, упавшие, повтор и удаление |
| База знаний | `/knowledge-base` | `KnowledgeBase` | поиск, категории, статья с оглавлением и `md-prose` |
| Пользователи | `/users` | `UserRepository`, `RoleRepository` | поиск, роли, администратор, soft delete |
| Роли | `/roles` | `RoleRepository` | каталог ролей и прав |
| Настройки | `/settings` | `SettingsRepository` | поиск, разделы по префиксу имени, редактирование значения |
| Журнал | `/journal` | `ActionLogRepository` | фильтр типа, поиск, удаление записи |
| Ошибки | `/errors` | `ErrorRepository` | группы одинаковых сообщений, поиск, плоский список, удаление записи или группы |
| Модули | `/admin/modules` | `ModuleInstaller` | состояние модуля (ядро / включён / выключен / не установлен), установка, включение, отключение, удаление из реестра |
| Настройки модуля | `/admin/modules/<id>/settings` | `ModuleSettingsController` | форма по схеме `module.json`, значения в реестре, выбор подписок на свойства объекта |
| API и webhook | `/api` | `WebhookService` | список, секрет один раз, методы, перевыпуск |
| API-документация | `/admin/api` | `Router` | живой список маршрутов и правила авторизации |
| API-токены | `/admin/tokens` | `ApiTokenRepository` | выпуск, срок, отзыв |
| Оповещения | `/notifications` | `NotificationService` | список непрочитанных, явное прочтение |
| Обслуживание | `/admin/maintenance` | `MaintenanceMode` | включение и выключение режима |
| UI Kit | `/ui-kit` | Blade-компоненты и Yoyo | примеры компонентов; ссылка в меню «Система» |
| Профиль | `/admin/profile` | `UserRepository` | имя и смена своего пароля |

«Классы» — единственный пункт меню для модели дома. Объекты, свойства,
методы, значения и история — не отдельные разделы, а вкладки класса и
объекта. Подкласс наследует свойства/методы всех предков; у объекта можно
добавить собственное свойство или метод, которое перекрывает классовое с
тем же названием. Значение существует только у объекта: карточка класса не
показывает и не принимает значения, даже если объектов у класса ещё нет.

### Проверка кода при сохранении

Код сценариев, методов класса/объекта и PHP-обработчика webhook проверяется
до записи в базу (`PhpSyntaxValidator`, разбор `token_get_all` с
`TOKEN_PARSE`). Невалидный код не сохраняется: репозиторий бросает
`InvalidUserCode` с номером строки, форма показывает ошибку в общем
оповещении и внутри редактора, а `x-md.code` подсвечивает строку и переводит
на неё курсор CodeMirror. Импорт JSON с невалидным сценарием пропускает
такую запись и сообщает о ней в результате. Проверка не запускает код и не
является sandbox: доверенный PHP по-прежнему выполняется при запуске.

Инструкции по запуску и выполнению вынесены в отдельную карточку, а не в
карточку редактора: так подсказка не смешивается с полем кода.

## 4. Обзор

`DashboardStats` (`src/Panel/DashboardStats.php`) собирает счётчики и
последние записи. Каждый запрос защищён проверкой таблицы: обзор обязан
открываться на свежей установке без миграций. Страница использует готовые
классы `md-dash*` из `ui-v2.css`, поэтому не требует нового CSS и графиков.
График активности и живые KPI alpha-панели не перенесены: для них нужен
отдельный lazy-chunk и endpoint.

## 5. Проверки

- `tests/Feature/AdminSectionsTest.php` — обзор, единый раздел классов,
  подклассы и наследование, вкладки карточки объекта, общий диалог
  подтверждения и отсутствие legacy-ссылок `/module/` в ядровых страницах.
- `tests/Feature/ClassHierarchyTest.php` — глубина дерева, переопределение
  свойств/методов, объектные члены и защита от циклов.
- Профильные тесты разделов (`CoreObjectsTest`, `CorePropertiesTest`,
  `CoreMethodsTest`, `CoreEventsTest`, `CoreJobsTest`, `CoreScriptsTest`,
  `CoreUsersTest`, `CoreSettingsTest`, `CoreActionsLogTest`,
  `CoreSystemErrorsTest`, `ModulesPageTest`, `KnowledgeBaseTest`,
  `ApiWebhooksTest`, `PropertyHistoryTest`, `ScriptEventTriggerTest`).
- `phpstan`, `php-cs-fixer`, `php vendor/bin/phpunit`.
- Внешний вид проверяется вручную в браузере: светлая и тёмная тема,
  1500 px и 390 px. Изолированные тесты проверяют разметку и состояния,
  но не заменяют визуальную проверку.

## 6. Что ещё не перенесено из alpha

Перечислено, чтобы не считать админку законченной:

- trace-drawer цепочки свойства (история значения с графиком уже есть,
  причинная цепочка OnChange — нет);
- живые KPI и график активности на обзоре, как в alpha-панели;
- клиентская фильтрация таблиц без перезагрузки;
- отметка «просмотрено» и история повторов ошибок (сейчас есть группы и
  удаление, но нет отдельного статуса прочтения).

Уже перенесены: редактор кода CodeMirror 6 (ленивый чанк, PHP-подсветка,
oneDark), drawer истории свойства с периодом, статистикой, графиком
ApexCharts и журналом записей, а также Yoyo-компоненты интерактивных блоков
(`events-table`, `property-history`, `folder-picker`, `script-categories`).

## 7. Как повторить проверку

Страницы и данные проверяются без браузера и без рабочей БД:

```bash
php vendor/bin/phpunit --filter AdminSectionsTest
php vendor/bin/phpunit --filter PropertyHistoryTest
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

