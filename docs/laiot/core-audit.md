---
title: LaIOT — аудит реализованного ядра
audience: agent, user
category: core
tags: laiot, аудит, свойства, тесты
description: Исправления значений, сессий, HTTP и очереди; проверенные сценарии и состояние общего прогона.
order: 20
---

# Аудит ядра, 2026-10-26

Это исторический снимок аудита до завершения параллельного переноса
ядровых разделов. Приведённые ниже количества тестов и сбои относятся
к состоянию файлов в момент этих запусков, а не к текущей сборке.
Актуальная карта приложения — [README](README.md); аудит документации
не выполнял заново полный runtime-набор.

Проверяются реализованные пункты мастер-плана. Незакрытые этапы переезда
не считаются регрессиями. Отложенный HTTP/auth-аудит возобновлён по просьбе
владельца. Во время проверки другой агент продолжил перенос core-страниц
и API; его незавершённые изменения не исправлялись в рамках этого прохода.

## Проверено и исправлено

- `PropertyValueService::setValue()` принимал свойство любого класса.
  Теперь разрешены класс объекта и глобальные свойства (`class_id = 0`).
  Отказ не создаёт значение, историю или событие.
- `ValueRepository::write()` перезаписывал `created_at` при каждом
  обновлении. Дата создания сохраняется и при восстановлении удалённой
  строки; меняется `updated_at`.

Файлы: `app/Services/Properties/PropertyValueService.php`,
`app/Services/Properties/ValueRepository.php`,
`tests/Feature/PropertyValueIntegrityTest.php`,
`tests/Feature/CorePvaluesTest.php`, `docs/laiot/events.md`,
`docs/todo/laiot.md` и этот журнал. HTTP-фикстуры предварительно переведены
на `tests/Feature/SessionFixture.php` для проверки реального пользователя.

Регрессионные тесты сначала воспроизвели обе ошибки, после исправления
прошли. Связанный набор: `PropertyValueIntegrityTest`, `FunctionsTest`,
`WrappersTest`, `CoreEventsTest`, `CoreHistoryTest`, `CoreObjectsTest`,
`CorePvaluesTest` — **19 тестов, 131 assertion**, SQLite `:memory:`,
`APP_ENV=testing`, без рабочего bootstrap и внешних сервисов.

PHPStan, `php -l`, PHP-CS-Fixer dry-run для двух классов и нового теста —
без ошибок. `node --check`, `npm run build`, `config_smoke.php` прошли.
`docs_quality_smoke.php` при проверке остановился на `laiot/sdk.md`
(поле `audience`); файл в параллельной разработке. Старого
`tests/routing_smoke.php` в текущей ветке нет.

## Завершение HTTP/auth и очереди

- `AuthenticateAdmin` больше не пропускает сессии без `stamp`. Удаление
  пользователя, снятие `is_admin` и смена пароля отзывают старую сессию.
  Испорченный или нестроковый отпечаток также прекращает сессию.
- `UserRepository::sessionUser()` возвращает активного администратора с
  отпечатком текущего хеша пароля. Профиль после смены пароля обновляет
  текущую сессию; следующий защищённый запрос остаётся доступен.
- `ThrottleLogin` сбрасывает счётчик по актуальному редиректу `/admin`.
  Полный цикл: четыре неверных пароля → успешный вход → новая попытка
  проверен через HTTP-границу приложения.
- `Kernel` разрешает управление обслуживанием и выход, сохраняя `auth`,
  права и CSRF. Тест не меняет рабочий флаг: используется временный каталог.
- `APP_DEBUG` выключен по умолчанию и читается типизированным `Config`.
  Проверены скрытие подробностей в HTML/JSON и чтение булевой настройки.
- `DatabaseQueue` повторно резервирует просроченное задание с проверкой
  срока и состояния при UPDATE. Тест моделирует падение первого воркера
  и получение задания другим экземпляром очереди на той же SQLite.
- `ModuleManager` не обращается к реестру, если БД не настроена. Это убрало
  PHP-warning о недостающих `driver`/`database` в публичных запросах без БД.
- Завершены сессионные фикстуры HTTP/Yoyo/Shoutbox и проверка окружения
  `tests/phpunit.php`: рабочий bootstrap и `.env` не загружаются.

### Изменённые файлы этого этапа

Код: `bootstrap/app.php`, `config/app.php`, `app/Foundation/Config.php`,
`app/Services/Auth/UserRepository.php`, `app/Http/Middleware/AuthenticateAdmin.php`,
`app/Http/Middleware/ThrottleLogin.php`, `app/Http/Kernel.php`,
`app/Http/Controllers/ProfileController.php`, `app/Services/Queue/DatabaseQueue.php`,
`app/Services/Modules/ModuleManager.php`.

Проверки: `tests/Feature/SessionSecurityTest.php`, `LoginTest.php`,
`ProfilePageTest.php`, `MaintenanceTest.php`, `HttpKernelTest.php`,
`RolesPageTest.php`, `QueueTest.php`, `ThrottleLoginTest.php`, `YoyoTest.php`,
`CoreShoutboxTest.php` (затем удалён параллельным переносом),
остальные HTTP-фикстуры `tests/Feature`, `tests/phpunit.php`,
`tests/config_smoke.php`.

Документация: `docs/laiot/architecture.md`, `functions.md`, `testing.md`,
этот журнал и `docs/todo/laiot.md`.

### Результат проверок

- Финальный адресный прогон: **36 тестов, 158 assertions**, без PHP-warning.
  Сессии, вход, профиль, обслуживание, kernel, роли, очередь и Yoyo.
- PHPStan для изменённого кода и его тестов — без ошибок.
- Синтаксис PHP, PHP-CS-Fixer dry-run, `node --check`, `npm run build`,
  `config_smoke.php` и `docs_quality_smoke.php` прошли.
- Общий прогон на промежуточном состоянии параллельной разработки:
  **158 тестов, 4 failures** в `ApiWebhooksTest` — фикстура ещё запрашивала
  удаляемый модуль `api` через `ModuleDb`. Ранее тот же набор проходил
  до переноса этих файлов. Контракты нового API в этом проходе не менялись.
- Общий `composer check` остановился в PHPStan на избыточных `is_array()`
  в новых `app/Http/Controllers/Core/ObjectsController.php:116` и
  `UsersController.php:123`, которые создавались другим агентом.

После завершения параллельного переноса повторить общий `composer check`.
Это оставшаяся интеграционная проверка; пять отложенных исправлений
HTTP/auth и очереди завершены и проверены адресным набором.
