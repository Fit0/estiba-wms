<?php
require_once dirname(__FILE__) . '/config.php';

// One connection per request, kept in a static variable like old code does.
function erp_db()
{
    static $db = null;

    if ($db === null) {
        $dir = erp_dir_datos();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $db = new PDO('sqlite:' . $dir . '/erp.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_TIMEOUT, 10);
    }

    return $db;
}

function erp_crear_tablas()
{
    $db = erp_db();
    $db->exec('CREATE TABLE IF NOT EXISTS pedidos (
        numero TEXT PRIMARY KEY,
        fecha TEXT NOT NULL,
        cliente TEXT NOT NULL,
        direccion TEXT NOT NULL,
        cp TEXT NOT NULL,
        poblacion TEXT NOT NULL,
        pais TEXT NOT NULL,
        urgencia TEXT NOT NULL,
        fecha_limite TEXT NOT NULL,
        estado TEXT NOT NULL DEFAULT \'PENDIENTE\',
        exportado_en TEXT,
        enviado_en TEXT,
        transportista TEXT,
        seguimiento TEXT,
        bultos INTEGER
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS lineas (
        pedido TEXT NOT NULL,
        linea INTEGER NOT NULL,
        articulo TEXT NOT NULL,
        unidades INTEGER NOT NULL,
        enviadas INTEGER,
        PRIMARY KEY (pedido, linea)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS contadores (
        nombre TEXT PRIMARY KEY,
        valor INTEGER NOT NULL
    )');
}
