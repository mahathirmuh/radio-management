# =====================================================================
# MTI Radio Management: the Laravel application, served by Apache with
# mod_php from public/. The same setup as the other apps of this team.
#
# What the image does not hold: .env (mounted at run time, so the image
# carries no password and a change of settings needs no rebuild), and the
# contents of storage/ (a named volume, see docker-compose.yml).
# =====================================================================
FROM php:8.4-apache-bookworm

# ---------------------------------------------------------------------
# System packages
# ---------------------------------------------------------------------
# msodbcsql18 and unixodbc-dev are for SQL Server: the database of the
# application and the HR database ("Find employee"). The base image is
# pinned to Debian 12 (bookworm) because that is the Microsoft repository
# named here. The rest are build dependencies of the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        unixodbc-dev \
    && curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------
# PHP extensions
# ---------------------------------------------------------------------
# The image already has what Laravel needs besides these (mbstring, curl,
# dom, xml, fileinfo, ...). gd draws the E-Sign signatures, zip reads and
# writes Excel workbooks (Import, Export, ICT Masterlist), ftp is asked for
# by league/flysystem-ftp (a package of the base app; composer refuses to
# install without it). No ldap: Active Directory is spoken to by
# freedsx/ldap, in PHP.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        zip \
        intl \
        ftp \
        opcache \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# ---------------------------------------------------------------------
# PHP configuration
# ---------------------------------------------------------------------
# Import Excel takes a workbook of up to 20 MB (RadioImportController), so
# PHP allows a little more than that. On Linux max_execution_time counts
# CPU time, not the time spent waiting for SharePoint or SQL Server.
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'upload_max_filesize = 24M'; \
        echo 'post_max_size = 32M'; \
        echo 'max_execution_time = 120'; \
        echo 'expose_php = Off'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# ---------------------------------------------------------------------
# Apache
# ---------------------------------------------------------------------
# Laravel is served from public/, never from the project root: otherwise
# .env would be one request away from being downloaded.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# The access log without the query string: a search of the register or of
# the employee directory (?search=..., ?q=...) can carry a person's name,
# and the log of the web server is not the place for it.
RUN printf '%s\n' 'LogFormat "%h %l %u %t \"%m %U\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" combined' \
        > /etc/apache2/conf-available/log-no-query.conf \
    && a2enconf log-no-query

WORKDIR /var/www/html

# ---------------------------------------------------------------------
# Dependencies, then code
# ---------------------------------------------------------------------
# composer.json and the lock file first, so a change of the code does not
# install every package again. --no-scripts: the artisan scripts need the
# whole source tree, which comes after.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY . .

# The folders of storage/ are made here: .dockerignore keeps their
# contents (logs, sessions, cache) out of the image, and with the contents
# the folders. Docker fills an empty named volume from what the image has
# at that place, so a folder missing here would be missing for good.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
