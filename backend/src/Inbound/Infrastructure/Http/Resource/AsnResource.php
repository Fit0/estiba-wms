<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnStatus;
use App\Inbound\Infrastructure\Http\Input\RegisterAsnInput;
use App\Inbound\Infrastructure\Http\State\AsnProvider;
use App\Inbound\Infrastructure\Http\State\CancelAsnProcessor;
use App\Inbound\Infrastructure\Http\State\CloseAsnProcessor;
use App\Inbound\Infrastructure\Http\State\RegisterAsnProcessor;

#[ApiResource(
    shortName: 'Asn',
    operations: [
        new GetCollection(uriTemplate: '/asns', provider: AsnProvider::class),
        new Get(uriTemplate: '/asns/{id}', provider: AsnProvider::class),
        new Post(uriTemplate: '/asns', input: RegisterAsnInput::class, processor: RegisterAsnProcessor::class),
        new Post(
            uriTemplate: '/asns/{id}/cancel',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: CancelAsnProcessor::class,
        ),
        new Post(
            uriTemplate: '/asns/{id}/close',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: CloseAsnProcessor::class,
        ),
    ],
)]
final readonly class AsnResource
{
    /**
     * @param list<AsnLineResource> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $reference,
        public string $supplier,
        public string $expectedDate,
        public AsnStatus $status,
        public ?string $openReceiptId,
        public array $lines,
    ) {
    }

    public static function fromDomain(Asn $asn): self
    {
        return new self(
            $asn->id()->value,
            $asn->reference()->value,
            $asn->supplier()->value,
            $asn->expectedDate()->format('Y-m-d'),
            $asn->status(),
            $asn->openReceiptId()?->value,
            array_map(AsnLineResource::fromDomain(...), $asn->lines()),
        );
    }
}
