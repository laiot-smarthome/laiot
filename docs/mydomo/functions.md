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
| `sg(string $name, $value, string $source = 'sg')` | bool, запись через цепочку свойства |
| `setGlobal(string $name, $value, string $source = '')` | Алиас sg; пустой source становится `setGlobal` |
| `getHistory(string $name, int $limit = 50)` | Массив последних `value/created_at` |
| `history(string $name)` | Построитель выборки истории или null |
| `getHistoryRange(string $name, $from = null, $to = null, int $limit = 500)` | Записи за период |
| `getHistoryAvg/Min/Max/Sum(string $name, $from = null, $to = null)` | Числовой агрегат или null |
| `getHistoryCount(string $name, $from = null, $to = null)` | Число записей |

Имя `Объект.Свойство` разбивается по последней точке. Объект должен
существовать; системные имена класса, объекта, свойства и метода — латиница,
цифры и `_` (`src/Support/Identifier`). Свойство ищется среди объектных
свойств объекта, затем в его классе и по цепочке наследования. Дубликаты
имён в ветке не создаются; для legacy-строк разрешение идёт по ближайшему
классу. Автосоздания объектов/свойств нет. Переименование объекта, свойства
или метода обновляет ссылки в сценариях и методах; подробности —
[admin-ui.md](admin-ui.md).

```php
sg('KitchenLight.temperature', 23.5, 'mqtt');
$value = gg('KitchenLight.temperature');
$history = getHistory('KitchenLight.temperature', 20);
$avg = history('KitchenLight.temperature')->from('-1 day')->avg();
$week = getHistoryRange('KitchenLight.temperature', '-7 days', 'now', 200);
```

Значение нормализуется: null → пустая строка, bool → `1`/`0`, скаляр →
строка, массив → JSON. Неподдерживаемый объект или ненайденное свойство
дают false при записи. Ошибки инфраструктуры не превращаются автоматически
в false.

`sg()` асинхронен: `true` означает «значение принято и, если оно новое,
событие поставлено в очередь», а не «метод уже выполнился». При включённом у
свойства `only_new_values` повтор того же значения тоже возвращает `true`, но
цепочку не запускает. Журнал и история появятся после обработки задания
`core-worker events`. Значение `gg()` доступно сразу.

Своего эха модуль не получает: пока он обрабатывает изменение, его записи
не возвращаются ему как новое событие; другие подписчики и OnChange
получают их как обычно. Отдельного параметра управления доставкой нет —
alpha-совместимые `$noLinked` в новое API не переносятся. Запись значения
без истории, доставки и OnChange — только низкоуровневый `ValueRepository`
для импорта, а не пользовательский путь. Правила доставки —
[events.md](events.md).

Если значение не изменилось, история не растёт, но OnChange может быть
вызван снова. История хранится только при `keep_history`.

## История и выборки

`history($name)` возвращает построитель `HistoryQuery`: период, лимит,
значения и агрегаты. Период принимает дату, `DateTimeInterface` или
относительную строку (`-1 day`, `today`). Агрегаты считает один SQL по
индексу `(object_id, property_id, created_at)`, а не PHP по всем строкам.

```php
use MajorDoMo\History\HistoryQuery;

$day = history('KitchenLight.temperature')->from('-24 hours');
$stats = $day->stats();     // count, min, max, avg, sum, last
$top = history('KitchenLight.temperature')->last(10)->oldestFirst()->values();
```

Значения в истории — строки. В `min/max/avg/sum` входят только непустые
значения, приведённые к числу; `count()` считает все записи. Для строковых
свойств используйте `count()`, `values()` и `lastValue()`, а не числовые
агрегаты. Тот же построитель доступен в методе как `$this->history()`.

Сервис для ядровых форм:

```php
use MajorDoMo\Properties\PropertyValueService;

$values = $app->container()->make(PropertyValueService::class);
$ok = $values->setValue($objectId, $propertyId, '24', 'panel');
```

`setValue` отвергает чужой класс/объект, перекрытое свойство и
отсутствующую/удалённую сущность, не создавая значение или событие.
Обновление значения сохраняет его created_at. Подробный контракт
транзакции, журнала и циклов — [events.md](events.md).

## Методы объектов

Поддерживаются две формы одного синхронного вызова:

```php
$result = callMethod('KitchenLight', 'double', ['x' => 5]);
$result = callMethod('KitchenLight.double', ['x' => 5]);
$result = cm('KitchenLight.double', ['x' => 5]);
```

| Функция | Параметры |
| --- | --- |
| `callMethod(string $object, mixed $method = null, array $params = [])` | Раздельные имена либо составное имя и массив |
| `callMethodSafe(string $object, mixed $method = null, array $params = [])` | Сейчас тот же callMethod |
| `cm(string $method, mixed $params = 0)` | Составное имя; не-массив params становится пустым |
| `runMethod(string $method, mixed $params = 0)` | Алиас cm |

