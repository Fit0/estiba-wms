<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025100923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dispatch: the carrier, the tracking number and the date of a shipment that has gone out';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipments ADD carrier VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE shipments ADD tracking_number VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE shipments ADD dispatched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shipments ALTER status TYPE VARCHAR(10)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipments DROP carrier');
        $this->addSql('ALTER TABLE shipments DROP tracking_number');
        $this->addSql('ALTER TABLE shipments DROP dispatched_at');
        $this->addSql('ALTER TABLE shipments ALTER status TYPE VARCHAR(7)');
    }
}
