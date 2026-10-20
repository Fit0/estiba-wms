<?php

declare(strict_types=1);

namespace App\Tests\Functional\Warehouse\Persistence;

use App\Tests\Functional\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Starts every test with an empty database, on the real one (see {@see TestDatabase}).
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

        TestDatabase::reset($this->entityManager);
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
