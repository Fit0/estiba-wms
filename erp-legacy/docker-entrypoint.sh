#!/bin/sh
# Starts the ERP: prepares its folders and database, exports the pending orders now and then, and
# hands over to Apache. The exporter is a loop here because a real ERP of this age would run it
# from the system cron.
set -e

mkdir -p "$ERP_DATA_DIR" "$ERP_OUTBOX_DIR"
chown -R www-data:www-data "$ERP_DATA_DIR"
# The SFTP container shares this folder and its user has another id: anyone may add and move files.
chmod 0777 "$ERP_OUTBOX_DIR"

run_as_erp() {
    su -s /bin/sh www-data -c "php /var/www/bin/$1"
}

run_as_erp iniciar_bd.php

(
    while true; do
        run_as_erp exportar_pedidos.php || true
        sleep "$ERP_EXPORT_INTERVAL"
    done
) &

exec docker-php-entrypoint "$@"
