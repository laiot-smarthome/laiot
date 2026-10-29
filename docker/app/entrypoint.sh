#!/usr/bin/env bash
# Локальный стенд LaIOT: ждём БД, применяем миграции, создаём админа.
# 2026-10-27 — начальные данные соответствуют политике логина и пароля.
set -euo pipefail

cd /var/www/html

mkdir -p var/cache var/log var/locks var/sessions var/security
chown -R www-data:www-data var 2>/dev/null || true

echo "[laiot] ждём базу ${DB_HOST:-db}:${DB_PORT:-3306}"
for attempt in $(seq 1 60); do
    if php -r '
        $dsn = "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT");
        try {
            new PDO($dsn, getenv("DB_USER"), getenv("DB_PASSWORD"));
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    ' 2>/dev/null; then
        break
    fi
    sleep 2
done

echo "[laiot] миграции"
php bin/laiot db:migrate

echo "[laiot] администратор"
php bin/laiot user:create "${LAIOT_ADMIN_USER:-sysadmin}" "${LAIOT_ADMIN_NAME:-Администратор}" "${LAIOT_ADMIN_PASSWORD:-laiot!}" --admin || true

exec "$@"
