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
ядра запрещены. Старые функции `alpha` доступны только legacy-модулям до их
переноса.

## 2. Application

```php
$app = require __DIR__ . '/bootstrap/app.php'; // уже booted

$app->config('version', 'dev');
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
Пока роль в сессии (`is_admin`); таблица ролей — следующий этап.

## 8. CLI

```bash
php bin/mydomo about
php bin/mydomo health
php bin/mydomo route:list
php bin/mydomo module:list
php bin/mydomo cache:clear
php bin/mydomo queue:stats --queue=default
php bin/mydomo queue:work --once
php bin/mydomo queue:failed
php bin/mydomo queue:retry <id>
php bin/mydomo db:migrate
php bin/mydomo db:status
```

`health` возвращает 0 только если БД и кэш доступны.

## 9. Пользовательские обёртки

В `alpha` сценарии и методы используют `gg`, `sg`, `say`, `setTimeOut`,
`callMethod`, `getObject`, `getHistory`. В MyDoMo эти имена **пока не
перенесены**; новый код должен использовать сервисы, а перенос обёрток
делается отдельным этапом.

Планируемый контракт (ещё не реализован):

| Обёртка | Смысл | Возврат |
| --- | --- | --- |
| `gg($name, $default = null)` | публичное состояние/свойство | mixed |
| `sg($name, $value)` | записать публичное состояние | bool/результат сервиса |
| `say($message)` | сообщение в журнал | void |
| `setTimeOut($name, $delay, $method)` | таймер | id/результат |
| `clearTimeOut($id)` | снять таймер | bool |
| `callMethod($object, $method, $params = [])` | вызвать метод | mixed |
| `getObject($name)` | объект по имени | object/array |
| `getHistory($object, $property, $limit)` | история свойства | array |

До реализации этих обёрток **не обещайте** их в новом API. После реализации
таблица переедет из «планируется» в «работает», с сигнатурами и примерами.

## 10. Для агента

- Сначала проверь, есть ли сервис в этом файле; не добавляй новый глобальный
  хелпер без записи в документ.
- Новый публичный метод — короткое описание, сигнатура, возврат, пример.
- Ошибки — по `docs/agents/errors.md`; комментарии — по-русски.
- После изменения API обнови этот файл и `architecture.md` в том же коммите.
