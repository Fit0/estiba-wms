<?php
require_once dirname(__FILE__) . '/../inc/pedidos.php';

function h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

$pedidos = erp_todos_los_pedidos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>ERP Marea Moda</title>
<style>
body { font-family: Verdana, sans-serif; font-size: 13px; margin: 20px; }
table { border-collapse: collapse; }
th, td { border: 1px solid #999; padding: 4px 8px; text-align: left; }
th { background: #ddd; }
</style>
</head>
<body>
<h1>ERP Marea Moda</h1>
<p>Pedidos de cliente. <a href="/soap/erp.php?wsdl">WSDL</a></p>
<table>
<tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Urgencia</th><th>Límite</th><th>Líneas</th><th>Estado</th><th>Envío</th></tr>
<?php foreach ($pedidos as $p) { ?>
<tr>
<td><?php echo h($p['numero']); ?></td>
<td><?php echo h(erp_fecha($p['fecha'])); ?></td>
<td><?php echo h($p['cliente']); ?></td>
<td><?php echo h($p['urgencia']); ?></td>
<td><?php echo h(erp_fecha_hora($p['fecha_limite'])); ?></td>
<td><?php echo count(erp_lineas($p['numero'])); ?></td>
<td><?php echo h($p['estado']); ?></td>
<td><?php echo $p['estado'] === 'ENVIADO' ? h(erp_fecha_hora($p['enviado_en']) . ' ' . $p['transportista']) : ''; ?></td>
</tr>
<?php } ?>
</table>
</body>
</html>
