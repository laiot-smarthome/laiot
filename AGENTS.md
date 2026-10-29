# AGENTS.md

Обязательная точка входа для работы с репозиторием. Активная ветка —
**beta** (проект LaIOT), отдельное приложение без обратной совместимости
с alpha. Предыдущие ветки `MyDoMo` и `alpha` остаются источником истории
и примеров для переработки, но не текущей реализации.

Перед изменениями читать [docs/laiot/README.md](docs/laiot/README.md),
[правила LaIOT](docs/laiot/AGENTS.md),
[архитектуру](docs/laiot/architecture.md) и
[мастер-план](docs/todo/laiot.md).

Коротко, что нельзя нарушать:
 
- Рабочая платформа — Linux/Ubuntu. Windows — локальная разработка и
  доступные проверки; обходы её ограничений в рабочий код не добавлять.
  Процессы, pcntl и systemd проверяются в Linux.
- PHP LaIOT — 8.2+, Illuminate 12 components. Это не полный Laravel;
  фактические версии/возможности проверять по файлам текущей задачи.
- Входные данные проверять через `illuminate/validation` и общий
  `Illuminate\Contracts\Validation\Factory` из DI. Сначала штатные правила
  и `Rule::*`, без собственных движков и повторов required/email/min/max.
  Предметные правила — только при отсутствии штатного аналога; авторизация
  проверяется отдельно. Контракт — [validation.md](docs/laiot/validation.md).
- Секреты интеграций шифровать общим Illuminate Encrypter; модели используют
  штатные `encrypted`/`encrypted:array` и `$hidden`. APP_KEY автоматически
  создаётся установщиком, повторно не генерируется. Контракт —
  [encryption.md](docs/laiot/encryption.md).
- Файлы сохранять через `LocalFiles`; модуль использует только
  `$context->files()` в `var/files/modules/<id>`. При удалении модуля его
  файлы удаляются без `--purge`. Контракт — [files.md](docs/laiot/files.md).
- Ядровые страницы объектов, пользователей, журналов, API и настроек живут
  в `app/Http/Controllers/Core`, `app/Services/Webhooks`, `resources/views/pages`.
  Они не являются модулями, не получают module.json и ModuleDb.
- Интеграции MQTT/Zigbee и сторонние расширения остаются SDK-модулями в
  `modules/core/<id>` или `modules/<id>`; их код не растворяется в ядре.
  Правила — [modules.md](docs/laiot/modules.md) и [sdk.md](docs/laiot/sdk.md).
- Сохраняется дизайн MDJUI/alpha, а не его старый PHP/JS runtime.
  Перед UI-изменениями читать [admin-ui.md](docs/laiot/admin-ui.md) и живую
  страницу `/ui-kit`; реализация — Blade, Alpine, `x-md.*` и `md-*`,
  без JTemplate, jQuery и старой панели.
- Интерактив внутри страницы — Yoyo-компонент (`app/Yoyo`, действие через
  `/yoyo`), а не новый JSON-эндпоинт. Имя `kebab-case` само разрешается в
  класс `LaIOT\Yoyo\StudlyCase`; ручная регистрация не нужна.
  `/api/v1/panel/*` — только виджеты оболочки (поиск в шапке, уведомления)
  и внешний API.
- Статика — `public/assets`, исходники — `resources/js` и `resources/css`;
  сборка `npm run build`. Библиотеки локально через npm, без CDN и новых
  script/link на библиотеки в шаблоне.
- Результат любой операции админки — через Notify (`x-md.notify` или
  `$store.mdToasts.push()`), справа снизу; не через баннер в контенте.
  Ошибки полей остаются рядом с полями. Постоянные состояния страницы —
  отдельные информационные блоки. Контракт — [ui-kit.md](docs/laiot/ui-kit.md).
- Текст Notify не передавать в GET: Yoyo отправляет `md:notify` через
  `dispatchBrowserEvent()`, обычный POST/redirect — одноразовую сессию
  через `PanelRenderer::redirect()`.
- Новую или изменённую CLI-команду сразу описывать в
  [console.md](docs/laiot/console.md): параметры, умолчания, пример.
- Конфигурация — `.env` + Config со схемой; не добавлять define/getenv в
  модули. Текущие параметры — [configuration.md](docs/laiot/configuration.md).
- Пользовательские обёртки нового ядра — `app/Support/helpers`, справочник
  [functions.md](docs/laiot/functions.md). Значения менять через
  PropertyValueService, сохраняя историю и цепочку событий.
- CLI — `php bin/laiot`, классы `app/Console/Commands`, регистрация `bin/laiot`.
  Старые `php majordomo`, `lib/core/Console`, alpha `deploy.sh` и весь
  alpha-runtime удалены; обновление ядра ещё проектируется. Не создавать
  самостоятельные CLI-скрипты для отдельных операций.
- Комментарии по-русски, с объяснением причины. При изменении класса в
  заголовке добавлять `ГГГГ-ММ-ДД — что изменилось`; правило оформления —
  [code-comments.md](docs/laiot/code-comments.md).
- Не возвращать старые URL, legacy-таблицы, адаптеры и удалённые шаблоны.
  Alpha остаётся отдельной веткой; импорт данных — отдельная операция.
- Совместимость с alpha не цель: у старой функции берём смысл, а не
  сигнатуру и флаги. Внутренний API проектируем явно; правило —
  [docs/laiot/AGENTS.md](docs/laiot/AGENTS.md).
- Docker — только локальная отладка и просмотр текущего состояния. Стенд
  описан в [docker.md](docs/laiot/docker.md) и не является production-
  инструкцией. Локальный запуск без Docker —
  `php -S 127.0.0.1:8080 -t public tools/dev-server.php`.
- Тесты — изолированно, без рабочего .env/БД и внешних сервисов. Для кода
  `composer check`, для документации `php tests/docs_quality_smoke.php`;
  остальные проверки по [testing.md](docs/laiot/testing.md).

Рабочие файлы могут сразу отправляться на сервер через PhpStorm:
вносить только законченные изменения, не экспериментировать записями.
Не менять production .env/секреты и не запускать команды против рабочих
сервисов без явного разрешения. Детали статуса и известных ограничений —
[docs/laiot/limitations.md](docs/laiot/limitations.md).
