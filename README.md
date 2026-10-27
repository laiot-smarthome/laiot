# MyDoMo

Новая ветка MajorDoMo: PHP 8.2+, компоненты Illuminate 12, Blade/Alpine.
Alpha-runtime (`lib/`, `templates/`, `scripts/`, `languages/`, `cms/`,
alpha-модули и старые входы) удалён из этой ветки; историю и старую
реализацию смотрите в ветке `alpha`.

## Быстрый старт

Document root — `public/`, код приложения — уровнем выше.

```bash
cp .env.example .env          # Windows: copy .env.example .env; заполните DB_*
composer install
npm ci
npm run build                 # админ-бандл, редактор и графики
php bin/mydomo db:migrate     # схема новой установки
php bin/mydomo user:create admin "Администратор" "пароль" --admin
php -S 127.0.0.1:8080 -t public tools/dev-server.php
```

Открыть `http://127.0.0.1:8080/login`. Для локальной отладки без Docker
этого достаточно; MySQL/MariaDB и при необходимости Redis должны быть
запущены.

Есть готовый Docker-стенд: `docker compose up --build -d`, панель на
`http://127.0.0.1:8080`, администратор `admin` / `majordomo`. Подробности —
[docs/mydomo/docker.md](docs/mydomo/docker.md).

## Проверки

```bash
composer check                    # lint, PHPStan, CS-Fixer, Feature-тесты
php tests/docs_quality_smoke.php  # документация
npm run build                     # статика
```

## Документация

- [docs/mydomo/README.md](docs/mydomo/README.md) — карта нового ядра.
- [docs/mydomo/architecture.md](docs/mydomo/architecture.md) — устройство и путь запроса.
- [docs/todo/mydomo.md](docs/todo/mydomo.md) — мастер-план и журнал изменений.
- [AGENTS.md](AGENTS.md) — правила работы с репозиторием.

## Что ещё не готово

Новый установщик, обновление с откатом, API v2/OpenAPI, импорт данных из
alpha и Docker-стенд нового ядра. Установка сейчас — `db:migrate` +
`user:create`; API v1 живёт в `routes/api.php` и `public/index.php`.
Подробности — [limitations.md](docs/mydomo/limitations.md).

## Лицензия

См. [LICENSE](LICENSE).
