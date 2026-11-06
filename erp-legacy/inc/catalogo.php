<?php
// The articles the ERP sells and the customers it serves. They mirror the Marea Moda demo of the
// WMS (backend/src/Demo/Fashion), so an order of the ERP names articles the WMS knows. If the demo
// catalogue changes, change this list too.

function erp_articulos()
{
    $modelos = array(
        array('CAM-LIN', array('S', 'M', 'L'), array('BLA', 'AZU')),
        array('PAN-CHI', array('38', '40', '42', '44'), array('ARE', 'MAR')),
        array('VES-MID', array('S', 'M', 'L'), array('NEG', 'VER')),
        array('CHA-LIG', array('S', 'M', 'L'), array('BEI', 'NEG')),
        array('JER-PUN', array('S', 'M', 'L'), array('GRI', 'ROJ')),
        array('CIN-CUE', array('85', '95', '105'), array('MRR')),
        array('ZAP-LON', array('38', '40', '42', '44'), array('BLA')),
    );
    $articulos = array();

    foreach ($modelos as $modelo) {
        foreach ($modelo[2] as $color) {
            foreach ($modelo[1] as $talla) {
                $articulos[] = 'PV27-' . $modelo[0] . '-' . $color . '-' . $talla;
            }
        }
    }

    return $articulos;
}

function erp_clientes()
{
    return array(
        array('Marea Moda Sevilla Nervión', 'Calle Luis de Morales 32', '41005', 'Sevilla', 'ES'),
        array('Marea Moda Valencia Colón', 'Calle Colón 14', '46004', 'Valencia', 'ES'),
        array('Marea Moda Bilbao Gran Vía', 'Gran Vía de Don Diego López de Haro 25', '48009', 'Bilbao', 'ES'),
        array('Franquicia Costa del Sol', 'Avenida de Andalucía 118', '29006', 'Málaga', 'ES'),
        array('Franquicia Lisboa Chiado', 'Rua Garrett 41', '1200-203', 'Lisboa', 'PT'),
        array('Franquicia Madrid Salamanca', 'Calle de Serrano 61', '28006', 'Madrid', 'ES'),
    );
}
