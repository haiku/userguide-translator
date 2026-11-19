#FROM docker.io/richarvey/nginx-php-fpm:latest
# 3.2.0 HACK to upgrade PHP to 8.2.35 for CVE-2026-109618, CVE-2026-109619
# 3.2.1 HACK to upgrade PHP to 8.2.36
#  https://github.com/richarvey/nginx-php-fpm/pull/308
#  https://github.com/richarvey/nginx-php-fpm/issues/307
FROM ghcr.io/haiku/nginx-php-fpm:3.2.1

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
