<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ReadModel;

use App\Identity\Application\Contract\UserDirectory;
use App\Identity\Domain\UserId;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Answers the {@see UserDirectory} contract with plain SQL over `users`. The query names the tenant
 * itself, because the tenant filter of the ORM does not see plain SQL.
 */
final readonly class DbalUserDirectory implements UserDirectory
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function emailsOf(TenantId $tenantId, array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $rows = $this->connection->executeQuery(
            'SELECT id, email FROM users WHERE tenant_id = :tenant AND id IN (:ids)',
            [
                'tenant' => $tenantId->value,
                'ids' => array_map(static fn (string $id): string => UserId::fromString($id)->value, $userIds),
            ],
            ['ids' => ArrayParameterType::STRING],
        )->fetchAllAssociative();

        $emails = [];
        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['email']));
            $emails[$row['id']] = $row['email'];
        }

        return $emails;
    }
}
