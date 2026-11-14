---
title: "Una capa anticorrupción para un ERP legacy"
date: 2026-11-14
lang: es
translation: 2026-11-14-anti-corruption-layer-legacy-erp.en.md
tags: [symfony, ddd, capa-anticorrupcion, strangler-fig, legacy, integracion, wms]
---

# Una capa anticorrupción para un ERP legacy

Todos los sistemas de almacén en los que he trabajado acaban hablando con un ERP. El ERP suele ser más viejo que el sistema de almacén, más viejo que el equipo y, a veces, más viejo que el edificio donde corre. Tiene sus propias palabras para las cosas. Escribe las fechas con el día primero. Responde con números en vez de errores. Y la integración es el sitio donde un dominio limpio se ensucia, un nombre de columna cada vez.

En [Estiba](https://github.com/Fit0/estiba-wms) quise probar lo contrario. El ERP puede ser todo lo raro que quiera, siempre que la rareza termine en una sola puerta. Este post cuenta esa puerta, el ERP falso que tuve que construir para probarla y lo que fue saliendo mal. Al final hay una nota sobre el patrón strangler fig, porque la puerta resulta ser lo que lo hace posible.

## Un ERP viejo a propósito

Un proyecto de portfolio no trae un ERP de serie, así que escribí uno, en `erp-legacy/`. Intenté que fuera viejo en lo que da problemas:

- PHP 5.6, funciones globales, `include`, SQLite.
- Nombres en español para todo: `NUMERO_PEDIDO`, `ARTICULO`, `ConfirmarEnvio`.
- Los pedidos salen como ficheros CSV en Latin-1, separados por `;`, con fechas `dd/mm/yyyy` y la urgencia en una letra (`B`, `N`, `A` o `U`).
- Los envíos vuelven por SOAP, y el ERP nunca lanza un fault por un problema de negocio. Contesta con un código. 0 es registrado, 1 es ya confirmado, 2 es que no existe el pedido, y así.

El endpoint SOAP está escrito a mano. Había pensado usar `ext-soap`, pero la imagen `php:5.6` está sobre un Debian cuyos repositorios de paquetes ya no existen, y instalar la extensión obligaba a reescribir las fuentes de apt. En aquella época el SOAP a mano era habitual, además. El precio: el endpoint es código mío, así que puede aceptar cosas que un servidor real rechazaría. Vuelvo a esto más abajo.

## Un contexto, dos puertos

Estiba es un monolito modular, así que la integración es un contexto delimitado, `Erp`. Puede depender de los contratos publicados de Catalog, Outbound y Warehouse, y nada depende de él. Dentro hay dos puertos:

- `ErpOrderFiles` lee lo que deja el ERP.
- `ErpGateway` avisa al ERP de que un envío ha salido.

Sus implementaciones, `CsvErpOrderFiles` y `SoapErpGateway`, son el único código que conoce el CSV, el Latin-1 o el SOAP. El modelo de pedido del ERP se queda en `Erp/Domain`, con las palabras del ERP, y una sola clase lo convierte al lenguaje del almacén:

| El ERP dice | El almacén dice |
|---|---|
| `NUMERO_PEDIDO` | la referencia del pedido, y su identidad |
| `ARTICULO` | un producto del catálogo, buscado por SKU |
| `URGENCIA` `B`, `N`, `A`, `U` | prioridad `low`, `normal`, `high`, `urgent` |
| `FECHA_LIMITE` `08/11/2026 14:00`, hora de España | un instante, `2026-11-08T13:00:00+00:00` |

Lo que sale del traductor es un `IncomingOrder` que no sabe que existe un ERP. Outbound lo registra con el mismo comando `RegisterOrder` que usa la API, así que un pedido del ERP cumple las mismas reglas que uno tecleado a mano. El número de pedido del ERP es la identidad: registrar una referencia que ya existe no es un error, contesta `AlreadyRegistered`. Esa decisión es la que hace inofensivo importar dos veces el mismo fichero.

## Errores que dicen qué hacer

Los códigos del ERP son un idioma, y no quiero que el resto del código lo aprenda. La pasarela lee el código y lanza algo que dice qué hacer:

```php
return match ($code) {
    self::CODE_OK => ConfirmationOutcome::Confirmed,
    self::CODE_ALREADY_CONFIRMED => ConfirmationOutcome::AlreadyConfirmed,
    self::CODE_NOT_EXPORTED => throw ErpUnavailable::because($message),
    default => throw ErpRejectedShipment::withCode($confirmation->orderReference, $code, $message),
};
```

`ErpUnavailable` significa reintentar más tarde. `ErpRejectedShipment` significa no hacerlo, porque el mismo mensaje tendrá la misma respuesta. El código 3, "todavía no exportado", es cuestión de tiempo, así que es del primer tipo. El código 1 es un éxito, y ahora explico por qué.

## Un fallo que encontró el ERP

El ERP da las horas límite con el reloj de España, `08/11/2026 14:00`, y el traductor las convertía en instantes correctos. Y entonces el pedido volvía de la base de datos con una hora de retraso.

La hora límite no se convertía a UTC antes de guardarla, y la columna no guarda zona horaria. Así que las 14:00 de Madrid se guardaban como 14:00 y se leían como 14:00 UTC. La API tenía el mismo problema con cualquier desfase distinto de cero. Solo lo vi porque el ERP habla en hora de Madrid. Ahora la hora límite se convierte a UTC al registrar el pedido.

## Leer ficheros con un temporizador

Symfony Scheduler manda `ImportErpOrders` cada 30 segundos, y un contenedor `scheduler` lo consume. Para el SFTP uso phpseclib, que es PHP puro, así que no hay extensión que instalar. Hay tres decisiones que merece la pena dejar escritas:

1. **Idempotente por pedido, no por fichero.** El importador registra el pedido y después mueve el fichero. Si el proceso muere entre una cosa y otra, la siguiente ejecución encuentra el pedido ya registrado y solo mueve el fichero.
2. **Un fichero malo no frena a los demás.** Lo que el dominio rechaza manda ese fichero a `rejected/` con un aviso, y la ejecución sigue. Un fallo del servidor SFTP o de la base de datos detiene la ejecución, y el siguiente ciclo empieza de nuevo con los mismos ficheros.
3. **La importación va por un bus sin transacción.** Esta es fácil de hacer mal. Si toda la importación corriera dentro de la transacción del bus de comandos, un fallo al registrar un pedido, aunque lo capture, marca la transacción como solo-rollback y se pierden los pedidos ya registrados. Por eso cada pedido pasa por el bus de comandos con su propia transacción, y el trabajo decide fichero a fichero.

Los ficheros se mueven, nunca se borran. `processed/` y `rejected/` son el rastro para quien tenga que averiguar qué mandó el ERP.

## Avisar al ERP: un outbox

Cuando un pedido sale del almacén, el ERP tiene que enterarse. Pero el ERP puede estar caído, lento o ocupado con su propia exportación, y una expedición no puede esperarle ni fallar por su culpa.

Outbound publica `ShipmentLeft`, que pasa por Redis hasta el worker. Una política de `Erp` escribe una fila en `erp_shipment_outbox`, pero solo si el pedido vino del ERP. Los pedidos tecleados en Estiba solo recibirían "no existe el pedido". Cada 30 segundos un trabajo envía lo que toca. Tras un fallo espera un minuto, luego duplica la espera hasta una hora, y el octavo fallo es el último, unas tres horas en total. Si el ERP rechaza una confirmación, la fila pasa a `failed` al momento con el motivo, porque el mismo mensaje se rechazaría otra vez.

La entrega es al menos una vez. Si el proceso muere después de que el ERP aceptara una confirmación y antes de marcar la fila, se vuelve a enviar. Por eso importa el código 1: el ERP contesta "ya confirmado", la pasarela lo trata como éxito y nadie se entera. Elegí ese comportamiento a propósito para el ERP simulado. Los ERP reales varían en esto, y en una integración real es de lo primero que miraría. Sin él, la idempotencia te toca construirla a ti.

## Mantener el contrato a raya

Los tests de la pasarela usan una copia del WSDL del ERP, y el ERP tiene el original. Dos copias de un contrato acaban por separarse. `WsdlContractTest` las compara, ignorando los finales de línea y la dirección del servicio, y falla si difieren. En un contenedor que solo monta `backend/` no ve el original, así que se salta y dice por qué. La CI clona todo el repositorio, así que ahí sí se ejecuta.

Es también mi respuesta al endpoint permisivo. Los tests toman el WSDL como contrato, no lo que acepte mi SOAP hecho a mano.

Queda una pieza. Cuando funciona nadie lo mira, y cuando no, los únicos sitios donde mirar eran una carpeta SFTP, una tabla y los logs. Así que cada fichero que procesa la importación deja una fila en `erp_import_log`, y un supervisor puede ver importaciones y confirmaciones en una pantalla y devolver a la cola una confirmación fallida. El reintento no llama al ERP. Pone la fila otra vez en `pending`, y el trabajo sigue siendo el único que envía.

## Dónde encaja el strangler fig

El patrón strangler fig trata de reemplazar un sistema legacy sin un big bang. Pones algo nuevo delante, le vas desviando una pieza cada vez, y el sistema viejo se encoge hasta que puedes apagarlo.

Estiba no está estrangulando este ERP. El ERP es el dueño de los pedidos de cliente y nunca quise reemplazarlo. Pero el contexto `Erp` te da la costura por donde lo harías. Si el ERP tuviera que irse, el trabajo estaría al otro lado de los dos puertos: un adaptador nuevo para cada uno, o un caso de uso nuevo en el almacén y el final de un flujo. Outbound e Inbound no se enterarían.

Eso no lo he construido aquí, y no quiero aparentar lo contrario. Más adelante, en este mismo proyecto, hago lo de verdad: una aplicación PHP 5.6 con Symfony delante, enrutando las rutas migradas y moviendo un módulo cada vez.

## Lo que no hace

- Un solo tenant y un solo almacén reciben los pedidos del ERP. El ERP no dice cuál, así que van en la configuración.
- Solo puede correr un `scheduler`. Con dos, el mismo fichero podría leerse dos veces a la vez.
- Un fichero rechazado no se reintenta. Se arregla la causa y se devuelve el fichero a mano.
- El registro de importaciones no tiene política de retención.
- Si Redis pierde el evento entre la expedición y el outbox, se pierde una confirmación. Una comprobación que compare pedidos enviados con el outbox lo detectaría. No está hecha.
- La clave del host SFTP no se verifica en desarrollo.
- Solo cruzan pedidos y confirmaciones de envío. Los datos maestros y las compras no.

## Lo siguiente

Las funcionalidades de IA: clasificar productos, sugerir ubicaciones y explicar anomalías, todo a través de Claude y todo de solo lectura.

El código está en [GitHub](https://github.com/Fit0/estiba-wms). Las decisiones están en los ADR [0046](../adr/0046-a-simulated-legacy-erp.md), [0047](../adr/0047-an-anti-corruption-layer-for-the-erp.md), [0048](../adr/0048-a-scheduled-import-of-erp-orders.md), [0049](../adr/0049-a-database-outbox-for-erp-confirmations.md), [0050](../adr/0050-an-integration-panel-and-a-contract-test-for-the-erp.md) y [0051](../adr/0051-the-erp-integration-as-a-whole.md), y los diagramas en [ERP integration](../architecture/erp-integration.md).
