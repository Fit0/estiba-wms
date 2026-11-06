<?php
// Tries the ERP from end to end: takes an order, exports it, asks about it and confirms its shipment
// over SOAP. It makes its own order, so it can run as often as you like.
//   php bin/comprobar.php [base URL, default http://127.0.0.1]
require_once dirname(__FILE__) . '/../inc/exportacion.php';

$base = isset($argv[1]) ? rtrim($argv[1], '/') : 'http://127.0.0.1';
$fallos = 0;

function comprobar($condicion, $texto)
{
    global $fallos;
    echo ($condicion ? 'OK     ' : 'FALLO  ') . $texto . "\n";
    if (!$condicion) {
        $fallos++;
    }
}

function llamar($base, $operacion, $cuerpo)
{
    $sobre = '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"'
        . ' xmlns:e="urn:marea:erp:v1"><soap:Body><e:' . $operacion . '>' . $cuerpo . '</e:' . $operacion . '></soap:Body></soap:Envelope>';
    $contexto = stream_context_create(array('http' => array(
        'method' => 'POST',
        'header' => "Content-Type: text/xml; charset=utf-8\r\nSOAPAction: \"urn:marea:erp:v1#" . $operacion . '"',
        'content' => $sobre,
        'ignore_errors' => true,
        'timeout' => 10,
    )));

    return (string) file_get_contents($base . '/soap/erp.php', false, $contexto);
}

function codigo_de($respuesta)
{
    return preg_match('#<Codigo>(\d+)</Codigo>#', $respuesta, $m) ? (int) $m[1] : -1;
}

function estado_de($respuesta)
{
    return preg_match('#<Estado>(\w+)</Estado>#', $respuesta, $m) ? $m[1] : '';
}

erp_crear_tablas();

$wsdl = @file_get_contents($base . '/soap/erp.php?wsdl');
comprobar(strpos((string) $wsdl, 'ConfirmarEnvio') !== false, 'el WSDL describe ConfirmarEnvio');

$numero = erp_pedido_de_ejemplo();
$lineas = erp_lineas($numero);
comprobar($numero !== '' && count($lineas) > 0, "pedido $numero creado con " . count($lineas) . ' líneas');

erp_exportar_pendientes();
$fichero = erp_dir_salida() . '/' . $numero . '.csv';
comprobar(is_file($fichero), 'el fichero ' . basename($fichero) . ' está en la carpeta de salida');
$filas = is_file($fichero) ? file($fichero, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
comprobar(count($filas) === count($lineas) + 1, 'el CSV tiene la cabecera y una fila por línea');
comprobar(isset($filas[0]) && strpos($filas[0], 'NUMERO_PEDIDO;FECHA_PEDIDO;') === 0, 'la cabecera usa punto y coma');

comprobar(estado_de(llamar($base, 'ConsultarPedido', "<e:NumeroPedido>$numero</e:NumeroPedido>")) === 'EXPORTADO', 'el pedido consta como EXPORTADO');

$cuerpoLineas = '';
foreach ($lineas as $linea) {
    $cuerpoLineas .= '<e:Linea><e:Articulo>' . $linea['articulo'] . '</e:Articulo><e:Unidades>' . $linea['unidades'] . '</e:Unidades></e:Linea>';
}
$confirmacion = "<e:NumeroPedido>$numero</e:NumeroPedido><e:FechaEnvio>" . date('d/m/Y') . '</e:FechaEnvio>'
    . '<e:Transportista>SEUR</e:Transportista><e:Seguimiento>TRK123</e:Seguimiento><e:Bultos>1</e:Bultos>'
    . "<e:Lineas>$cuerpoLineas</e:Lineas>";

comprobar(codigo_de(llamar($base, 'ConfirmarEnvio', $confirmacion)) === ERP_OK, 'ConfirmarEnvio acepta el envío (código 0)');
comprobar(codigo_de(llamar($base, 'ConfirmarEnvio', $confirmacion)) === ERP_YA_CONFIRMADO, 'confirmarlo otra vez da el código 1 y no cambia nada');
comprobar(estado_de(llamar($base, 'ConsultarPedido', "<e:NumeroPedido>$numero</e:NumeroPedido>")) === 'ENVIADO', 'el pedido consta como ENVIADO');
comprobar(codigo_de(llamar($base, 'ConsultarPedido', '<e:NumeroPedido>NO-EXISTE</e:NumeroPedido>')) === ERP_PEDIDO_INEXISTENTE, 'un pedido desconocido da el código 2');

echo $fallos === 0 ? "ERP en orden\n" : "$fallos comprobaciones han fallado\n";
exit($fallos === 0 ? 0 : 1);
