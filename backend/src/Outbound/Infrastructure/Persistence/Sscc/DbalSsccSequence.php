<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Sscc;

use App\Outbound\Domain\Sscc;
use App\Outbound\Domain\SsccSequence;
use Doctrine\DBAL\Connection;

/**
 * Gives the next serial of the company in one statement: the row is created on the first parcel and
 * counted up after that, and the database lock makes a second packer wait for the first. It runs
 * inside the transaction that closes the parcel, so a parcel that is refused gives its serial back.
 */
final readonly class DbalSsccSequence implements SsccSequence
{
    public function __construct(
        private Connection $connection,
        private string $companyPrefix,
        private int $extensionDigit = 0,
    ) {
    }

    #[\Override]
    public function next(): Sscc
    {
        $serial = $this->connection->fetchOne(
            'INSERT INTO sscc_serials (company_prefix, last_serial) VALUES (?, 1) '
            .'ON CONFLICT (company_prefix) DO UPDATE SET last_serial = sscc_serials.last_serial + 1 '
            .'RETURNING last_serial',
            [$this->companyPrefix],
        );
        \assert(is_numeric($serial));

        return Sscc::compose($this->extensionDigit, $this->companyPrefix, (int) $serial);
    }
}
