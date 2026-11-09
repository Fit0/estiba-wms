<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Sftp;

use App\Erp\Application\ErpOrderInbox;
use App\Erp\Domain\Exception\ErpUnavailable;
use phpseclib3\Net\SFTP;

/**
 * The outbox of the ERP, read over SFTP (ADR 0048). The files wait in the directory; the ones already
 * looked at move to `processed/` or to `rejected/` inside it. The connection is made the first time it is
 * needed and kept for the rest of the process, and made again if it dropped.
 *
 * In development the server does not prove who it is: the host key is not checked. A production setup
 * pins the key of the ERP's server before it sends a real password.
 */
final class SftpErpOrderInbox implements ErpOrderInbox
{
    private const int TIMEOUT_SECONDS = 10;

    private const string PROCESSED = 'processed';

    private const string REJECTED = 'rejected';

    private ?SFTP $sftp = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        #[\SensitiveParameter]
        private readonly string $password,
        private readonly string $directory,
    ) {
    }

    #[\Override]
    public function pending(): array
    {
        $names = $this->connection()->nlist($this->directory);
        if (!\is_array($names)) {
            $this->disconnect();

            throw ErpUnavailable::because(\sprintf('the directory %s cannot be listed', $this->directory));
        }

        $files = [];
        foreach ($names as $name) {
            if (\is_string($name) && 1 === preg_match('/^[^.\/][^\/]*\.csv$/i', $name)) {
                $files[] = $name;
            }
        }
        sort($files);

        return $files;
    }

    #[\Override]
    public function read(string $name): string
    {
        $contents = $this->connection()->get($this->path($name));
        if (!\is_string($contents)) {
            $this->disconnect();

            throw ErpUnavailable::because(\sprintf('the file %s cannot be read', $name));
        }

        return $contents;
    }

    #[\Override]
    public function markProcessed(string $name): void
    {
        $this->moveTo(self::PROCESSED, $name);
    }

    #[\Override]
    public function markRejected(string $name): void
    {
        $this->moveTo(self::REJECTED, $name);
    }

    private function moveTo(string $folder, string $name): void
    {
        $sftp = $this->connection();
        $folderPath = $this->path($folder);
        if (!$sftp->is_dir($folderPath) && !$sftp->mkdir($folderPath)) {
            $this->disconnect();

            throw ErpUnavailable::because(\sprintf('the directory %s cannot be created', $folderPath));
        }

        $target = $folderPath.'/'.$name;
        if (false !== $sftp->stat($target)) {
            // The ERP sent a file with a name that was already dealt with: keep both.
            $target .= '.'.date('YmdHis');
        }

        if (!$sftp->rename($this->path($name), $target)) {
            $this->disconnect();

            throw ErpUnavailable::because(\sprintf('the file %s cannot be moved to %s', $name, $folder));
        }
    }

    private function path(string $name): string
    {
        return $this->directory.'/'.$name;
    }

    private function connection(): SFTP
    {
        if ($this->sftp instanceof SFTP) {
            return $this->sftp;
        }

        try {
            $sftp = new SFTP($this->host, $this->port, self::TIMEOUT_SECONDS);
            $loggedIn = $sftp->login($this->user, $this->password);
        } catch (\Throwable $error) {
            throw ErpUnavailable::because(\sprintf('the SFTP server %s:%d does not answer (%s)', $this->host, $this->port, $error->getMessage()));
        }

        if (!$loggedIn) {
            throw ErpUnavailable::because(\sprintf('the SFTP server %s:%d did not accept the user %s', $this->host, $this->port, $this->user));
        }

        return $this->sftp = $sftp;
    }

    private function disconnect(): void
    {
        $this->sftp?->disconnect();
        $this->sftp = null;
    }
}
