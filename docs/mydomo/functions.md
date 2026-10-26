---
title: MyDoMo — функции и пользовательские обёртки
audience: user, agent
category: core
tags: mydomo, функции, обёртки, сценарии, свойства, сервисы
description: Реальные сигнатуры пользовательских функций, возвращаемые значения, ошибки и доступ к сервисам нового ядра.
order: 12
---

# Функции MyDoMo

Здесь описаны существующие обёртки `src/Support/helpers`, а не полный
справочник функций alpha. CLI вынесен в [console.md](console.md), HTTP —
в [api.md](api.md), SDK — в [sdk.md](sdk.md).

Прямой SQL, shell, подключение старых loader и обход доменных сервисов
запрещены правилами нового кода. Технической PHP-песочницы и CodeGuard
пока нет; доверенность пользовательского кода остаётся существенным
ограничением, см. [limitations.md](limitations.md).

## Приложение и сервисы

В готовом запросе приложение уже загружено. В самостоятельном коде
bootstrap запускают один раз, только для нужной установки:

```php
$app = require __DIR__ . '/bootstrap/app.php';
$container = $app->container();
$clock = $app->clock();
$cache = $app->cache();
$queue = $app->queue();
$jobs = $app->jobs();
$modules = $app->modules();
```

Дополнительно доступны `root()`, `config($key, $default)`, `database()`,
`router()`, `logger()`, `session()`, `yoyo()`. `config()` принимает точечный
путь, например `queue.max_attempts`; это конфигурация Application, а не
прямое чтение произвольной переменной `.env`.

`handle(Request): Response` — HTTP-граница. Для тестов Application собирают
с отдельным массивом, ArraySession и SQLite, без рабочего bootstrap.
Контроллер/сервис получает зависимости через конструктор; новые глобалы
не нужны для доступа к репозиторию.

## Свойства

| Функция | Возврат и назначение |
| --- | --- |
| `getObject(string $name)` | Массив `id/title/class_id/location_id/description` или null |
| `gg(string $name)` | Сохранённая строка значения или null |
| `getGlobal(string $name)` | Алиас gg |
| `sg(string $name, $value, $noLinked = 0, string $source = 'sg')` | bool, запись через цепочку свойства |
| `setGlobal(string $name, $value, $noLinked = 0, string $source = '')` | Алиас sg; пустой source становится `setGlobal` |
| `getHistory(string $name, int $limit = 50)` | Массив последних `value/created_at` |

Имя `Объект.Свойство` разбивается по последней точке. Объект должен
существовать. Свойство ищется в его классе, затем среди глобальных
`class_id = 0`. Автосоздания объектов/свойств нет.

```php
sg('Кухня.temperature', 23.5, 0, 'mqtt');
$value = gg('Кухня.temperature');
$history = getHistory('Кухня.temperature', 20);
```

Значение нормализуется: null → пустая строка, bool → `1`/`0`, скаляр →
строка, массив → JSON. Неподдерживаемый объект или ненайденное свойство
дают false при записи. Ошибки инфраструктуры не превращаются автоматически
в false. `$noLinked` не отключает новый OnChange: параметр сохранён в
сигнатуре, а старых LINKED_MODULES здесь нет.

Если значение не изменилось, история не растёт, но OnChange может быть
вызван снова. История хранится только при `keep_history`; лимит чтения
нормализуется до 1–500. Функции агрегатов истории из старых планов пока
не реализованы этими helpers.

Сервис для ядровых форм:

```php
use MajorDoMo\Properties\PropertyValueService;

$values = $app->container()->make(PropertyValueService::class);
$ok = $values->setValue($objectId, $propertyId, '24', 'panel');
```

`setValue` отвергает чужой класс и отсутствующую/удалённую сущность, не
создавая значение или событие. Обновление значения сохраняет его created_at.
Подробный контракт транзакции, журнала и циклов — [events.md](events.md).

## Методы объектов

Поддерживаются две формы одного синхронного вызова:

