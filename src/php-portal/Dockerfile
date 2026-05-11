<<<<<<< HEAD
FROM php:8.1-apache
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql
=======
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

COPY . /var/www/html/
>>>>>>> 1a5930bdf99ce8baef2353d41463b45f5c94d05a
