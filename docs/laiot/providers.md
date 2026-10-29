---
title: LaIOT — сервис-провайдеры
audience: agent, user
category: core
tags: laiot, providers, контейнер, boot, зависимости
description: Регистрация и запуск подсистем, ленивые singleton, расширение сборки и отличие от провайдеров полного Laravel.
order: 3
---

# Сервис-провайдеры

Application управляет жизненным циклом, а сборка подсистем находится в
`app/Providers`. Список и порядок запуска задаёт
`config/providers.php`. Механизм использует уже установленный Illuminate
Container и базовый класс `LaIOT\Foundation\ServiceProvider`.
Дополнительные пакеты или полный Laravel для этого не подключаются.

## Две фазы запуска

`Application::boot()` выполняет:

1. Создание контейнера и привязку Application/Container к их экземплярам.
2. Проверку списка классов, создание провайдеров и удаление повторов одного
   класса с сохранением первого положения в списке.
3. `register()` всех провайдеров в порядке списка.
4. `boot()` всех провайдеров в том же порядке.
5. Обновление имён/действий маршрутов, включая маршруты дополнительных
   провайдеров; затем приложение считается запущенным.

Повторный boot после успеха ничего не регистрирует заново. Рекурсивный
boot во время запуска отклоняется. Ошибка не помечает приложение готовым;
автоматического отката эффектов произвольного boot нет.

`register()` описывает bindings, aliases и фабрики, не выполняет запросы,
не запускает процессы и не регистрирует маршруты. Не нужно создавать все
сервисы заранее: concrete-зависимости собираются контейнером по типам
конструктора, интерфейсы и скалярные параметры связываются явно.

`boot()` выполняет действия, для которых уже могут потребоваться
зарегистрированные зависимости других провайдеров: подключает helpers,
настраивает адаптер представлений, регистрирует маршруты или обработчики.
Доступность регистрации не означает, что boot другой подсистемы уже прошёл:
зависимые действия должны соблюдать порядок списка.

## Провайдеры ядра

| Провайдер | Регистрация и действия |
| --- | --- |
| InfrastructureServiceProvider | Clock, логгер, БД, кэш, Queue/JobRegistry, события; каталоги, timezone, core helpers в boot |
| ValidationServiceProvider | Общая фабрика Illuminate Validation, русский Translator и unique/exists через общую БД; без запросов при регистрации |
| EncryptionServiceProvider | Ленивый Encrypter, ключи из конфигурации и шифрование атрибутов моделей |
| FilesServiceProvider | Диски ядра/модулей на Flysystem; каталоги создаются при записи |
| DomainServiceProvider | Репозитории, авторизация, операции свойств, сценариев, API и панельные сервисы |
| ViewServiceProvider | Blade, именованные aliases, Yoyo и компоненты; подготовка Yoyo в boot |
| HttpServiceProvider | Сессия, Router/middleware, Kernel, PanelRenderer; ядровые web/API-маршруты в boot |
| ModuleServiceProvider | ModuleManager/Installer; подключение SDK-маршрутов и представлений |
| RuntimeServiceProvider | Worker, CoreWorker и состояние; процессы не запускаются при загрузке |

Использование общей фабрики и правила проверки входных данных —
[validation.md](validation.md).

Общие сервисы регистрируются singleton: повторное разрешение в рамках
одного Application возвращает тот же объект. Особенно важен
PropertyValueService с цепочкой вложенных OnChange. Ошибки и обслуживание
также имеют один экземпляр для kernel и контроллеров.

Ленивой является фабрика сервиса, а не обязательно вся подсистема.
View boot готовит Yoyo, Module boot обнаруживает модули и может читать
настроенную БД. Это не система полностью отложенных Laravel-провайдеров.

## Дополнительный провайдер

Пример ниже можно поместить в
`app/Providers/ExampleServiceProvider.php`. Он использует существующие
LoggerFactory/JobRegistry и добавляет обработчик, не выполняя само задание.

```php
namespace LaIOT\Providers;

use Illuminate\Container\Container;
use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Logging\LoggerFactory;
use LaIOT\Services\Queue\JobRegistry;
use Psr\Log\LoggerInterface;

final class ExampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('example.logger', static fn (Container $container): LoggerInterface =>
            $container->make(LoggerFactory::class)->channel('example'));
    }

    public function boot(): void
    {
        $logger = $this->container->make('example.logger');
        $this->container->make(JobRegistry::class)->register('example.log',
            static function (array $payload) use ($logger): void {
                $logger->info('Обработано примерное задание', $payload);
            });
    }
}
```

Добавьте `LaIOT\Providers\ExampleServiceProvider::class` в конец
массива `config/providers.php`. Каждый HTTP/CLI-процесс, собирающий это
приложение, получит обработчик при boot. Это решает задачу регистрации
обработчика для отдельного worker, в отличие от регистрации только в
одном HTTP-контроллере.

В провайдере доступны защищённые `$this->container` и `$this->app`.
Последний предоставляет root/config; публичные сервисные getters
Application предназначены для уже завершённого boot. Во время настройки
разрешайте сервис через контейнер провайдера.

## Переопределение и тесты

Дополнительный провайдер, идущий после стандартных, может заменить binding
до создания объекта. Регистрация всех классов завершается до первого boot,
поэтому такая подмена доступна стандартным boot-методам. Переопределение
уже созданного объекта не перенастраивает автоматически его потребителей.

Если конструктору Application явно передан ключ `providers`, этот список
заменяет стандартный целиком, без рекурсивного смешивания индексов.
Для расширения в тесте сначала загрузите стандартный список и добавьте
к нему тестовые классы; отключение базовых провайдеров может сделать
приложение неполным. Некорректный класс/пустой список отклоняются.

Все остальные настройки Application сохраняют рекурсивное объединение.
Перезапуск процесса нужен, чтобы долгоживущий worker увидел новый список.
Нельзя запускать миграции или фоновые циклы безусловно из boot-провайдера.

Это провайдеры приложения. Они не заменяют ModuleContext и lifecycle
SDK-модуля: специфичные для интеграции hooks остаются внутри её пакета.
Класс `Illuminate\Support\ServiceProvider` полного Laravel здесь не
используется: он рассчитан на другой Application-контракт.

Регрессии порядка фаз, подмены сервиса, singleton и повторного boot —
`tests/Feature/ServiceProvidersTest.php`. Существующие проверки HTTP,
Yoyo, событий свойств и SDK проверяют сохранение поведения приложения.
