#!/bin/sh
set -eu
PORT="${PORT:-3000}"
case "$PORT" in
  ''|*[!0-9]*) echo 'PORT inválida.' >&2; exit 1 ;;
esac
sed -ri "s/^Listen [0-9]+$/Listen $PORT/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
php /var/www/html/bin/migrate.php
exec apache2-foreground
