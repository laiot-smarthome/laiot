---
title: MyDoMo — функции, обёртки и CLI
audience: user, agent
category: core
tags: mydomo, функции, обёртки, cli, cache, queue, модули
description: Публичные API нового ядра MyDoMo, правила использования и статус пользовательских обёрток.
order: 3
---

# Функции, обёртки и CLI MyDoMo

## 1. Правило

В новом коде используются **только** сервисы и обёртки из этого документа.
`$db`, `SQLSelect*`, `mysqli_*`, `PDO`, `eval`, shell и прямые файловые пути
ядра запрещены. Старый API `alpha` не является контрактом MyDoMo и не
подключается к новому коду.

## 2. Application

```php
$app = require __DIR__ . '/bootstrap/app.php'; // уже booted

$app->config('version', 'dev');
$app->clock();      // CarbonImmutable, таймзона из APP_TIMEZONE
$app->router();
$app->logger();
$app->database();
$app->cache();
$app->queue();
$app->jobs();
$app->modules();
```

`Application::handle(Request $request): Response` — HTTP-граница для тестов
и CLI-сценариев. В вебе её вызывает `public/index.php`.

## 3. Кэш

```php
$cache = $app->cache();

$cache->put('settings.main', ['theme' => 'dark'], 300);
$value = $cache->get('settings.main', null);
$value = $cache->remember('settings.main', 300, fn () => loadSettings());
$cache->forget('settings.main');
$cache->has('settings.main');
$cache->clear(); // только namespace mydomo., Redis — через SCAN
```

- ключ: `[A-Za-z0-9_.-]`, до 180 символов;
- TTL: секунды, `DateInterval` или `null`;
- драйверы: `array` (тесты), `file`, `database`, `redis`;
- Redis настраивается `CACHE_DRIVER=redis` + `REDIS_*`.

## 4. Очередь

```php
$queue->push('default', 'demo.email', ['to' => 'a@b.c'], 0);
$queue->pushJob(new SendEmailJob('a@b.c', 'hello'));

$job = $queue->pop('default');
if ($job !== null) {
    $queue->ack($job);        // успех
    $queue->fail($job, $e);   // retry/dead-letter
}

$queue->stats('default');   // pending/reserved/failed
$queue->failed('default');  // список
$queue->pending('default'); // ожидают worker
$queue->retry($id);
$queue->forget($id);
```

`Job` — типизированное задание:

```php
final class SendEmailJob implements MajorDoMo\Queue\Job
{
    public function name(): string { return 'demo.email'; }
    public function payload(): array { return ['to' => $this->to]; }
}
```

Worker: `php bin/mydomo queue:work --once`.

## 5. ModuleDb

```php
final class NotesRepository
{
    public function __construct(private MajorDoMo\Modules\ModuleDb $db) {}

    public function all(): array
    {
        return $this->db->table('notes')->orderByDesc('ID')->get()->all();
    }
}
```

- логическое имя `notes` → физическое `module_<id>_notes`;
- allowlist задаётся в `module.json` → `storage.tables`;
- нет `raw()`, `join()`, `whereRaw()`, `selectRaw()`;
- драйверы `mysql`/`sqlite`; SQLite-файл в `data/`.

## 6. ModuleBus

```php
$bus->publish('router.42.online', ['online' => true], 300);
$event = $bus->latest('module.keenetic.router.42.online');
$bus->subscribe('module.keenetic.router.*', $listener);
```

- писать только в `module.<id>.*`;
- `latest` возвращает последнее событие до истечения TTL;
- подписки — в текущем процессе; фоновая доставка будет через очередь.

## 7. Права

```php
$auth->can('panel.view');
$auth->isAdmin();
```

Middleware: `->middleware('can:panel.view')`.

Роли: `roles`, `permissions`, `role_permissions`, `user_roles`; миграция
создаёт `admin`/`user` и переносит администраторов. Если БД/миграции ещё
недоступны, используется сессионный `is_admin`.

## 8. API v1

```text
GET /api/v1/health   — БД и кэш, 200/503
GET /api/v1/about    — версия, PHP, число core-модулей
GET /api/v1/modules  — список core-модулей
GET /api/v1/me       — Bearer/X-API-Key, user_id + roles/permissions
GET /api/v1/panel/search?q=<текст>&limit=<1..50> — поиск для админки
GET /api/v1/panel/notifications?module=&offset= — непрочитанные и summary
POST /api/v1/panel/notifications/dismiss        — прочитать (нужен CSRF)
```

`/api/v1/*` обслуживает новое ядро (`public/index.php`); legacy `/api/*`
остаётся на `public/api.php` — это разные контракты. Nginx и
`dev_server.php` различают префиксы.

`panel/search` требует сессию администратора (без API-токена) и отдаёт
конверт для `window.MDJAdminApi`:

