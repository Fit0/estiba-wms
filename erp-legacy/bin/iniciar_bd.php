<?php
// Creates the tables and, on a new database, a few orders so there is something to import.
require_once dirname(__FILE__) . '/../inc/pedidos.php';

erp_crear_tablas();

$cuantos = (int) erp_config('ERP_SEED_ORDERS', 3);
$hay = (int) erp_db()->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();

if ($hay === 0) {
    for ($i = 0; $i < $cuantos; $i++) {
        erp_pedido_de_ejemplo();
    }
    echo "ERP: $cuantos pedidos de ejemplo creados\n";
}
