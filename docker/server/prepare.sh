#!/usr/bin/env bash
# 2026-10-30 — штатный серверный setup на выделенной БД; пароли и конфиги не сбрасываются при перезапуске.
set -euo pipefail

if [[ ! -f /var/lib/mysql/.laiot-user-created ]]; then
    mariadb --protocol=socket <<'SQL'
CREATE DATABASE IF NOT EXISTS laiot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'laiot'@'localhost' IDENTIFIED BY 'laiot';
CREATE USER IF NOT EXISTS 'laiot'@'127.0.0.1' IDENTIFIED BY 'laiot';
GRANT ALL PRIVILEGES ON laiot.* TO 'laiot'@'localhost';
GRANT ALL PRIVILEGES ON laiot.* TO 'laiot'@'127.0.0.1';
SQL
    touch /var/lib/mysql/.laiot-user-created
fi

bash /srv/laiot/deploy/setup-ubuntu.sh --root /srv/laiot --user laiot --group laiot --php-bin /usr/bin/php
