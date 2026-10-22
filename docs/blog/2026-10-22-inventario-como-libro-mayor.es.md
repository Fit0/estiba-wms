---
title: "El inventario como libro mayor en Symfony hexagonal"
date: 2026-10-22
lang: es
translation: 2026-10-22-inventory-as-a-ledger.en.md
tags: [symfony, ddd, arquitectura-hexagonal, cqrs, wms]
---

# El inventario como libro mayor en Symfony hexagonal

He pasado muchos años construyendo ERP y almacenes para retail de moda, y hay una pregunta que se repite en todos ellos: "¿de dónde salen estas 12 unidades?". El sistema dice 12 y en la estantería hay 9. Si el stock es un número en una columna que cualquiera puede sobrescribir, no hay respuesta posible. El número es el que es y nadie sabe cómo llegó ahí.

En [Estiba](https://github.com/Fit0/estiba-wms), el WMS que estoy construyendo como proyecto de portfolio, decidí desde el primer día que el stock no se edita. Se mueve. Este post cuenta cómo lo he montado con Symfony 7.4, Doctrine y una arquitectura hexagonal, y qué ha costado.

## La idea: contabilidad, no un contador

Un contable no borra un apunte equivocado. Hace otro que lo compensa. El saldo de una cuenta es la suma de sus apuntes, y si alguien pregunta por qué el saldo es ese, se le enseña la lista.

Con el stock hago lo mismo. Hay dos piezas:

- `StockItem` (existencia): las unidades de un producto, en una ubicación, de un lote y una caducidad, con un estado (disponible, cuarentena...).
- `StockMovement` (movimiento): un apunte inmutable. Cuánto cambió, cuánto quedó, quién lo hizo, cuándo y con qué referencia.

Y unas reglas con nombre, que en el repo tienen su ID y su test:

| ID | Regla |
|---|---|
| STK-01 | El stock nunca es negativo. |
| STK-03 | Cada cambio del stock se registra con exactamente un movimiento, en la misma transacción. |
| STK-04 | Un movimiento nunca se modifica ni se borra. Los errores se corrigen con otro movimiento. |
| STK-05 | La suma de los movimientos de una existencia es igual a lo que tiene. |
| STK-08 | Un traspaso quita de una existencia y añade a otra: las dos cosas o ninguna. |

## Un solo sitio donde cambian las unidades

La forma más fiable que conozco de cumplir STK-03 es que solo haya un lugar en todo el código donde cambie el stock. En `StockItem` es este método privado:

```php
private function applyChange(MovementType $type, StockMovementId $movementId, Quantity $after, int $change, ?MovementReference $reference, ?AdjustmentReason $reason, AuthorId $author, \DateTimeImmutable $at): void
{
    $this->onHand = $after;
    $this->recorded[] = new StockMovement($movementId, $this->tenantId, $this->id, $type, $change, $after, $reference, $reason, $author, $at);
    $this->record(new StockMovementRecorded($movementId, $this->tenantId, $this->id, $type, $change, $at));
}
```

Recibir, sacar, regularizar y traspasar pasan todos por aquí. No hay un `setOnHand()`. Si mañana alguien quiere cambiar el stock sin dejar rastro, tendrá que escribir el método que lo haga, y eso se ve en una revisión de código.

STK-01 lo protege `Quantity`, un value object que no puede ser negativo. `Quantity::minus()` lanza `NegativeStockNotAllowed` antes de que nada cambie. Todos los métodos de `StockItem` comprueban todo antes de tocar nada, así que una operación rechazada no deja medio cambio hecho.

El traspaso es el caso interesante, porque toca dos agregados:

```php
$remaining = $this->onHand->minus($quantity);
$arriving = $destination->onHand->plus($quantity);

$this->applyChange(MovementType::TransferOut, $outMovementId, $remaining, -$quantity->units, $reference, null, $author, $at);
$destination->applyChange(MovementType::TransferIn, $inMovementId, $arriving, $quantity->units, $reference, null, $author, $at);
```

Primero se calculan los dos saldos. Si el origen no tiene bastante, la excepción salta ahí y ninguna de las dos existencias ha cambiado. Después se aplican los dos cambios. La transacción de la base de datos hace el resto.

## Dónde queda Doctrine

El dominio es PHP puro: ni atributos de Doctrine ni nada de Symfony. El mapeo está en XML, en `Infrastructure/Persistence/Doctrine`. Y no es una convención que haya que recordar: un test de arquitectura con PHPat falla el análisis estático si alguien importa el framework desde el dominio.

`StockItem` no guarda la lista de sus movimientos. Sería absurdo cargar diez mil apuntes para recibir una caja. Guarda los que ha registrado desde que se cargó, y el repositorio los persiste junto con la existencia:

```php
public function save(StockItem $stockItem): void
{
    $this->entityManager->persist($stockItem);

    foreach ($stockItem->pullMovements() as $movement) {
        $this->entityManager->persist($movement);
    }
}
```

El repositorio no tiene método para borrar, y la entidad `StockMovement` está mapeada como `read-only="true"`. STK-04 tiene tres defensas: la clase no tiene setters, el repositorio no puede borrar y Doctrine ignora cualquier cambio en una fila ya guardada.

## El detalle que no esperaba: NULL en una clave única

Una existencia es única por cliente, producto, ubicación, lote, caducidad y estado. Muchos productos no tienen lote ni caducidad. Una camiseta no caduca. Así que puse un índice único sobre esas seis columnas y lo di por resuelto.

No lo estaba. En SQL, `NULL` no es igual a `NULL`, así que dos filas sin lote nunca chocan en un índice único. Se podían crear dos existencias de la misma camiseta en la misma ubicación. PostgreSQL 15 tiene `NULLS NOT DISTINCT`, pero las herramientas de esquema de Doctrine no lo conocen, y la base de datos de los tests y la de las migraciones habrían dejado de coincidir.

La solución fue poco elegante pero clara: en la base de datos, "sin lote" se guarda como `''` y "sin caducidad" como `9999-12-31`, a través de dos tipos DBAL. Ningún lote real está vacío y ningún producto caduca ese día. El dominio sigue viendo `null`. Está en el [ADR 0011](../adr/0011-putaway-and-stock-booked-by-event-policies.md), con las alternativas que descarté.

## Los demás contextos no tocan el libro

Recepción (Inbound) mete stock cuando se cierra un albarán y lo mueve cuando un operario confirma una ubicación. Pero no conoce `StockItem`. Habla con Inventory a través de un contrato publicado:

```php
interface StockMovements
{
    public function receive(StockArrival $arrival): void;

    public function transfer(StockTransfer $transfer): void;
}
```

Los DTOs llevan strings e ints, no objetos del dominio de Inventory. El mismo test de arquitectura impide que un contexto importe nada de otro salvo su `Application/Contract`. Así el libro mayor tiene una sola puerta de entrada.

## Leer el stock: la parte que acabo de terminar

Un libro mayor es estupendo para escribir y bastante incómodo para leer. Una pantalla de stock no quiere un `product_id` y un `location_id`. Quiere el SKU, el nombre del producto, el código de ubicación y el almacén. Esos datos viven en Catalog y en Warehouse, y la regla del proyecto es que no se hacen JOIN entre tablas de contextos distintos.

Barajé enriquecer cada página llamando a los contratos (60 llamadas para listar 30 existencias, y sin poder ordenar por SKU en SQL) y una vista de base de datos que cruzara las tablas (rápida de escribir y justo lo que la regla prohíbe). Me quedé con una proyección:

1. Cada movimiento publica `StockMovementRecorded`.
2. El evento va a un stream de Redis y lo procesa el worker de Messenger, después de que el cambio haga commit.
3. Una política carga la existencia, pide el producto y la ubicación a los contratos y escribe una fila en `stock_overview`.

El punto 3 tiene truco. La tentación es hacer `on_hand = on_hand + change` con cada evento. Pero un worker puede recibir el mismo mensaje dos veces, o dos mensajes en otro orden, y sumar dos veces es exactamente el tipo de error que el libro mayor quería evitar. Así que la política no suma: lee la existencia como está ahora y escribe la fila entera con un upsert.

```php
$this->projection->save(new StockOverviewEntry(
    $stockItem->id()->value,
    $tenantId,
    $product->id,
    $product->sku,
    $product->name,
    $location->warehouseId,
    $location->warehouseCode,
    $location->id,
    $location->code,
    $key->lot?->value,
    $key->expiryDate,
    $key->status,
    $stockItem->onHand()->units,
));
```

Procesarla dos veces da la misma fila. No hace falta una tabla de mensajes procesados.

La tabla se escribe y se lee con SQL a pelo (DBAL). Un modelo de lectura no tiene reglas que proteger, y cargar entidades solo añadiría coste. Y aquí "sin lote" vuelve a ser `NULL`, porque esta tabla no tiene una clave única que defender.

Lo que más me gusta de todo esto es que el modelo de lectura es desechable. Si se corrompe, si cambia su forma o si alguien renombra un producto, `make stock-rebuild` lo vacía y lo reconstruye desde el libro mayor en una transacción. Mientras tanto, quien consulte ve los datos anteriores. Esa garantía solo existe porque el stock es una lista de hechos y no un número suelto.

## Lo que cuesta

Nada de esto sale gratis, y prefiero decirlo:

- Hay consistencia eventual. Si un operario ubica una caja y refresca la lista al instante, puede que aún no la vea. En los tests el transporte es síncrono y no se nota. En producción suele ser una fracción de segundo, pero existe.
- El SKU, el nombre y los códigos son copias. Si se renombra un producto, las filas viejas no se enteran hasta que esa existencia se mueva o se reconstruya la vista. Cuando una pantalla lo necesite, Catalog publicará un evento `ProductRenamed`.
- Todavía no hay bloqueo de existencias. Con un solo worker no es un problema real. Cuando llegue la reserva de stock para pedidos (FEFO y FIFO), lo será.
- Hay más piezas: un evento, una política, una tabla, un comando para reconstruir y un ADR. Para un CRUD sería excesivo. Para un almacén, donde la pregunta "¿de dónde salen estas unidades?" tiene que tener respuesta, creo que compensa.

## Lo siguiente

Pedidos y reserva de stock: elegir qué existencias cubren cada línea, primero las que caducan antes, y que dos pedidos no se lleven la misma caja. Ahí sí que el bloqueo va a importar.

El código está en [GitHub](https://github.com/Fit0/estiba-wms). Las decisiones de este post están en los ADR [0011](../adr/0011-putaway-and-stock-booked-by-event-policies.md), [0012](../adr/0012-asynchronous-policies-with-a-redis-transport.md) y [0013](../adr/0013-a-stock-read-model-fed-by-the-ledger.md).
