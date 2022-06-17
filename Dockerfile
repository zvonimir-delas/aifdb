FROM zdelas/aifdb-base:0.1
# FROM php:5.6-apache # Moved to Dockerfile-base
# Newly added for debugging
COPY docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/
COPY php.ini /usr/local/etc/php/
ADD AIFdb /var/www/html/
# Newly added for debugging
# RUN pecl install xdebug-2.5.5 && docker-php-ext-enable xdebug # Moved to Dockerfile-base
RUN mkdir /var/www/html/upload/tmp
RUN chmod 777 /var/www/html/upload/tmp
RUN mkdir -p /var/www/html/tmp
RUN chmod 777 /var/www/html/tmp
# Added for debugging
RUN touch /var/log/php-scripts.log
RUN chmod 777 /var/log/php-scripts.log
RUN a2enmod rewrite
RUN a2enmod headers 
# RUN docker-php-ext-install mysql mysqli pdo # Moved to Dockerfile-base
# RUN apt-get update && apt-get install -y graphviz mysql-client # Moved to Dockerfile-base
