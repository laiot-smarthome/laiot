#!/usr/bin/env bash
# 2026-10-30 — код копируется в Linux-том только при первом запуске, дальнейшие релизы не затираются.
set -euo pipefail

root=/srv/laiot

# Docker создаёт отдельный cgroup namespace. Разрешаем systemd менять только его поддерево.
mount -o remount,rw /sys/fs/cgroup

install -d -m 0755 /etc/laiot /run/php
for source in /opt/laiot-server-config/*; do
    target="/etc/laiot/${source##*/}"
    if [[ ! -e $target ]]; then install -m 0644 "$source" "$target"; fi
done
ln -sfn /etc/laiot/nginx.conf /etc/nginx/sites-enabled/laiot
ln -sfn /etc/laiot/php-fpm.conf /etc/php/8.3/fpm/pool.d/laiot.conf
ln -sfn /etc/laiot/php.ini /etc/php/8.3/fpm/conf.d/99-laiot.ini
ln -sfn /etc/laiot/php.ini /etc/php/8.3/cli/conf.d/99-laiot.ini
ln -sfn /etc/laiot/mariadb.cnf /etc/mysql/mariadb.conf.d/99-laiot.cnf

if [[ ! -f "$root/.server-initialized" ]]; then
    if [[ -e "$root/app" || -e "$root/current" ]]; then
        printf '%s\n' 'Незавершённая первичная подготовка: сохранённые файлы не перезаписываются.' >&2
        exit 1
    fi
    install -d -o laiot -g laiot -m 0755 "$root/var"
    cp -a /opt/laiot-seed/. "$root/"
    # Чистая установка использует обычный .env; раскладку релизов подготовит штатный обновитель.
    if [[ ! -e "$root/.env" ]]; then
        umask 0077
        printf 'APP_ENV=production\nAPP_DEBUG=false\nAPP_URL=%s\nAPP_TIMEZONE=Europe/Moscow\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_NAME=\nDB_USER=laiot\nDB_PASSWORD=laiot\nCACHE_DRIVER=file\nUPDATE_REPOSITORY=laiot-smarthome/laiot\nUPDATE_CHANNEL=beta\n' "${LAIOT_SERVER_URL:-http://127.0.0.1:8082}" > "$root/.env"
        umask 0022
    fi
    /usr/bin/php <<'PHP'
<?php
require '/srv/laiot/vendor/autoload.php';
use LaIOT\Foundation\ConfigLoader;
use LaIOT\Services\Updates\UpdateFactory;
use LaIOT\Services\Updates\UpdateFiles;
$root = '/srv/laiot';
$var = $root . '/var';
$updates = UpdateFactory::make($root, ConfigLoader::load($root));
UpdateFiles::json($root . '/release.json', $updates->current());
UpdateFiles::json($var . '/install-connection.json', ['db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'laiot', 'db_user' => 'laiot', 'db_password' => 'laiot', 'cache_driver' => 'redis', 'redis_host' => '127.0.0.1', 'redis_port' => '6379', 'redis_database' => '0']);
PHP
    chown -R laiot:laiot "$root"
    chmod 0600 "$root/.env"
    touch "$root/.server-initialized"
fi

if [[ ! -d /var/lib/mysql/mysql ]]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi
chown mysql:mysql /var/lib/mysql
chown redis:redis /var/lib/redis

exec /sbin/init