Метод ищется среди объектных методов объекта, затем в его классе и по
цепочке наследования. Дубликаты имён не создаются; для legacy-строк
разрешение идёт по ближайшему классу. В коде доступен `$params`;
возвращается результат include/явного return, для пустого кода — null.

Несуществующий объект/метод вызывает InvalidArgumentException.
Некорректное составное имя без точки возвращает null на уровне helper.
Суффикс Safe не означает sandbox, очередь или перехват исключения.

### `$this` в методе

Тело метода выполняется с контекстом объекта `MethodContext`, поэтому
`$this` — не пустой указатель, а короткий доступ к объекту:

```php
$this->set('state', $this->param('x'));
$average = $this->history('temperature')->from('-1 day')->avg();

return [$this->title(), $this->get('state'), $average];
```

| Метод контекста | Назначение |
| --- | --- |
| `title()`, `id()`, `method()` | Название/ID объекта и имя метода |
| `params()`, `param($name, $default = null)` | Параметры вызова |
| `get($property)` | Значение свойства этого объекта |
| `set($property, $value, $source = 'method')` | Запись через общий конвейер значения и событий |
| `history($property)` | `HistoryQuery` этого свойства |
| `call($method, $params = [])` | Вызвать другой метод того же объекта |
| `notify($message, $type = 'info', $options = [])` | Уведомление в админке |

Имена свойств и методов — короткие, без склейки `Объект.Свойство`. Старый
`$params` остаётся доступен: контекст его не заменяет.

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
через временный файл с `$params`, без eval. Если у сценария выключено
«Подключать ядро», код выполняется отдельным php-процессом без bootstrap:
функции ядра недоступны, а `runScript()` возвращает строковое представление
результата (`var_export` для скаляров, JSON для массива). Пустой код
возвращает null, отсутствующий сценарий — InvalidArgumentException. Ошибки
самого кода передаются вызывающему. Safe не обещает фонового исполнения.

## Таймеры

`setTimeOut(string $title, string $commands, int $timeout = 0, int $expire = 0): int`
создаёт запись; `clearTimeOut(string $title): bool` снимает ожидающие записи
с этим именем. Отрицательная задержка становится нулевой; имя при записи
обрезается/trim, поэтому caller должен использовать одно нормализованное
имя для записи и очистки. Повторное имя не заменяет старый таймер.
`$expire` не используется.

Выполнение: разовый `timeout:run` либо отдельный `core-worker timeouts`.
Ограничения повторов и конкуренции — [queue.md](queue.md).

## Комнаты

Состояние комнаты читается из свойств автоматического объекта комнаты,
которые система обновляет при изменениях свойств с ролью. Название можно
передавать как название локации («Кухня») или объекта комнаты («Kukhnya»).

| Функция | Возврат и назначение |
| --- | --- |
| `roomState(string $room)` | `active/light/heating/condition/timeout/last_activity_at` или null |
| `roomIsActive(string $room)` | bool: активность не истекла по `activityTimeout` |
| `roomIsLight(string $room)` | bool: есть активный источник со ролью «Свет» |
| `roomIsHeating(string $room)` | bool: есть активный источник со ролью «Обогрев» |
| `roomIsCondition(string $room)` | bool: есть активный источник со ролью «Кондиционер» |

```php
if (roomIsActive('Кухня') && !roomIsLight('Кухня')) {
    notify('В кухне кто-то есть, но свет выключен', 'warning', ['key' => 'room:kitchen']);
}
```

Хелперы не запускают пересчёт: значения уже записаны сервисом активности.
Если комната или приложение не найдены, `roomState()` возвращает null, а
проверки — false. Роли свойств, таймер и ограничения описаны в
[locations.md](locations.md).

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

## MQTT

Helpers модуля MQTT доступны, пока модуль включён, и работают через его
сервисы.

| Функция | Возврат и назначение |
| --- | --- |
| `mqtt_publish(string $topic, mixed $value, int $qos = 0, bool $retain = false)` | bool; отправить команду. Ошибка брокера возвращает false и попадает в RuntimeState |
| `mqtt_value(string $path)` | Последнее известное значение топика или null |
| `mqtt_connected()` | bool; соединение приёма активно и worker запущен |

Устройство и ограничения — [mqtt.md](mqtt.md).

## Helpers модулей и отсутствие контекста

HelperLoader загружает core-файлы и helpers включённых SDK-модулей.
Публичная функция модуля получает префикс его ID и делегирует сервису.
Пример механизма — [modules.md](modules.md), структура пакета — [sdk.md](sdk.md).

Глобальные обёртки получают Application через AppContext. Без загруженного
приложения чтение объекта/значения/метода/сценария возвращает null,
история — пустой массив, запись/очистка — false, setTimeOut — 0,
notify — ошибку `status=false`. Эти результаты не означают, что функция
самостоятельно загрузила или проверила рабочее окружение.
