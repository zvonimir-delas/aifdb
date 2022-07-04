FROM php:5.6-apache

# Newly added for debugging
RUN pecl install xdebug-2.5.5 && docker-php-ext-enable xdebug

# Moved higher to enable caching
RUN docker-php-ext-install mysql mysqli pdo
RUN apt-get update && apt-get install -y graphviz mysql-client

# Newly added for debugging
COPY docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/

COPY php.ini /usr/local/etc/php/

RUN a2enmod rewrite
RUN a2enmod headers

# Moved lower to enable caching of layers above
ADD AIFdb /var/www/html/

RUN mkdir /var/www/html/upload/tmp
RUN chmod 777 /var/www/html/upload/tmp
RUN mkdir /var/www/html/tmp
RUN chmod 777 /var/www/html/tmp
