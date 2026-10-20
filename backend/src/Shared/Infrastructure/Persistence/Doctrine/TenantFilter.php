<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Adds `tenant_id = <the current tenant>` to every query on an entity that has a `tenantId`
 * field, so a query that forgets to ask for the tenant still cannot read another tenant's rows.
 * It is a second wall behind the use cases, which already check the tenant (ADR 0008).
 *
 * It fails closed: while the filter is enabled, the tenant parameter must be set, and Doctrine
 * throws if it is not. A missing tenant is an error, never "all tenants".
 *
 * It is enabled by {@see EnableTenantFilter} for requests of a signed-in user. Console commands,
 * migrations and the sign-in itself run without it.
 */
final class TenantFilter extends SQLFilter
{
    public const string NAME = 'tenant';

    public const string PARAMETER = 'tenant';

    #[\Override]
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('tenantId')) {
            return '';
        }

        return \sprintf('%s.%s = %s', $targetTableAlias, $targetEntity->getColumnName('tenantId'), $this->getParameter(self::PARAMETER));
    }
}
