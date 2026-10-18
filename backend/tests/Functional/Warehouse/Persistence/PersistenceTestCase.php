<?php

declare(strict_types=1);

namespace App\Tests\Functional\Warehouse\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Starts every test with an empty schema built from the mapping, on the real database.
 * The migrations are checked apart, by running them and validating the schema.
 */
abstract class PersistenceTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Writes pending changes and forgets every loaded object, so the next read comes from the database.
     */
    protected function flushAndForget(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
