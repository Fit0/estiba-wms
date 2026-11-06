<?php
require_once dirname(__FILE__) . '/../../inc/soap.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET' && isset($_GET['wsdl'])) {
    header('Content-Type: text/xml; charset=utf-8');
    echo erp_wsdl('http://' . $_SERVER['HTTP_HOST'] . '/soap/erp.php');
    exit;
}

if ($metodo !== 'POST') {
    header('Allow: GET, POST', true, 405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERP legacy. WSDL: /soap/erp.php?wsdl\n";
    exit;
}

$respuesta = erp_soap_atender(file_get_contents('php://input'));
header('Content-Type: text/xml; charset=utf-8', true, $respuesta[0]);
echo $respuesta[1];
