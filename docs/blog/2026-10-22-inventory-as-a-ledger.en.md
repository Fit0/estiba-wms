---
title: "Inventory as a ledger in hexagonal Symfony"
date: 2026-10-22
lang: en
translation: 2026-10-22-inventario-como-libro-mayor.es.md
tags: [symfony, ddd, hexagonal-architecture, cqrs, wms]
---

# Inventory as a ledger in hexagonal Symfony

I spent many years building ERP and warehouse systems for fashion retail, and one question comes up in all of them: "where did these 12 units come from?" The system says 12 and the shelf holds 9. If stock is a number in a column that anyone can overwrite, nobody can answer that. The number is what it is, and nobody knows how it got there.

In [Estiba](https://github.com/Fit0/estiba-wms), the WMS I'm building as a portfolio project, I decided on day one that stock is never edited. It moves. This post is about how I built that with Symfony 7.4, Doctrine and a hexagonal architecture, and what it cost.

## The idea: bookkeeping, not a counter

An accountant doesn't erase a wrong entry. They add another one that cancels it. An account's balance is the sum of its entries, and if someone asks why the balance is what it is, you show them the list.

Stock works the same way here. There are two pieces:

- `StockItem`: the units of one product, in one location, of one lot and expiry date, with one status (available, quarantine...).
- `StockMovement`: an immutable entry. How much changed, how much was left, who did it, when, and with which reference.

And a few named rules. In the repo each one has an ID and a test:

| ID | Rule |
|---|---|
| STK-01 | Stock is never negative. |
| STK-03 | Every change of stock is recorded by exactly one movement, in the same transaction. |
| STK-04 | A movement is never updated or deleted. Mistakes are fixed with another movement. |
| STK-05 | The movements of a stock item add up to what it holds. |
| STK-08 | A transfer takes from one stock item and adds to another: both or neither. |

## One place where units change

The most reliable way I know to keep STK-03 is to have exactly one place in the code where stock changes. In `StockItem` it is this private method:

```php
private function applyChange(MovementType $type, StockMovementId $movementId, Quantity $after, int $change, ?MovementReference $reference, ?AdjustmentReason $reason, AuthorId $author, \DateTimeImmutable $at): void
{
    $this->onHand = $after;
    $this->recorded[] = new StockMovement($movementId, $this->tenantId, $this->id, $type, $change, $after, $reference, $reason, $author, $at);
    $this->record(new StockMovementRecorded($movementId, $this->tenantId, $this->id, $type, $change, $at));
}
```

Receiving, issuing, adjusting and transferring all go through it. There is no `setOnHand()`. If someone wants to change stock without a trace, they'll have to write the method that does it, and that shows up in a code review.

STK-01 is guarded by `Quantity`, a value object that cannot be negative. `Quantity::minus()` throws `NegativeStockNotAllowed` before anything changes. Every method of `StockItem` checks everything before it touches anything, so a refused operation never leaves half a change behind.

Transfers are the interesting case, because they touch two aggregates:

```php
$remaining = $this->onHand->minus($quantity);
$arriving = $destination->onHand->plus($quantity);

$this->applyChange(MovementType::TransferOut, $outMovementId, $remaining, -$quantity->units, $reference, null, $author, $at);
$destination->applyChange(MovementType::TransferIn, $inMovementId, $arriving, $quantity->units, $reference, null, $author, $at);
```

Both balances are worked out first. If the source doesn't hold enough, the exception is thrown there and neither stock item has changed. Then both changes are applied. The database transaction does the rest.

## Where Doctrine lives

The domain is plain PHP, with no Doctrine attributes and nothing from Symfony. The mapping is XML, in `Infrastructure/Persistence/Doctrine`. This isn't a convention you have to remember: a PHPat architecture test fails static analysis if anyone imports the framework into the domain.

`StockItem` doesn't hold its list of movements. Loading ten thousand entries to receive one box would be silly. It keeps the ones it recorded since it was loaded, and the repository persists them with the stock item:

```php
public function save(StockItem $stockItem): void
{
    $this->entityManager->persist($stockItem);

    foreach ($stockItem->pullMovements() as $movement) {
        $this->entityManager->persist($movement);
    }
}
```

The repository has no delete method, and the `StockMovement` entity is mapped `read-only="true"`. So STK-04 has three guards: the class has no setters, the repository can't delete, and Doctrine ignores any change to a row it already saved.

## The detail I didn't expect: NULL in a unique key

A stock item is unique by tenant, product, location, lot, expiry date and status. Lots of products have no lot and no expiry date. A T-shirt doesn't expire. So I put a unique index on those six columns and called it done.

It wasn't. In SQL, `NULL` is not equal to `NULL`, so two rows without a lot never clash in a unique index. You could end up with two stock items for the same T-shirt in the same location. PostgreSQL 15 has `NULLS NOT DISTINCT`, but Doctrine's schema tools don't know about it, and the test database and the migrations would have drifted apart.

The fix isn't pretty, but it's clear. In the database, "no lot" is stored as `''` and "no expiry date" as `9999-12-31`, through two DBAL types. No real lot is empty and no product expires on that day. The domain still sees `null`. It's written up in [ADR 0011](../adr/0011-putaway-and-stock-booked-by-event-policies.md), with the options I rejected.

## Other contexts don't touch the ledger

Receiving (Inbound) books stock when a receipt closes and moves it when an operator confirms a putaway. But it doesn't know `StockItem` exists. It talks to Inventory through a published contract:

```php
interface StockMovements
{
    public function receive(StockArrival $arrival): void;

    public function transfer(StockTransfer $transfer): void;
}
```

The DTOs carry strings and ints, not Inventory's domain objects. The same architecture test stops a context from importing anything from another one except its `Application/Contract`. The ledger has one way in.

## Reading stock: the part I just finished

A ledger is great to write to and awkward to read from. A stock screen doesn't want a `product_id` and a `location_id`. It wants the SKU, the product name, the location code and the warehouse. That data lives in Catalog and Warehouse, and the project's rule is no SQL joins between the tables of different contexts.

I weighed enriching every page through the contracts (60 calls to list 30 stock items, and no sorting by SKU in SQL) and a database view across the tables (quick to write, and exactly what the rule forbids). I went with a projection:

1. Every movement publishes `StockMovementRecorded`.
2. The event goes to a Redis stream, and the Messenger worker handles it after the change commits.
3. A policy loads the stock item, asks the contracts for the product and the location, and writes a row in `stock_overview`.

Step 3 has a catch. The obvious move is `on_hand = on_hand + change` for each event. But a worker can get the same message twice, or two messages out of order, and adding twice is exactly the kind of error the ledger was supposed to rule out. So the policy doesn't add anything. It reads the stock item as it is now and writes the whole row with an upsert:

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

Handling it twice gives the same row. No table of processed messages needed.

The table is written and read with plain SQL (DBAL). A read model has no rules to protect, and loading entities would only add cost. And here "no lot" is `NULL` again, because this table has no unique key to defend.

What I like most is that the read model is disposable. If it gets corrupted, if its shape changes, or if someone renames a product, `make stock-rebuild` empties it and rebuilds it from the ledger in one transaction. Anyone reading meanwhile sees the old rows. That guarantee only exists because stock is a list of facts and not a loose number.

## What it costs

None of this is free, and I'd rather say so:

- It's eventually consistent. If an operator puts a box away and refreshes the list at once, it may not be there yet. In the tests the transport is synchronous, so you don't notice. In production it's usually a fraction of a second, but it's there.
- The SKU, the name and the codes are copies. Rename a product and old rows don't find out until that stock item moves again or the view is rebuilt. When a screen needs it, Catalog will publish a `ProductRenamed` event.
- Stock items aren't locked yet. With a single worker that's not a real problem. When order allocation arrives (FEFO and FIFO), it will be.
- There are more moving parts: an event, a policy, a table, a rebuild command and an ADR. For a CRUD app it would be overkill. For a warehouse, where "where did these units come from?" needs an answer, I think it pays off.

## What's next

Orders and stock allocation: choosing which stock items cover each line, earliest expiry first, and making sure two orders don't take the same box. That's where locking will start to matter.

The code is on [GitHub](https://github.com/Fit0/estiba-wms). The decisions in this post are in ADRs [0011](../adr/0011-putaway-and-stock-booked-by-event-policies.md), [0012](../adr/0012-asynchronous-policies-with-a-redis-transport.md) and [0013](../adr/0013-a-stock-read-model-fed-by-the-ledger.md).
