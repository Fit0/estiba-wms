<?php
// Leaves the CSV file of every order that has not been exported yet. Run by the entrypoint every few seconds.
require_once dirname(__FILE__) . '/../inc/exportacion.php';

foreach (erp_exportar_pendientes() as $numero) {
    echo "ERP: pedido $numero exportado\n";
}
