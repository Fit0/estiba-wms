<?php
// Where the ERP keeps things. The paths come from the environment so Docker can place them.

function erp_config($clave, $defecto)
{
    $valor = getenv($clave);

    return ($valor === false || $valor === '') ? $defecto : $valor;
}

function erp_dir_datos()
{
    return erp_config('ERP_DATA_DIR', dirname(__FILE__) . '/../data');
}

function erp_dir_salida()
{
    return erp_config('ERP_OUTBOX_DIR', dirname(__FILE__) . '/../outbox');
}