```json
{
  "ok": true,
  "data": {
    "query": "Датчик",
    "total": 3,
    "counts": {"Классы": 1, "Свойства": 1, "Методы": 1},
    "sections": {
      "Классы": [
        {"type": "class", "title": "Датчик", "url": "/module/classes/1/edit", "description": "", "meta": ["Класс"], "action": ""}
      ]
    },
    "message": ""
  }
}
```

Ищутся объекты, классы, свойства, методы, скрипты, пользователи, локации и
core-модули. Запрос короче 3 символов возвращает пустой результат; `limit`
ограничивает число элементов в каждом разделе, а не общий итог. Результаты
выводятся в шапке (`MDJAdminApi.search`) и в полном drawer
`#mdmGlobalSearchDrawer` из `layouts.alpha`.

Уведомления: `GET /api/v1/panel/notifications` отдаёт `total`, `counts`,
`levels`, `severity` и `items` (до 50); `POST .../dismiss` принимает `{id}` или
`{module}` и требует `X-CSRF-Token`. Устройство, типы, лимиты и `notify()`
описаны в [notifications.md](notifications.md).

Токены: `api_tokens`, в БД только SHA-256. Выпуск:

```bash
php bin/mydomo api:token 1 "monitoring"
```

Токен показывается один раз. `Authorization: Bearer <token>` или
`X-API-Key: <token>`.

`/admin` (auth) — обзор веб-админки на Blade-компонентах и alpha-layout;
в шапке работают поиск (`Ctrl K`), колокольчик уведомлений и полный drawer.
`/module/users` — пользователи; роли назначаются в форме пользователя.
`/admin/tokens` (auth + `can:users.manage`) — выпуск/отзыв API-токенов.
`/admin/modules` (auth) — реестр core-модулей: версии, таблицы, helpers.
`/admin/api` (auth) — живой список API-маршрутов.
`/module/api` (auth) — входящие webhook: секрет, методы, сценарий/PHP,
последний статус. Публичный вызов — `GET|POST|PUT|PATCH|DELETE
/api/v1/webhooks/{secret}`; секрет 48 hex, хранится только SHA-256, при
создании/ротации показывается один раз. Ошибки: 404/405 (`Allow`)/403/500.
`/api/v1/panel/linked-object` (auth) — `?q=` поиск объектов, `?object=`
свойства/методы класса для `x-md.linked-object`; старый модуль удалён.
`/module/events` — журнал цепочки событий свойства (`property_events`).
`/module/actions_log` — аудит: вход/выход и изменения пользователей пишутся
в `action_log`.
`/admin/profile` (auth) — имя и смена пароля текущего пользователя.
`/admin/maintenance` (auth + `can:settings.manage`) — режим обслуживания;
`/health` и `/login` остаются доступны.
Страница `/status` (auth) показывает версию, PHP, БД/кэш и число модулей
в alpha-layout. `/roles` (auth + `can:users.manage`) — роли и permissions.

Ошибки — JSON с `error.code` и `request_id`. Пока это публичный срез без
токенов; auth API — следующий этап.

## 9. CLI

```bash
php bin/mydomo about
php bin/mydomo doctor
php bin/mydomo maintenance status
php bin/mydomo maintenance on
php bin/mydomo maintenance off
php bin/mydomo timeout:run
php bin/mydomo health
php bin/mydomo route:list
php bin/mydomo module:list
php bin/mydomo module:install example_sdk
php bin/mydomo module:disable example_sdk
php bin/mydomo module:enable example_sdk
php bin/mydomo module:remove example_sdk
php bin/mydomo module:validate
php bin/mydomo worker <module> --once
php bin/mydomo core-worker queue --once
php bin/mydomo core-worker timeouts --once
php bin/mydomo-agent serve
php bin/mydomo-agent install
php bin/mydomo cache:clear
php bin/mydomo queue:stats --queue=default
php bin/mydomo queue:work --once
php bin/mydomo queue:failed
php bin/mydomo queue:retry <id>
php bin/mydomo db:migrate
php bin/mydomo db:status
php bin/mydomo api:token 1 "monitoring"
```

`health` возвращает 0 только если БД и кэш доступны.

## 10. Пользовательские обёртки и helpers

### Автоподключение

- core: `src/Support/helpers/*.php` — загружает `HelperLoader` при boot;
- модуль: `module.json` → `"helpers": "helpers.php"` (или список) —
  загружает `ModuleManager` при обнаружении модуля;
- если `helpers` не указан, ищется `helpers.php` в корне модуля;
- ручных `require` в bootstrap нет.

Модульный helper — тонкий фасад над сервисом модуля:

```php
function kinetic_change_connection(string $connection): bool
{
    return app()
        ->modules()
        ->service('kinetic', KineticService::class)
        ->changeConnection($connection);
}
```

