# A deliberately old ERP: PHP 5.6 on Apache, procedural code, SQLite for its data. It is the
# "legacy system" that Estiba integrates with (ADR 0046). Do not use this image for anything real.
FROM php:5.6-apache

# The image is built on a Debian that no longer has package mirrors, so nothing is installed with
# apt: the SOAP endpoint is written by hand on top of DOM, which the base image already has.
COPY php.ini /usr/local/etc/php/conf.d/erp.ini
COPY www /var/www/html
COPY inc /var/www/inc
COPY bin /var/www/bin
COPY docker-entrypoint.sh /usr/local/bin/erp-entrypoint

ENV ERP_DATA_DIR=/var/erp/data \
    ERP_OUTBOX_DIR=/var/erp/outbox \
    ERP_EXPORT_INTERVAL=15 \
    ERP_SEED_ORDERS=3

RUN chmod +x /usr/local/bin/erp-entrypoint

ENTRYPOINT ["erp-entrypoint"]
CMD ["apache2-foreground"]
