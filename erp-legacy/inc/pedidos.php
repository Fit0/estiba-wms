<?php
require_once dirname(__FILE__) . '/db.php';
require_once dirname(__FILE__) . '/catalogo.php';

// What the ERP does with orders: take them, say what state they are in and record that they shipped.

// The codes the ERP answers with. They are part of its contract with the WMS (see the README).
define('ERP_OK', 0);
define('ERP_YA_CONFIRMADO', 1);
define('ERP_PEDIDO_INEXISTENTE', 2);
define('ERP_NO_EXPORTADO', 3);
define('ERP_ARTICULO_AJENO', 4);
define('ERP_UNIDADES_INVALIDAS', 5);
define('ERP_FECHA_INVALIDA', 6);
define('ERP_DATOS_INCOMPLETOS', 7);

// Urgency, as the ERP writes it: B baja, N normal, A alta, U urgente.
function erp_urgencias()
{
    return array('B', 'N', 'A', 'U');
}

function erp_siguiente_numero()
{
    $db = erp_db();
    $db->beginTransaction();
    $fila = $db->query("SELECT valor FROM contadores WHERE nombre = 'pedido'")->fetch(PDO::FETCH_ASSOC);
    $valor = $fila ? (int) $fila['valor'] + 1 : 1;
    $sql = $fila
        ? "UPDATE contadores SET valor = $valor WHERE nombre = 'pedido'"
        : "INSERT INTO contadores (nombre, valor) VALUES ('pedido', $valor)";
    $db->exec($sql);
    $db->commit();

    return 'P' . date('Y') . sprintf('%06d', $valor);
}

