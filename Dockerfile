FROM docker.io/richarvey/nginx-php-fpm:latest

# Add application artifacts
ADD userguide/. /var/app/userguide/
ADD scripts/* /var/scripts/

# Install the requirements
RUN apk update && \
    apk add postgresql-dev && \
    docker-php-ext-install pdo pdo_pgsql && \
    mkdir /var/app/userguide/data && \
    ls -la /var/app/userguide && \
    chmod 755 /var/scripts && \
    chown -R nginx:nginx /var/app/userguide

ENV WEBROOT='/var/app'
ENV DATA_DIR='/var/app/userguide/data'
ENV SCRIPTS_DIR='/var/scripts'
ENV RUN_SCRIPTS='1'
