# 2026-10-30 — Ubuntu с настоящим systemd и обычными серверными службами.
# Официальное зеркало Docker Library доступно и при сбоях auth.docker.io.
FROM public.ecr.aws/docker/library/ubuntu:24.04

ARG DEBIAN_FRONTEND=noninteractive
ENV container=docker TZ=Europe/Moscow

RUN printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
       systemd systemd-sysv dbus nginx mariadb-server mariadb-client redis-server mosquitto \
       php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-intl php8.3-curl \
       php8.3-zip php8.3-xml php8.3-sqlite3 php8.3-bcmath php8.3-gd php8.3-opcache \
       ca-certificates curl tzdata nano less procps iproute2 util-linux \
    && rm -rf /var/lib/apt/lists/* \
    && ln -snf /usr/share/zoneinfo/Europe/Moscow /etc/localtime

RUN useradd --create-home --shell /bin/bash laiot \
    && rm /etc/nginx/sites-enabled/default /etc/php/8.3/fpm/pool.d/www.conf

WORKDIR /opt/laiot-seed
COPY app ./app
COPY bin ./bin
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY deploy ./deploy
COPY docs ./docs
COPY modules/core ./modules/core
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY vendor ./vendor
COPY composer.json composer.lock ./

COPY docker/server/config/ /opt/laiot-server-config/
COPY docker/server/entrypoint.sh /usr/local/sbin/laiot-server-entrypoint
COPY docker/server/prepare.sh /usr/local/sbin/laiot-server-prepare
COPY docker/server/systemd/ /etc/systemd/system/
RUN chmod 0755 /usr/local/sbin/laiot-server-entrypoint /usr/local/sbin/laiot-server-prepare \
    && find /etc/systemd/system -type f -exec chmod 0644 {} + \
    && systemctl enable nginx php8.3-fpm mariadb redis-server mosquitto \
       laiot-server-prepare.service laiot-after-install.path \
    && systemctl mask systemd-udevd.service systemd-udevd-control.socket \
       systemd-udevd-kernel.socket systemd-modules-load.service getty.target

WORKDIR /srv/laiot
EXPOSE 80
STOPSIGNAL SIGRTMIN+3
ENTRYPOINT ["/usr/local/sbin/laiot-server-entrypoint"]
