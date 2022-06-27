FROM docker.io/richarvey/nginx-php-fpm:latest

# Add application artifacts
ADD userguide/. /var/app/userguide/
ADD scripts/* /var/scripts/

# Install the requirements
RUN apk update && \
    apk add postgresql-dev && \
    docker-php-ext-install pdo pdo_pgsql && \
    ls -la /var/app/userguide && \
    mkdir /var/app/userguide/source_docs && \
    chmod 755 /var/scripts && \
    chown -R nginx:nginx /var/app/userguide && \
    chmod -R 775 /var/app/userguide/source_docs

ENV WEBROOT='/var/app'
ENV SCRIPTS_DIR='/var/scripts'
ENV RUN_SCRIPTS='1'
