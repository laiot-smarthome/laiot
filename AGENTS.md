# AGENTS.md

Обязательная точка входа для работы с репозиторием. Активная ветка —
**MyDoMo**, отдельное приложение без обратной совместимости с alpha.

Перед изменениями читать [docs/mydomo/README.md](docs/mydomo/README.md),
[правила MyDoMo](docs/mydomo/AGENTS.md),
[архитектуру](docs/mydomo/architecture.md) и
[мастер-план](docs/todo/mydomo.md).

Коротко, что нельзя нарушать:

- Рабочая платформа — Linux/Ubuntu. Windows — локальная разработка и
  доступные проверки; обходы её ограничений в рабочий код не добавлять.
  Процессы, pcntl и systemd проверяются в Linux.
- PHP MyDoMo — 8.2+, Illuminate 12 components. Это не полный Laravel;
  фактические версии/возможности проверять по файлам текущей задачи.
- Ядровые страницы объектов, пользователей, журналов, API и настроек живут
  в `src/Http/Controllers/Core`, `src/Api`, `resources/views/pages`.
  Они не являются модулями, не получают module.json и ModuleDb.
- Интеграции MQTT/Zigbee и сторонние расширения остаются SDK-модулями в
  `modules/core/<id>` или `modules/<id>`; их код не растворяется в ядре.
  Правила — [modules.md](docs/mydomo/modules.md) и [sdk.md](docs/mydomo/sdk.md).
- Сохраняется дизайн MDJUI/alpha, а не его старый PHP/JS runtime.
  Перед UI-изменениями читать [admin-ui.md](docs/mydomo/admin-ui.md) и живую
  страницу `/ui-kit`; реализация — Blade, Alpine, `x-md.*` и `md-*`,
  без JTemplate, jQuery и старой панели.
- Интерактив внутри страницы — Yoyo-компонент (`src/Yoyo`, действие через
  `/yoyo`), а не новый JSON-эндпоинт. Имя `kebab-case` само разрешается в
  класс `MajorDoMo\Yoyo\StudlyCase`; ручная регистрация не нужна.
  `/api/v1/panel/*` — только виджеты оболочки (поиск в шапке, уведомления)
  и внешний API.
- Статика — `public/assets`, исходники — `resources/js` и `resources/css`;
  сборка `npm run build`. Библиотеки локально через npm, без CDN и новых
  script/link на библиотеки в шаблоне.
- Конфигурация — `.env` + Config со схемой; не добавлять define/getenv в
  модули. Текущие параметры — [configuration.md](docs/mydomo/configuration.md).
- Пользовательские обёртки нового ядра — `src/Support/helpers`, справочник
  [functions.md](docs/mydomo/functions.md). Значения менять через
  PropertyValueService, сохраняя историю и цепочку событий.
- CLI — `php bin/mydomo`, классы `src/Console`, регистрация `bin/mydomo`.
  Старые `php majordomo`, `lib/core/Console`, alpha `deploy.sh` и весь
  alpha-runtime удалены; обновление ядра ещё проектируется. Не создавать
  самостоятельные CLI-скрипты для отдельных операций.
- Комментарии по-русски, с объяснением причины. При изменении класса в
  заголовке добавлять `ГГГГ-ММ-ДД — что изменилось`; правило оформления —
  [code-comments.md](docs/mydomo/code-comments.md).
- Не возвращать старые URL, legacy-таблицы, адаптеры и удалённые шаблоны.
  Alpha остаётся отдельной веткой; импорт данных — отдельная операция.
- Совместимость с alpha не цель: у старой функции берём смысл, а не
  сигнатуру и флаги. Внутренний API проектируем явно; правило —
  [docs/mydomo/AGENTS.md](docs/mydomo/AGENTS.md).
- Docker — только локальная отладка и просмотр текущего состояния. Стенд
  описан в [docker.md](docs/mydomo/docker.md) и не является production-
  инструкцией. Локальный запуск без Docker —
  `php -S 127.0.0.1:8080 -t public tools/dev-server.php`.
- Тесты — изолированно, без рабочего .env/БД и внешних сервисов. Для кода
  `composer check`, для документации `php tests/docs_quality_smoke.php`;
  остальные проверки по [testing.md](docs/mydomo/testing.md).

Рабочие файлы могут сразу отправляться на сервер через PhpStorm:
вносить только законченные изменения, не экспериментировать записями.
Не менять production .env/секреты и не запускать команды против рабочих
сервисов без явного разрешения. Детали статуса и известных ограничений —
[docs/mydomo/limitations.md](docs/mydomo/limitations.md).
