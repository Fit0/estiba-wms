<?php
// Takes a made-up order, or as many as the first argument says: php bin/nuevo_pedido.php 5
require_once dirname(__FILE__) . '/../inc/pedidos.php';

erp_crear_tablas();
$cuantos = isset($argv[1]) ? max(1, (int) $argv[1]) : 1;

for ($i = 0; $i < $cuantos; $i++) {
    echo erp_pedido_de_ejemplo() . "\n";
}
