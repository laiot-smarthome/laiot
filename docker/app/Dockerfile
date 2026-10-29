FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring pcntl \
    && rm -rf /var/lib/apt/lists/*

# Пул локального стенда: те же ручки, что в production, но числа меньше.
COPY php-fpm.d/zz-laiot.conf /usr/local/etc/php-fpm.d/zz-laiot.conf
COPY entrypoint.sh /usr/local/bin/laiot-entrypoint
RUN chmod +x /usr/local/bin/laiot-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["laiot-entrypoint"]
CMD ["php-fpm"]
