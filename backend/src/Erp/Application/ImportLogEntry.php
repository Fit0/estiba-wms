<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * One file that the import dealt with: its name, whether its orders were registered or it was rejected, and why.
 */
final readonly class ImportLogEntry
{
    /**
     * @param int         $orders    how many orders the file held and were registered or already were; none for a rejected file
     * @param string|null $errorCode the code of the rule that rejected the file
     * @param string|null $message   what the rule said, in English, for whoever looks at the panel
     */
    public function __construct(
        public string $id,
        public string $fileName,
        public ImportResult $result,
        public int $orders,
        public ?string $errorCode,
        public ?string $message,
        public \DateTimeImmutable $recordedAt,
    ) {
    }
}
