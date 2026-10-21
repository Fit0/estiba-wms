<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\ExpectedLine;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptId;
use App\Inbound\Domain\ReceivableProduct;
use App\Inbound\Domain\SupplierName;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;

/**
 * Builds valid objects so each test only spells out what it is about.
 */
final class InboundMother
{
    public static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21T10:00:00+00:00');
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString(Ids::next());
    }

    public static function asnId(): AsnId
    {
        return AsnId::fromString(Ids::next());
    }

    public static function receiptId(): ReceiptId
    {
        return ReceiptId::fromString(Ids::next());
    }

    public static function dock(): LocationId
    {
        return LocationId::fromString(Ids::next());
    }

    /**
     * An active product that is not tracked by lot or expiry date.
     */
    public static function product(?ProductId $id = null): ReceivableProduct
    {
        return new ReceivableProduct($id ?? ProductId::fromString(Ids::next()), true, false, false);
    }

    /**
     * An active product tracked by lot and expiry date, like food.
     */
    public static function trackedProduct(): ReceivableProduct
    {
        return new ReceivableProduct(ProductId::fromString(Ids::next()), true, true, true);
    }

    /**
     * @param list<ExpectedLine>|null $lines one line of 40 units of a new product when not given
     */
    public static function asn(?array $lines = null, ?TenantId $tenantId = null, string $reference = 'ALB-2027-0153'): Asn
    {
        $asn = Asn::register(
            self::asnId(),
            $tenantId ?? self::tenantId(),
            AsnReference::fromString($reference),
            SupplierName::fromString('Textiles del Sur'),
            new \DateTimeImmutable('2026-10-24'),
            $lines ?? [new ExpectedLine(self::product(), 40)],
            new InMemoryAsnReferences(),
            self::at(),
        );
        $asn->pullEvents();

        return $asn;
    }

    /**
     * An ASN of the products with their units, and a receipt open against it, with no events left.
     *
     * @param array<string, array{ReceivableProduct, int}> $lines
     *
     * @return array{Asn, Receipt}
     */
    public static function openReceipt(array $lines): array
    {
        $asn = self::asn(array_values(array_map(
            static fn (array $line): ExpectedLine => new ExpectedLine($line[0], $line[1]),
            $lines,
        )));
        $receipt = $asn->openReceipt(self::receiptId(), self::dock(), self::at());
        $receipt->pullEvents();

        return [$asn, $receipt];
    }
}
