#!/bin/bash
set -e

# Update Apache to listen on Render's $PORT
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/:80/:${PORT}/" /etc/apache2/sites-enabled/000-default.conf
fi

# Laravel optimizations — skip if DB is unreachable (first boot race)
echo "Running Laravel pre-boot tasks..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations — force (no prompt) so deploys auto-apply schema changes
echo "Running database migrations..."
php artisan migrate --force

# Make queue worker log destinations writable by www-data
mkdir -p /var/log
touch /var/log/queue.log /var/log/queue-error.log
chown www-data:www-data /var/log/queue.log /var/log/queue-error.log

# Supervise Apache + the Laravel queue worker together
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
