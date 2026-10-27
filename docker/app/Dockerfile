FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /usr/local/bin/mydomo-entrypoint
RUN chmod +x /usr/local/bin/mydomo-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["mydomo-entrypoint"]
CMD ["apache2-foreground"]