// $cliente is array(name, street, postal code, city, country); $lineas is a list of array(article, units).
function erp_crear_pedido($cliente, $lineas, $urgencia, $fechaLimite)
{
    $db = erp_db();
    $numero = erp_siguiente_numero();

    $db->beginTransaction();
    $stmt = $db->prepare('INSERT INTO pedidos (numero, fecha, cliente, direccion, cp, poblacion, pais, urgencia, fecha_limite)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(array($numero, date('Y-m-d H:i:s'), $cliente[0], $cliente[1], $cliente[2], $cliente[3], $cliente[4], $urgencia, $fechaLimite));

    $stmt = $db->prepare('INSERT INTO lineas (pedido, linea, articulo, unidades) VALUES (?, ?, ?, ?)');
    $n = 0;
    foreach ($lineas as $linea) {
        $n++;
        $stmt->execute(array($numero, $n, $linea[0], (int) $linea[1]));
    }
    $db->commit();

    return $numero;
}

// An order made up on the spot, for demos: a random customer, one to four articles, a due date in a few days.
function erp_pedido_de_ejemplo()
{
    $clientes = erp_clientes();
    $articulos = erp_articulos();
    $lineas = array();
    $usados = array();
    $cuantas = mt_rand(1, 4);

    while (count($lineas) < $cuantas) {
        $articulo = $articulos[mt_rand(0, count($articulos) - 1)];
        if (isset($usados[$articulo])) {
            continue;
        }
        $usados[$articulo] = true;
        $lineas[] = array($articulo, mt_rand(1, 6));
    }

    $urgencias = erp_urgencias();
    $limite = date('Y-m-d', time() + mt_rand(1, 3) * 86400) . ' 14:00:00';

    return erp_crear_pedido($clientes[mt_rand(0, count($clientes) - 1)], $lineas, $urgencias[mt_rand(0, 3)], $limite);
}

function erp_pedido($numero)
{
    $stmt = erp_db()->prepare('SELECT * FROM pedidos WHERE numero = ?');
    $stmt->execute(array($numero));
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return $fila ? $fila : null;
}

function erp_lineas($numero)
{
    $stmt = erp_db()->prepare('SELECT * FROM lineas WHERE pedido = ? ORDER BY linea');
    $stmt->execute(array($numero));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function erp_todos_los_pedidos()
{
    return erp_db()->query('SELECT * FROM pedidos ORDER BY numero DESC')->fetchAll(PDO::FETCH_ASSOC);
}

// Dates are stored as 2026-11-06 14:00:00 and spoken as 06/11/2026 14:00, which is how the ERP writes them.
function erp_fecha_hora($valor)
{
    if ($valor === null || $valor === '') {
        return '';
    }
    $fecha = DateTime::createFromFormat('Y-m-d H:i:s', $valor);

    return $fecha ? $fecha->format('d/m/Y H:i') : '';
}

function erp_fecha($valor)
{
    $fecha = DateTime::createFromFormat('Y-m-d H:i:s', $valor);

    return $fecha ? $fecha->format('d/m/Y') : '';
}

// A date as the WMS sends it, dd/mm/yyyy. Returns Y-m-d, or null when it is not a real date.
function erp_leer_fecha($texto)
{
    $fecha = DateTime::createFromFormat('d/m/Y', trim($texto));
    if (!$fecha || $fecha->format('d/m/Y') !== trim($texto)) {
        return null;
    }

    return $fecha->format('Y-m-d');
}

// Records that an order left the warehouse. $lineas is a list of array(article, units shipped).
// Answers array(code, message). Nothing changes unless the code is ERP_OK.
function erp_confirmar_envio($numero, $fechaEnvio, $transportista, $seguimiento, $bultos, $lineas)
{
    $pedido = erp_pedido($numero);
    if ($pedido === null) {
        return array(ERP_PEDIDO_INEXISTENTE, 'El pedido ' . $numero . ' no existe');
    }
    if ($pedido['estado'] === 'ENVIADO') {
        return array(ERP_YA_CONFIRMADO, 'El pedido ' . $numero . ' ya estaba confirmado');
    }
    if ($pedido['estado'] !== 'EXPORTADO') {
        return array(ERP_NO_EXPORTADO, 'El pedido ' . $numero . ' todavía no se ha exportado');
    }
    if (trim($transportista) === '' || $bultos < 1) {
        return array(ERP_DATOS_INCOMPLETOS, 'Hacen falta el transportista y al menos un bulto');
    }
    $fecha = erp_leer_fecha($fechaEnvio);
    if ($fecha === null) {
        return array(ERP_FECHA_INVALIDA, 'La fecha de envío debe ser dd/mm/aaaa');
    }

    if (count($lineas) === 0) {
        return array(ERP_DATOS_INCOMPLETOS, 'Hace falta al menos una línea');
    }

    $pedidas = array();
    foreach (erp_lineas($numero) as $linea) {
        $pedidas[$linea['articulo']] = (int) $linea['unidades'];
    }
    $enviadas = array();
    foreach ($lineas as $linea) {
        $articulo = $linea[0];
        $unidades = $linea[1];
        if (!isset($pedidas[$articulo])) {
            return array(ERP_ARTICULO_AJENO, 'El artículo ' . $articulo . ' no está en el pedido');
        }
        if ($unidades < 0 || $unidades > $pedidas[$articulo]) {
            return array(ERP_UNIDADES_INVALIDAS, 'Unidades no válidas para el artículo ' . $articulo);
        }
        $enviadas[$articulo] = $unidades;
    }

    $db = erp_db();
    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE lineas SET enviadas = ? WHERE pedido = ? AND articulo = ?');
    foreach ($pedidas as $articulo => $unidades) {
        // An article the WMS did not mention shipped nothing.
        $stmt->execute(array(isset($enviadas[$articulo]) ? $enviadas[$articulo] : 0, $numero, $articulo));
    }
    $stmt = $db->prepare("UPDATE pedidos SET estado = 'ENVIADO', enviado_en = ?, transportista = ?, seguimiento = ?, bultos = ? WHERE numero = ?");
    $stmt->execute(array($fecha . ' 00:00:00', $transportista, $seguimiento, (int) $bultos, $numero));
    $db->commit();

    return array(ERP_OK, 'Envío confirmado');
}
