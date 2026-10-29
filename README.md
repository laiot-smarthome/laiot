# LaIOT

LaIOT — отдельное приложение на PHP 8.2+, компонентах Illuminate 12 и
Blade/Alpine. Alpha-runtime (`lib/`, `templates/`, `scripts/`, `languages/`,
`cms/`, alpha-модули и старые входы) удалён из этой ветки; историю и старую
реализацию MajorDoMo смотрите в ветке `alpha`.

## Быстрый старт

Document root — `public/`, код приложения — уровнем выше.

```bash
cp .env.example .env          # Windows: copy .env.example .env; заполните DB_*
composer install
npm ci
npm run build                 # админ-бандл, редактор и графики
php bin/laiot db:migrate     # схема новой установки
php bin/laiot user:create admin "Администратор" "пароль" --admin
php -S 127.0.0.1:8080 -t public tools/dev-server.php
```

Открыть `http://127.0.0.1:8080/login`. Для локальной отладки без Docker
этого достаточно; MySQL/MariaDB и при необходимости Redis должны быть
запущены.

Есть готовый Docker-стенд: `docker compose up --build -d`, панель на
`http://127.0.0.1:8080`, администратор `sysadmin` / `laiot!`. Подробности —
[docs/laiot/docker.md](docs/laiot/docker.md).

## Проверки

```bash
composer check                    # lint, PHPStan, CS-Fixer, Feature-тесты
php tests/docs_quality_smoke.php  # документация
npm run build                     # статика
```

## Документация

- [docs/laiot/README.md](docs/laiot/README.md) — карта нового ядра.
- [docs/laiot/architecture.md](docs/laiot/architecture.md) — устройство и путь запроса.
- [docs/todo/laiot.md](docs/todo/laiot.md) — мастер-план и журнал изменений.
- [AGENTS.md](AGENTS.md) — правила работы с репозиторием.

## Что ещё не готово

Новый установщик, обновление с откатом, API v2/OpenAPI, импорт данных из
alpha и Docker-стенд нового ядра. Установка сейчас — `db:migrate` +
`user:create`; API v1 живёт в `routes/api.php` и `public/index.php`.
Подробности — [limitations.md](docs/laiot/limitations.md).

## Лицензия

См. [LICENSE](LICENSE).
