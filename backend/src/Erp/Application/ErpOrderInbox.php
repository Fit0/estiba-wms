<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Erp\Domain\Exception\ErpUnavailable;

/**
 * The place where the ERP leaves its order files, and where the ones already looked at are put aside so
 * they are not read again (ADR 0048). A file is never deleted: it moves to `processed` or to `rejected`.
 */
interface ErpOrderInbox
{
    /**
     * The names of the files waiting, oldest name first.
     *
     * @return list<string>
     *
     * @throws ErpUnavailable
     */
    public function pending(): array;

    /**
     * @return string the bytes of the file, as the ERP wrote them
     *
     * @throws ErpUnavailable
     */
    public function read(string $name): string;

    /**
     * @throws ErpUnavailable
     */
    public function markProcessed(string $name): void;

    /**
     * @throws ErpUnavailable
     */
    public function markRejected(string $name): void;
}
