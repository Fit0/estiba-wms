<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Erp\Application\ImportLogEntry;
use App\Erp\Application\ImportResult;
use App\Erp\Infrastructure\Http\State\ErpImportProvider;

/**
 * A file of orders that the import dealt with: whether its orders were registered or it was rejected, and why (ADR 0050).
 */
#[ApiResource(
    shortName: 'ErpImport',
    operations: [
        new GetCollection(uriTemplate: '/erp/imports', provider: ErpImportProvider::class),
    ],
)]
final readonly class ErpImportResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $fileName,
        public ImportResult $result,
        public int $orders,
        public ?string $errorCode,
        public ?string $message,
        public string $recordedAt,
    ) {
    }

    public static function fromApplication(ImportLogEntry $entry): self
    {
        return new self($entry->id, $entry->fileName, $entry->result, $entry->orders, $entry->errorCode, $entry->message, $entry->recordedAt->format(\DATE_ATOM));
    }
}
