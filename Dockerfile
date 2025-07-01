FROM php:8.1-apache as base

COPY ./src /var/www/html

RUN apt update -q && \
    apt install -q -y libpq-dev && \
    docker-php-ext-install pdo_pgsql pgsql && \
    apt install nano && \
    apt install cron -y && \
    mkdir /etc/cronlog && \
    touch /etc/cronlog/delete_users_schedule.log
    
COPY ./cron.conf /etc/cron.d/crontab.conf
RUN chmod 0644 /etc/cron.d/crontab.conf