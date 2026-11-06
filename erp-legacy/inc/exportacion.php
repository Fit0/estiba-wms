<?php
require_once dirname(__FILE__) . '/pedidos.php';

// The ERP tells the world about its new orders by leaving one CSV file per order in a folder that an
// SFTP server shows. Files are Latin-1, separated by semicolons and end their lines with CRLF, as the
// old tools that read them expect.

function erp_cabecera_csv()
{
    return array('NUMERO_PEDIDO', 'FECHA_PEDIDO', 'CLIENTE', 'DIRECCION', 'CP', 'POBLACION', 'PAIS', 'URGENCIA', 'FECHA_LIMITE', 'LINEA', 'ARTICULO', 'UNIDADES');
}

function erp_campo_csv($valor)
{
    $valor = (string) $valor;
    if (strpos($valor, ';') !== false || strpos($valor, '"') !== false || strpos($valor, "\n") !== false) {
        $valor = '"' . str_replace('"', '""', $valor) . '"';
    }

    return $valor;
}

function erp_linea_csv($campos)
{
    return implode(';', array_map('erp_campo_csv', $campos)) . "\r\n";
}

// The text of the file of an order, in UTF-8. It has a header and one row per line of the order.
function erp_csv_de_pedido($numero)
{
    $pedido = erp_pedido($numero);
    $texto = erp_linea_csv(erp_cabecera_csv());

    foreach (erp_lineas($numero) as $linea) {
        $texto .= erp_linea_csv(array(
            $pedido['numero'],
            erp_fecha($pedido['fecha']),
            $pedido['cliente'],
            $pedido['direccion'],
            $pedido['cp'],
            $pedido['poblacion'],
            $pedido['pais'],
            $pedido['urgencia'],
            erp_fecha_hora($pedido['fecha_limite']),
            $linea['linea'],
            $linea['articulo'],
            $linea['unidades'],
        ));
    }

    return $texto;
}

// Writes the file of every pending order and marks it exported. Returns the numbers it wrote.
// The file appears whole or not at all: it is written under a name that starts with a dot and then renamed.
function erp_exportar_pendientes()
{
    $dir = erp_dir_salida();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $exportados = array();
    $pendientes = erp_db()->query("SELECT numero FROM pedidos WHERE estado = 'PENDIENTE' ORDER BY numero")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($pendientes as $numero) {
        $final = $dir . '/' . $numero . '.csv';
        $temporal = $dir . '/.' . $numero . '.csv.tmp';
        file_put_contents($temporal, mb_convert_encoding(erp_csv_de_pedido($numero), 'ISO-8859-1', 'UTF-8'));
        chmod($temporal, 0666);
        rename($temporal, $final);

        $stmt = erp_db()->prepare("UPDATE pedidos SET estado = 'EXPORTADO', exportado_en = ? WHERE numero = ?");
        $stmt->execute(array(date('Y-m-d H:i:s'), $numero));
        $exportados[] = $numero;
    }

    return $exportados;
}
