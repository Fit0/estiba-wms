<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Erp\Domain\ErpOrder;
use App\Erp\Domain\ErpOrderLine;
use App\Erp\Domain\ErpUrgency;
use App\Erp\Domain\Exception\UnknownArticle;
use App\Outbound\Application\Contract\IncomingOrder;
use App\Outbound\Application\Contract\IncomingOrderLine;
use App\Outbound\Application\Contract\IncomingOrderPriority;
use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * Turns an order of the ERP into an order of the warehouse: the article becomes a product of the
 * catalogue, the letter of urgency a priority, and the order keeps the ERP's number as its reference.
 * This is the anti-corruption layer for orders (ADR 0047): what comes out knows nothing of the ERP.
 */
final readonly class ErpOrderTranslator
{
    public function __construct(private ProductCatalog $catalog)
    {
    }

    /**
     * @param string $warehouseId the warehouse that will serve the order: the ERP does not say
     *
     * @throws UnknownArticle when a line names an article the catalogue does not have
     */
    public function translate(TenantId $tenantId, string $warehouseId, ErpOrder $order): IncomingOrder
    {
        return new IncomingOrder(
            $order->number,
            $warehouseId,
            $order->customer,
            $order->street,
            $order->postalCode,
            $order->city,
            $order->countryCode,
            $this->priorityOf($order->urgency),
            $order->dueAt,
            array_map(fn (ErpOrderLine $line): IncomingOrderLine => new IncomingOrderLine($this->productOf($tenantId, $line->article)->id, $line->units), $order->lines),
        );
    }

    private function productOf(TenantId $tenantId, string $article): ProductSnapshot
    {
        try {
            $product = $this->catalog->findBySku($tenantId, $article);
        } catch (DomainError) {
            // A code that cannot be a SKU is an article the catalogue does not have.
            $product = null;
        }

        return $product ?? throw UnknownArticle::forSku($article);
    }

    private function priorityOf(ErpUrgency $urgency): IncomingOrderPriority
    {
        return match ($urgency) {
            ErpUrgency::Low => IncomingOrderPriority::Low,
            ErpUrgency::Normal => IncomingOrderPriority::Normal,
            ErpUrgency::High => IncomingOrderPriority::High,
            ErpUrgency::Urgent => IncomingOrderPriority::Urgent,
        };
    }
}