```php
$result = callMethod('Кухня', 'double', ['x' => 5]);
$result = callMethod('Кухня.double', ['x' => 5]);
$result = cm('Кухня.double', ['x' => 5]);
```

| Функция | Параметры |
| --- | --- |
| `callMethod(string $object, mixed $method = null, array $params = [])` | Раздельные имена либо составное имя и массив |
| `callMethodSafe(string $object, mixed $method = null, array $params = [])` | Сейчас тот же callMethod |
| `cm(string $method, mixed $params = 0)` | Составное имя; не-массив params становится пустым |
| `runMethod(string $method, mixed $params = 0)` | Алиас cm |

Метод ищется в классе выбранного объекта по названию. Наследование классов
не добавляется этим поиском. В коде доступен `$params`; возвращается
результат include/явного return, для пустого кода — null.

Несуществующий объект/метод вызывает InvalidArgumentException.
Некорректное составное имя без точки возвращает null на уровне helper.
Суффикс Safe не означает sandbox, очередь или перехват исключения.
`$this` внутри метода не привязан к объекту MajorDoMo в текущем MethodRunner.

Пример тела метода без PHP-тегов:

```php
return ($params['x'] ?? 0) * 2;
```

## Сценарии

```php
$result = runScript(5, ['x' => 2]);
$result = runScript('Утренний сценарий', ['x' => 2]);
```

`runScript(int|string $id, mixed $params = [])` принимает ID или название.
Числовая строка трактуется как ID. `callScript`, `runScriptSafe`, `rs` —
синхронные алиасы; не-массив params становится пустым массивом.

ScriptRunner ищет существующий сценарий, CodeRunner исполняет его PHP
через временный файл с `$params`, без eval. Пустой код возвращает null,
отсутствующий сценарий — InvalidArgumentException. Ошибки самого кода
передаются вызывающему. Safe не обещает фонового исполнения.

## Таймеры

`setTimeOut(string $title, string $commands, int $timeout = 0, int $expire = 0): int`
создаёт запись; `clearTimeOut(string $title): bool` снимает ожидающие записи
с этим именем. Отрицательная задержка становится нулевой; имя при записи
обрезается/trim, поэтому caller должен использовать одно нормализованное
имя для записи и очистки. Повторное имя не заменяет старый таймер.
`$expire` не используется.

Выполнение: разовый `timeout:run` либо отдельный `core-worker timeouts`.
Ограничения повторов и конкуренции — [queue.md](queue.md).

## Уведомления и say

`notify(string $message, string $type = 'info', array $options = []): array`
делегирует в NotificationService от источника `system`. Возвращает
`status`, при успехе/дубликате — ID, при ошибке — описание. В options
доступны `key`, `title`, `details`, `url`, `label`.

```php
$result = notify('Датчик не отвечает', 'warning', [
    'key' => 'sensor:kitchen',
    'url' => '/objects',
]);
```

`say($ph, $level = 0, $member_id = 0, $source = ''): void` теперь через
AppContext отправляет информационное уведомление. Пустой source становится
`say`; источник должен подходить правилам NotificationService. `level` и
`member_id` не включают речь, громкость или выбор терминала. Результат
сервиса не возвращается. Это изменение смысла относительно alpha; Shoutbox
и `shoutbox_latest` больше не часть нового приложения.

Лимит, дедупликация и прочтение — [notifications.md](notifications.md).

## Helpers модулей и отсутствие контекста

HelperLoader загружает core-файлы и helpers включённых SDK-модулей.
Публичная функция модуля получает префикс его ID и делегирует сервису.
Пример механизма — [modules.md](modules.md), структура пакета — [sdk.md](sdk.md).

Глобальные обёртки получают Application через AppContext. Без загруженного
приложения чтение объекта/значения/метода/сценария возвращает null,
история — пустой массив, запись/очистка — false, setTimeOut — 0,
notify — ошибку `status=false`. Эти результаты не означают, что функция
самостоятельно загрузила или проверила рабочее окружение.
