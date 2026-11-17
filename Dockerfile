FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev libsqlite3-dev libonig-dev ca-certificates \
    && docker-php-ext-install pdo_mysql pdo_sqlite curl mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nOptions -Indexes\nRequire all granted\n</Directory>\nServerName localhost\n' > /etc/apache2/conf-available/bio.conf \
    && a2enconf bio

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/var \
    && chmod 755 /var/www/html/bin/start.sh \
    && printf 'expose_php=Off\nupload_max_filesize=2M\npost_max_size=4M\ndisplay_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/bio.ini

WORKDIR /var/www/html
EXPOSE 3000
CMD ["/var/www/html/bin/start.sh"]
