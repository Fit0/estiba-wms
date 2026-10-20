<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Gives every functional test an empty database, cheaply.
 *
 * The schema is built from the mapping once per test run. Every later test only empties the tables,
 * which takes a few milliseconds, where dropping and creating the schema took seconds and made the
 * suite crawl. The migrations are checked apart, by running them and validating the schema.
 */
final class TestDatabase
{
    private static bool $schemaBuilt = false;

    public static function reset(EntityManagerInterface $entityManager): void
    {
        $tables = self::tablesOf($entityManager);

        if (!self::$schemaBuilt) {
            $schemaTool = new SchemaTool($entityManager);
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            self::$schemaBuilt = true;

            return;
        }

        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
        $entityManager->clear();
    }

    /**
     * @return list<string> the quoted name of every table the mapping owns
     */
    private static function tablesOf(EntityManagerInterface $entityManager): array
    {
        $connection = $entityManager->getConnection();
        $tables = [];

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isEmbeddedClass || $metadata->isMappedSuperclass) {
                continue;
            }

            $tables[$metadata->getTableName()] = $connection->quoteSingleIdentifier($metadata->getTableName());
        }

        return array_values($tables);
    }
}