`ModuleManager::service()` проверяет, что класс принадлежит namespace модуля,
и не даёт helper-функции создать произвольный core-сервис. Имя helper
начинается с id модуля, чтобы не было коллизий.

`shoutbox_latest()` — рабочий пример: helper → `ShoutboxService::latest()`.

### `say()`

`say()` уже работает в MyDoMo:

```php
say('Дверь открыта', 0, 0, 'script');
```

- пишет в `module_shoutbox_messages` (core-модуль Shoutbox);
- сообщение обрезается до 255 символов;
- `level` пока игнорируется;
- при отсутствии таблицы/БД пишет warning в лог, не роняет сценарий.

Уже работают в MyDoMo:

```php
gg('Кухня.temperature');       // значение свойства (string|null)
sg('Кухня.temperature', 23.5); // true/false
getGlobal('Кухня.temperature'); // alpha-имя для gg()
setGlobal('Кухня.temperature', 23.5); // alpha-имя для sg()
getObject('Кухня');            // array|null
```

`gg`/`sg` работают только с существующими объектом и свойством; значения
хранятся в `property_values`. Свойство ищется в классе объекта, затем среди
глобальных (`class_id = 0`): одноимённые свойства разных классов не
перепутываются. `sg()` дополнительно принимает `$noLinked` (совместимость
сигнатуры alpha, в MyDoMo не используется) и `$source`:

```php
sg('Кухня.temperature', 23.5, 0, 'zigbee');
```

Запись — это цепочка: значение, история (если включена), строка
`property_events` и синхронный вызов OnChange-метода свойства
(`properties.on_change`). Если значение не изменилось, история и
`property.changed` не создаются, но обработчик всё равно вызывается.
Повтор свойства в цепочке останавливает обработчик, значение при этом
сохраняется. Подробно — [events.md](events.md); журнал — `/module/events`.

`getHistory('Кухня.temperature', 50)` возвращает последние значения из
`property_history`. Пишутся только реальные изменения и только у свойств с
включённым `keep_history`.

`callMethod('Кухня', 'double', ['x' => 5])` работает: находит объект, класс,
метод и выполняет доверенный пользовательский PHP через временный файл
(без eval). Поддерживается и alpha-форма с точкой:

```php
callMethod('Кухня', 'double', ['x' => 5]);
callMethod('Кухня.double', ['x' => 5]); // alpha-форма
cm('Кухня.double', ['x' => 5]);         // короткий алиас
runMethod('Кухня.double', ['x' => 5]);  // алиас
callMethodSafe('Кухня.double', ['x' => 5]); // в MyDoMo синхронный, как callMethod
```

Если объект или метод не найдены, выбрасывается `InvalidArgumentException`.

`runScript($id, $params)` запускает сценарий по ID **или названию**. Код
сценария — доверенный PHP без тегов, выполняется через `CodeRunner` (без
eval); `$params` доступен внутри кода. Алиасы — `callScript()`,
`runScriptSafe()`, `rs()`. В alpha `runScriptSafe` уходил в очередь, а
`callScript` — в сценарий; в MyDoMo все три вызова синхронные и одинаковые,
очередь для пользовательского кода пока не используется.

```php
$result = runScript(5, ['x' => 2]);
$result = runScript('Утренний сценарий', ['x' => 2]);
```

`setTimeOut($title, $code, $seconds)` создаёт запись в `runtime_timeouts`;
`clearTimeOut($title)` снимает её. Выполнение — только явно:
`php bin/mydomo timeout:run` (без автоматических циклов).

`notify($message, $type = 'info', $options = [])` оставляет системное
уведомление в колокольчике админки (модуль `system`). Возвращает
`['status' => bool, 'id' => int]` или `['status' => false, 'error' => '...']`.
Типы — `info`, `success`, `warning`, `danger`; в `$options` можно передать
`key`, `title`, `details`, `url` (только `/...`), `label`. Повтор с тем же
`key` среди непрочитанных не создаёт дубль. Подробно —
[notifications.md](notifications.md).

`/module/events` — журнал цепочки: тип шага, объект и свойство, старое и новое
значение, обработчик, статус (`changed`, `ok`, `error`, `missing`,
`stopped`), глубина и цепочка. Фильтры — по типу и статусу, поиск — по
объекту, свойству, обработчику и значению.

## 11. Для агента

- Сначала проверь, есть ли сервис в этом файле; не добавляй новый глобальный
  хелпер без записи в документ.
- Новый публичный метод — короткое описание, сигнатура, возврат, пример.
- Ошибки — по `docs/agents/errors.md`; комментарии — по-русски.
- После изменения API обнови этот файл и `architecture.md` в том же коммите.
