<?php
require_once dirname(__FILE__) . '/pedidos.php';

// A SOAP 1.1 endpoint written by hand. The image has no SOAP extension, and an ERP of this age often
// had its own code for it anyway. It understands two operations, described in erp.wsdl.

define('ERP_NS', 'urn:marea:erp:v1');
define('ERP_ENV', 'http://schemas.xmlsoap.org/soap/envelope/');

function erp_wsdl($location)
{
    $plantilla = file_get_contents(dirname(__FILE__) . '/erp.wsdl');

    return str_replace('__LOCATION__', htmlspecialchars($location, ENT_QUOTES), $plantilla);
}

function erp_soap_sobre($contenido)
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soap:Envelope xmlns:soap="' . ERP_ENV . '"><soap:Body>' . $contenido . '</soap:Body></soap:Envelope>';
}

function erp_soap_fallo($codigo, $texto)
{
    return erp_soap_sobre('<soap:Fault><faultcode>soap:' . $codigo . '</faultcode><faultstring>'
        . htmlspecialchars($texto, ENT_QUOTES) . '</faultstring></soap:Fault>');
}

function erp_soap_elemento($nombre, $valor)
{
    return '<' . $nombre . '>' . htmlspecialchars((string) $valor, ENT_QUOTES) . '</' . $nombre . '>';
}

// The first child element called $nombre, whatever its prefix, or null.
function erp_soap_hijo($nodo, $nombre)
{
    foreach ($nodo->childNodes as $hijo) {
        if ($hijo->nodeType === XML_ELEMENT_NODE && $hijo->localName === $nombre) {
            return $hijo;
        }
    }

    return null;
}

function erp_soap_texto($nodo, $nombre)
{
    $hijo = erp_soap_hijo($nodo, $nombre);

    return $hijo === null ? '' : trim($hijo->textContent);
}

// Handles the text of a request. Answers array(HTTP status, response text).
function erp_soap_atender($entrada)
{
    $doc = new DOMDocument();
    // LIBXML_NONET keeps the parser away from the network; entities are not substituted, so no file is read.
    if (trim($entrada) === '' || !@$doc->loadXML($entrada, LIBXML_NONET)) {
        return array(500, erp_soap_fallo('Client', 'La petición no es un XML válido'));
    }
    if ($doc->doctype !== null) {
        return array(500, erp_soap_fallo('Client', 'No se admiten declaraciones DOCTYPE'));
    }

    $sobre = $doc->documentElement;
    $cuerpo = ($sobre->namespaceURI === ERP_ENV) ? erp_soap_hijo($sobre, 'Body') : null;
    $operacion = null;
    if ($cuerpo !== null) {
        foreach ($cuerpo->childNodes as $hijo) {
            if ($hijo->nodeType === XML_ELEMENT_NODE) {
                $operacion = $hijo;
                break;
            }
        }
    }
    if ($operacion === null) {
        return array(500, erp_soap_fallo('Client', 'Falta el cuerpo del mensaje SOAP'));
    }

    switch ($operacion->localName) {
        case 'ConfirmarEnvio':
            return array(200, erp_soap_confirmar_envio($operacion));
        case 'ConsultarPedido':
            return array(200, erp_soap_consultar_pedido($operacion));
    }

    return array(500, erp_soap_fallo('Client', 'Operación desconocida: ' . $operacion->localName));
}

function erp_soap_confirmar_envio($operacion)
{
    $lineas = array();
    $nodoLineas = erp_soap_hijo($operacion, 'Lineas');
    if ($nodoLineas !== null) {
        foreach ($nodoLineas->childNodes as $nodo) {
            if ($nodo->nodeType === XML_ELEMENT_NODE && $nodo->localName === 'Linea') {
                $lineas[] = array(erp_soap_texto($nodo, 'Articulo'), (int) erp_soap_texto($nodo, 'Unidades'));
            }
        }
    }

    $resultado = erp_confirmar_envio(
        erp_soap_texto($operacion, 'NumeroPedido'),
        erp_soap_texto($operacion, 'FechaEnvio'),
        erp_soap_texto($operacion, 'Transportista'),
        erp_soap_texto($operacion, 'Seguimiento'),
        (int) erp_soap_texto($operacion, 'Bultos'),
        $lineas
    );

    return erp_soap_sobre('<ConfirmarEnvioResponse xmlns="' . ERP_NS . '">'
        . erp_soap_elemento('Codigo', $resultado[0])
        . erp_soap_elemento('Mensaje', $resultado[1])
        . '</ConfirmarEnvioResponse>');
}

function erp_soap_consultar_pedido($operacion)
{
    $numero = erp_soap_texto($operacion, 'NumeroPedido');
    $pedido = erp_pedido($numero);
    $partes = '';

    if ($pedido === null) {
        $partes = erp_soap_elemento('Codigo', ERP_PEDIDO_INEXISTENTE) . erp_soap_elemento('Mensaje', 'El pedido ' . $numero . ' no existe');
    } else {
        $partes = erp_soap_elemento('Codigo', ERP_OK)
            . erp_soap_elemento('Mensaje', 'Pedido encontrado')
            . erp_soap_elemento('Estado', $pedido['estado']);
        if ($pedido['exportado_en'] !== null) {
            $partes .= erp_soap_elemento('ExportadoEn', erp_fecha_hora($pedido['exportado_en']));
        }
        if ($pedido['enviado_en'] !== null) {
            $partes .= erp_soap_elemento('EnviadoEn', erp_fecha_hora($pedido['enviado_en']));
        }
    }

    return erp_soap_sobre('<ConsultarPedidoResponse xmlns="' . ERP_NS . '">' . $partes . '</ConsultarPedidoResponse>');
}
